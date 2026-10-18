<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['iyawe.system_admin' => [
            'name' => 'Platform Administrator',
            'email' => 'platform@example.test',
            'password' => 'SeededPassword123!',
        ]]);
    }

    public function test_seeded_system_administrator_can_sign_in_and_create_companies(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'platform@example.test')->firstOrFail();
        $this->assertTrue($user->is_system_admin);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('SeededPassword123!', $user->password));
        $this->assertDatabaseCount('company_user', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'platform.administrator.seeded', 'subject_id' => $user->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'SeededPassword123!'])->assertRedirect('/dashboard');
        $this->get('/companies/create')->assertOk();
        $this->post('/companies', ['name' => 'Created through UI', 'slug' => 'created-through-ui', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['slug' => 'created-through-ui']);
        $this->get('/staff')->assertForbidden();
    }

    public function test_reseeding_preserves_existing_administrator_credentials_and_account_status(): void
    {
        $this->seed(SystemAdminSeeder::class);
        $user = User::where('email', 'platform@example.test')->firstOrFail();
        $originalPassword = $user->password;
        $user->forceFill(['active' => false, 'name' => 'Updated profile'])->save();
        config(['iyawe.system_admin.password' => null]);
        $this->seed(SystemAdminSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame('Updated profile', $user->fresh()->name);
        $this->assertFalse($user->fresh()->active);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_existing_staff_account_is_not_promoted_or_overwritten(): void
    {
        $staff = User::factory()->create(['email' => 'platform@example.test']);
        $originalPassword = $staff->password;
        try {
            $this->seed(SystemAdminSeeder::class);
            $this->fail('An existing staff account must not be promoted.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('SeededPassword123!', $exception->getMessage());
            $this->assertFalse($staff->fresh()->is_system_admin);
            $this->assertSame($originalPassword, $staff->fresh()->password);
        }
    }

    public function test_missing_credentials_skip_bootstrap_without_creating_demo_accounts(): void
    {
        config(['iyawe.system_admin.email' => null, 'iyawe.system_admin.password' => null]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('permissions', count(CompanyAccess::PERMISSIONS));
    }

    public function test_incomplete_or_weak_credentials_are_rejected_without_secret_disclosure(): void
    {
        config(['iyawe.system_admin.password' => 'weak-secret']);
        try {
            $this->seed(SystemAdminSeeder::class);
            $this->fail('Weak credentials must not create an administrator.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('weak-secret', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_invalid_email_is_rejected(): void
    {
        config(['iyawe.system_admin.email' => 'invalid']);
        $this->expectException(RuntimeException::class);
        $this->seed(SystemAdminSeeder::class);
    }

    public function test_roles_seeder_is_idempotent_and_preserves_custom_company_roles(): void
    {
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $role = Role::create(['name' => 'Reporting Officer', 'guard_name' => 'web', 'company_id' => $company->id]);
        $role->syncPermissions(['reports.view']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', count(CompanyAccess::PERMISSIONS));
        $this->assertTrue($role->fresh()->hasPermissionTo('reports.view'));
        $this->assertDatabaseMissing('roles', ['company_id' => null]);
    }
}
