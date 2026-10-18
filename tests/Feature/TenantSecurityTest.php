<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function workspace(string $roleName = 'Company Administrator', ?User $user = null): array
    {
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $user ??= User::factory()->create();
        $company->users()->attach($user, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->unsetRelation('roles')->assignRole(Role::where('company_id', $company->id)->where('name', $roleName)->firstOrFail());
        setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [$company, $user];
    }

    public function test_cross_company_records_and_role_ids_are_denied(): void
    {
        [$a, $admin] = $this->workspace();
        [$b, $other] = $this->workspace();
        $role = Role::where('company_id', $b->id)->where('name', 'Field Staff')->firstOrFail();
        $location = Location::create(['company_id' => $b->id, 'name' => 'Secret location']);
        $this->actingAs($admin)->get('/staff/'.$other->id.'/edit')->assertNotFound();
        $this->put('/staff/'.$other->id, ['name' => 'Tampered', 'active' => 0, 'roles' => []])->assertNotFound();
        $this->get('/roles/'.$role->id.'/edit')->assertNotFound();
        $this->put('/roles/'.$role->id, ['name' => 'Intruder', 'permissions' => []])->assertNotFound();
        $this->put('/locations/'.$location->id, ['name' => 'Intruder', 'active' => 0])->assertNotFound();
        $this->post('/staff', ['name' => 'Intruder', 'email' => 'intruder@example.test', 'password' => 'StrongPassword123!', 'active' => 1, 'roles' => [$role->id]])->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
        $this->get('/staff?company_id='.$b->id)->assertOk()->assertDontSee($other->email);
    }

    public function test_company_switching_resets_permissions_even_for_same_user_instance(): void
    {
        [$a, $user] = $this->workspace();
        [$b] = $this->workspace('Field Staff', $user);
        $this->actingAs($user)->withSession(['company_id' => $a->id])->get('/staff')->assertOk();
        $this->post('/company/switch', ['company_id' => $b->id])->assertRedirect('/dashboard')->assertSessionHas('company_id', $b->id);
        $this->get('/staff')->assertForbidden();
        $this->get('/roles')->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertSee($b->name)->assertSee('Coming in Milestone 2');
        $this->post('/company/switch', ['company_id' => $a->id])->assertRedirect('/dashboard');
        $this->get('/roles')->assertOk();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_company_selection_requires_active_membership_and_company(): void
    {
        [$a, $user] = $this->workspace();
        [$b] = $this->workspace();
        $this->actingAs($user)->post('/company/switch', ['company_id' => $b->id])->assertNotFound();
        $a->update(['status' => 'suspended']);
        $this->post('/company/switch', ['company_id' => $a->id])->assertNotFound();
        $this->get('/staff')->assertForbidden();
        $this->post('/roles', ['name' => 'Blocked', 'permissions' => []])->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertSee('No active company');
    }

    public function test_deactivated_membership_is_rechecked_on_every_request(): void
    {
        [$a, $user] = $this->workspace();
        $this->actingAs($user)->get('/staff')->assertOk();
        $a->users()->updateExistingPivot($user->id, ['active' => false]);
        $this->get('/staff')->assertForbidden();
        $this->post('/invitations/'.$a->id.'/accept')->assertNotFound();
    }

    public function test_unauthorized_administration_and_platform_escalation_denied(): void
    {
        [$company, $field] = $this->workspace('Field Staff');
        $this->actingAs($field);
        foreach (['/staff', '/staff/create', '/roles', '/roles/create', '/locations', '/companies', '/companies/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/companies', ['name' => 'Escalation'])->assertForbidden();
        $this->post('/roles', ['name' => 'Escalation'])->assertForbidden();
        $this->post('/staff', ['name' => 'Escalation'])->assertForbidden();
        $this->put('/settings/company', ['name' => 'Escalation'])->assertForbidden();
        $this->put('/settings', ['name' => $field->name, 'email' => $field->email, 'current_password' => 'password', 'locale' => 'en', 'is_system_admin' => true, 'active' => true])->assertSessionHasNoErrors();
        $this->assertFalse($field->fresh()->is_system_admin);
    }

    public function test_platform_administrator_has_no_implicit_company_access(): void
    {
        $this->workspace();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->get('/companies')->assertOk();
        $this->get('/staff')->assertForbidden();
        $this->get('/locations')->assertForbidden();
        $this->get('/roles')->assertForbidden();
        $this->get('/dashboard')->assertOk();
    }

    public function test_reserved_roles_and_platform_permissions_cannot_be_modified(): void
    {
        [$company, $admin] = $this->workspace();
        $reserved = Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail();
        $this->actingAs($admin)->put('/roles/'.$reserved->id, ['name' => 'Changed', 'permissions' => []])->assertForbidden();
        $this->post('/roles', ['name' => 'Escalation', 'permissions' => ['platform.manage']])->assertSessionHasErrors('permissions.0');
        $this->post('/roles', ['name' => 'System Administrator', 'permissions' => []])->assertSessionHasErrors('name');
    }

    public function test_last_active_administrator_cannot_be_deactivated_or_demoted(): void
    {
        [$company, $admin] = $this->workspace();
        $this->actingAs($admin)->put('/staff/'.$admin->id, ['name' => $admin->name, 'active' => 0, 'roles' => []])->assertSessionHasErrors('roles');
        $this->put('/staff/'.$admin->id, ['name' => $admin->name, 'active' => 1, 'roles' => []])->assertSessionHasErrors('roles');
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $admin->id, 'active' => true]);
    }

    public function test_existing_account_requires_owner_acceptance_and_credentials_are_preserved(): void
    {
        [$company, $admin] = $this->workspace();
        $owner = User::factory()->create();
        $originalPassword = $owner->password;
        $role = Role::where('company_id', $company->id)->where('name', 'Field Staff')->firstOrFail();
        $this->actingAs($admin)->post('/staff', ['name' => 'Local name', 'email' => $owner->email, 'password' => 'UntrustedPassword123', 'active' => 1, 'roles' => [$role->id]])->assertSessionHasNoErrors();
        $this->assertSame($originalPassword, $owner->fresh()->password);
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $owner->id, 'active' => false, 'pending' => true]);
        $this->actingAs($owner)->post('/company/switch', ['company_id' => $company->id])->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertSee('Accept membership');
        $this->post('/invitations/'.$company->id.'/accept')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee($company->name);
        $this->get('/staff')->assertForbidden();
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $owner->id, 'active' => true, 'pending' => false]);
    }

    public function test_membership_manager_without_role_management_cannot_assign_admin(): void
    {
        [$company, $user] = $this->workspace('Field Staff');
        $role = Role::create(['company_id' => $company->id, 'name' => 'Staff Manager', 'guard_name' => 'web']);
        $role->syncPermissions(['staff.manage']);
        setPermissionsTeamId($company->id);
        $user->syncRoles([$role]);
        setPermissionsTeamId(null);
        $adminRole = Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail();
        $this->actingAs($user)->post('/staff', ['name' => 'Escalation', 'email' => 'bad@example.test', 'password' => 'StrongPassword123!', 'active' => 1, 'roles' => [$adminRole->id]])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'bad@example.test']);
    }
}
