<?php

namespace Tests\Feature;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\DocumentBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Milestone3Test extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'Company Administrator'): array
    {
        $this->withoutVite();
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->assignRole(Role::where('company_id', $company->id)->where('name', $role)->firstOrFail());
        setPermissionsTeamId(null);
        $this->actingAs($user);

        return [$company, $user];
    }

    public function test_partial_drafts_are_revision_checked_tenant_scoped_and_discarded_with_retention(): void
    {
        [$company, $user] = $this->staff();
        $swap = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id]);
        $this->patchJson('/swaps/'.$swap->id.'/draft', ['revision' => 0, 'step' => 1, 'commuter_name' => 'Example', 'national_id' => '000123'])->assertOk()->assertJsonPath('revision', 1);
        $this->assertSame('000123', $swap->fresh()->national_id);
        $this->assertStringNotContainsString('000123', $swap->fresh()->getRawOriginal('national_id'));
        $this->patchJson('/swaps/'.$swap->id.'/draft', ['revision' => 0, 'step' => 1, 'commuter_name' => 'Stale'])->assertConflict();
        $foreign = CardSwap::factory()->create();
        $this->patchJson('/swaps/'.$foreign->id.'/draft', ['revision' => 0, 'step' => 1])->assertNotFound();
        $this->get('/drafts')->assertOk()->assertSee($swap->reference)->assertDontSee($foreign->reference);
        $this->delete('/swaps/'.$swap->id.'/draft', ['revision' => 1])->assertRedirect('/drafts');
        $this->assertSoftDeleted('card_swaps', ['id' => $swap->id]);
        $this->assertDatabaseHas('swap_events', ['card_swap_id' => $swap->id, 'type' => 'draft.discarded']);
        $this->get('/swaps/'.$swap->id.'/continue')->assertNotFound();
    }

    public function test_branding_is_validated_encrypted_and_applied_to_scoped_reports(): void
    {
        [$company] = $this->staff();
        $this->put('/settings/branding', ['brand_legal_name' => 'Example Legal Ltd', 'brand_report_title' => 'Company operations', 'brand_logo' => UploadedFile::fake()->image('logo.png', 100, 60)])->assertSessionHasNoErrors()->assertRedirect();
        $company->refresh();
        $this->assertStringNotContainsString('Example Legal', $company->getRawOriginal('document_branding'));
        $brand = app(DocumentBranding::class)->current($company);
        $this->assertSame('Example Legal Ltd', $brand['legal_name']);
        $this->assertStringStartsWith('data:image/png;base64,', $brand['logo_data_uri']);
        $this->get('/reports')->assertOk()->assertSee('Example Legal Ltd');
        $this->get('/reports/print')->assertOk()->assertSee('Example Legal Ltd')->assertSee('Company operations');
        $this->get('/reports/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $svg = UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->putJson('/settings/branding', ['brand_logo' => $svg])->assertUnprocessable()->assertJsonValidationErrors('brand_logo');
        $other = Company::factory()->create(['name' => 'Another company']);
        $this->assertSame('Another company', app(DocumentBranding::class)->current($other)['legal_name']);
        $this->assertNull(app(DocumentBranding::class)->current($other)['logo_data_uri']);
    }

    public function test_company_members_require_branding_permission(): void
    {
        [$company, $user] = $this->staff();
        setPermissionsTeamId($company->id);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Limited', 'guard_name' => 'web']);
        $role->givePermissionTo(['swaps.view', 'swaps.update-pending']);
        $user->syncRoles([$role]);
        setPermissionsTeamId(null);
        $this->get('/settings/branding')->assertForbidden();
        $this->put('/settings/branding', ['brand_legal_name' => 'Unauthorised'])->assertForbidden();
        $other = User::factory()->create();
        $swap = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $other->id]);
        $this->patchJson('/swaps/'.$swap->id.'/draft', ['revision' => 0, 'step' => 1])->assertNotFound();
    }

    public function test_customer_handoff_protects_staff_navigation_and_requires_password(): void
    {
        [$company,$user] = $this->staff();
        $swap = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'step' => 3]);
        $this->post('/swaps/'.$swap->id.'/customer', ['language' => 'rw'])->assertRedirect();
        $this->get('/swaps/'.$swap->id.'/continue')->assertOk()->assertSee('customer-mode')->assertDontSee('sidebar-caption');
        $this->getJson('/reports')->assertStatus(423);
        $this->postJson('/company/switch', ['company_id' => $company->id])->assertStatus(423);
        $this->postJson('/swaps/'.$swap->id.'/steps/4', ['revision' => 0])->assertStatus(423);
        $this->post('/swaps/'.$swap->id.'/customer/return', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->post('/swaps/'.$swap->id.'/customer/return', ['current_password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/swaps/'.$swap->id.'/continue');
        $this->get('/reports')->assertOk();
    }

    public function test_historical_branding_does_not_follow_later_company_changes(): void
    {
        $company = Company::factory()->create(['name' => 'Original']);
        $service = app(DocumentBranding::class);
        $service->update($company, ['brand_legal_name' => 'Original Legal']);
        $swap = CardSwap::factory()->create(['company_id' => $company->id, 'company_name_snapshot' => 'Legacy Name', 'branding_snapshot' => $service->current($company)]);
        $service->update($company, ['brand_legal_name' => 'Renamed Legal']);
        $this->assertSame('Original Legal', $service->historical($swap)['legal_name']);
        $swap->branding_snapshot = null;
        $this->assertSame('Legacy Name', $service->historical($swap)['legal_name']);
        $this->assertNull($service->historical($swap)['logo_data_uri']);
    }
}
