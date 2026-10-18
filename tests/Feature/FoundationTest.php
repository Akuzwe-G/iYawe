<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function administrator(): array
    {
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $user = User::factory()->create();
        $company->users()->attach($user, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);

        return [$company, $user];
    }

    public function test_system_administrator_can_create_edit_and_suspend_company(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->post('/companies', ['name' => 'Example Co', 'slug' => 'example-co', 'status' => 'active'])->assertSessionHasNoErrors()->assertRedirect();
        $company = Company::firstOrFail();
        $this->assertDatabaseHas('roles', ['company_id' => $company->id, 'name' => 'Company Administrator']);
        $this->get('/companies')->assertOk()->assertSee('Example Co');
        $this->get('/companies/create')->assertOk();
        $this->get('/companies/'.$company->id.'/edit')->assertOk();
        $this->put('/companies/'.$company->id, ['name' => 'Renamed Co', 'slug' => 'example-co', 'status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.saved', 'company_id' => $company->id]);
    }

    public function test_initial_company_administrator_assignment(): void
    {
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $this->actingAs(User::factory()->create(['is_system_admin' => true]))
            ->post('/companies/'.$company->id.'/administrator', ['name' => 'Company Admin', 'email' => 'admin@example.test', 'password' => 'StrongPassword123!'])
            ->assertSessionHasNoErrors();
        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertDatabaseHas('company_user', ['user_id' => $user->id, 'company_id' => $company->id, 'active' => true]);
        setPermissionsTeamId($company->id);
        $this->assertTrue($user->hasRole('Company Administrator'));
        setPermissionsTeamId(null);
        $this->assertFalse($user->is_system_admin);
    }

    public function test_company_administrator_can_create_staff_and_scoped_role_assignment(): void
    {
        [$company, $admin] = $this->administrator();
        $role = Role::where('company_id', $company->id)->where('name', 'Field Staff')->firstOrFail();
        $this->actingAs($admin)->post('/staff', ['name' => 'Field Agent', 'email' => 'field@example.test', 'password' => 'StrongPassword123!', 'active' => 1, 'roles' => [$role->id]])->assertSessionHasNoErrors()->assertRedirect('/staff');
        $staff = User::where('email', 'field@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPassword123!', $staff->password));
        $this->assertDatabaseHas('model_has_roles', ['company_id' => $company->id, 'model_id' => $staff->id, 'role_id' => $role->id]);
        $this->get('/staff')->assertOk()->assertSee('Field Agent');
        $this->get('/staff/create')->assertOk();
        $this->get('/staff/'.$staff->id.'/edit')->assertOk();
        $this->put('/staff/'.$staff->id, ['name' => 'Updated Agent', 'active' => 0, 'roles' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $staff->id, 'active' => false, 'display_name' => 'Updated Agent']);
    }

    public function test_custom_roles_and_locations_and_settings_work(): void
    {
        [$company, $admin] = $this->administrator();
        $this->actingAs($admin)->get('/roles')->assertOk();
        $this->get('/roles/create')->assertOk();
        $this->post('/roles', ['name' => 'Supervisor', 'permissions' => ['reports.view']])->assertSessionHasNoErrors()->assertRedirect('/roles');
        $role = Role::where('name', 'Supervisor')->firstOrFail();
        $this->get('/roles/'.$role->id.'/edit')->assertOk();
        $this->put('/roles/'.$role->id, ['name' => 'Reporting Officer', 'permissions' => ['reports.view', 'field.access']])->assertSessionHasNoErrors();
        $this->post('/locations', ['name' => 'Central', 'address' => 'Kigali', 'active' => 1])->assertSessionHasNoErrors();
        $this->get('/locations')->assertOk()->assertSee('Central');
        $this->put('/locations/1', ['name' => 'Central', 'address' => 'Kigali', 'active' => 0])->assertSessionHasNoErrors();
        $this->get('/settings')->assertOk();
        $this->put('/settings/company', ['name' => 'Updated company', 'email' => 'office@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('Updated company', $company->fresh()->name);
        $this->get('/dashboard')->assertOk();
    }

    public function test_first_administrator_command_uses_hidden_password_and_refuses_existing_account(): void
    {
        $this->artisan('iyawe:admin', ['email' => 'first@example.test', '--name' => 'First Administrator'])
            ->expectsQuestion('Password (12+ characters, letters and numbers)', 'StrongPassword123!')
            ->expectsQuestion('Confirm password', 'StrongPassword123!')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'first@example.test', 'is_system_admin' => true]);
        $this->artisan('iyawe:admin', ['email' => 'first@example.test', '--name' => 'Other'])
            ->expectsQuestion('Password (12+ characters, letters and numbers)', 'StrongPassword123!')
            ->expectsQuestion('Confirm password', 'StrongPassword123!')->assertFailed();
    }
}
