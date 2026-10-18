<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdditionalSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_password_recovery_is_hidden_and_blocked_without_delivery(): void
    {
        config(['iyawe.password_reset_enabled' => false]);
        Notification::fake();
        $user = User::factory()->create();
        $this->get('/login')->assertOk()->assertDontSee('Forgot Password?');
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();
        $this->get('/reset-password/example')->assertNotFound();
        $this->post('/reset-password', ['email' => $user->email])->assertNotFound();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_multiple_companies_require_explicit_selection_and_reject_untrusted_context(): void
    {
        $user = User::factory()->create();
        $companies = Company::factory()->count(2)->create();
        foreach ($companies as $company) {
            CompanyAccess::provision($company);
            $company->users()->attach($user->id, ['active' => true]);
            setPermissionsTeamId($company->id);
            $user->unsetRelation('roles')->assignRole(Role::where('company_id', $company->id)->where('name', 'Field Staff')->firstOrFail());
        }
        setPermissionsTeamId(null);
        $this->actingAs($user)->withSession(['company_id' => 999999])->get('/dashboard')->assertOk()->assertSee('Select your workspace')->assertSessionMissing('company_id');
        $this->get('/staff')->assertForbidden();
        $this->post('/company/switch', ['company_id' => $companies->first()->id])->assertSessionHas('company_id', $companies->first()->id);
    }

    public function test_revoking_roles_in_one_company_preserves_other_company_roles(): void
    {
        $companies = Company::factory()->count(2)->create();
        $admin = User::factory()->create();
        $staff = User::factory()->create();
        foreach ($companies as $company) {
            CompanyAccess::provision($company);
            $company->users()->attach($admin->id, ['active' => true]);
            $company->users()->attach($staff->id, ['active' => true]);
            setPermissionsTeamId($company->id);
            $admin->unsetRelation('roles')->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
            $staff->unsetRelation('roles')->assignRole(Role::where('company_id', $company->id)->where('name', 'Field Staff')->firstOrFail());
        }
        setPermissionsTeamId(null);
        $this->actingAs($admin)->withSession(['company_id' => $companies->first()->id])
            ->put('/staff/'.$staff->id, ['name' => 'Local display', 'active' => 1, 'roles' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('model_has_roles', ['company_id' => $companies->first()->id, 'model_id' => $staff->id]);
        $this->assertDatabaseHas('model_has_roles', ['company_id' => $companies->last()->id, 'model_id' => $staff->id]);
    }

    public function test_new_staff_membership_can_be_created_inactive(): void
    {
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $admin = User::factory()->create();
        $company->users()->attach($admin->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $admin->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);
        $this->actingAs($admin)->post('/staff', ['name' => 'Inactive', 'email' => 'inactive@example.test', 'password' => 'StrongPassword123!', 'active' => 0, 'roles' => []])->assertSessionHasNoErrors();
        $staff = User::where('email', 'inactive@example.test')->firstOrFail();
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $staff->id, 'active' => false, 'pending' => false]);
    }

    public function test_shared_account_identity_and_password_cannot_be_overwritten_by_company_admin(): void
    {
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $admin = User::factory()->create();
        $staff = User::factory()->create();
        $company->users()->attach([$admin->id, $staff->id], ['active' => true]);
        setPermissionsTeamId($company->id);
        $admin->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);
        $email = $staff->email;
        $password = $staff->password;
        $this->actingAs($admin)->put('/staff/'.$staff->id, ['name' => 'Local name', 'active' => 1, 'roles' => [], 'email' => 'takeover@example.test', 'password' => 'StrongPassword123!'])->assertSessionHasErrors(['email', 'password']);
        $this->assertSame($email, $staff->fresh()->email);
        $this->assertSame($password, $staff->fresh()->password);
    }

    public function test_only_platform_administrators_can_manage_platform_preferences(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->put('/settings/platform', ['default_locale' => 'rw', 'support_email' => 'support@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('platform_settings', ['key' => 'default_locale', 'value' => 'rw']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'platform.settings.updated']);
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertSee('lang="rw"', false)->assertSee('Injira')->assertSee('mailto:support@example.test', false);
        $this->post('/login', ['email' => 'invalid@example.test', 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'Aya makuru ntahuye na konti ikora.']);
        $user = User::factory()->create();
        $this->actingAs($user)->put('/settings/platform', ['default_locale' => 'en'])->assertForbidden();
    }
}
