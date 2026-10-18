<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_local_montserrat_assets_and_responsive_layout(): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Build frontend assets to verify the production font output.');
        }
        $this->get('/login')->assertOk()->assertSee('montserrat-400')->assertSee('montserrat-700')
            ->assertSee('name="viewport"', false)->assertDontSee('fonts.googleapis.com')->assertDontSee('Instrument Sans')->assertDontSee('Poppins');
    }

    public function test_company_screens_render_in_kinyarwanda(): void
    {
        $this->withoutVite();
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $admin = User::factory()->create(['locale' => 'rw']);
        $company->users()->attach($admin->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $admin->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);
        $this->actingAs($admin);
        foreach (['/dashboard', '/staff', '/staff/create', '/roles', '/roles/create', '/locations', '/settings'] as $route) {
            $this->get($route)->assertOk()->assertSee('lang="rw"', false)->assertDontSee('â–')->assertDontSee('Â©');
        }
        $this->post('/roles', ['name' => '', 'permissions' => []])->assertSessionHasErrors('name');
        $this->get('/roles/create')->assertOk()->assertSee('Izina');
    }
}
