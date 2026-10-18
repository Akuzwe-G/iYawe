<?php

namespace Tests\Feature;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\ConsentVersion;
use App\Models\Location;
use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\DocumentBranding;
use App\Support\PrivateDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StagingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['filesystems.disks.archive' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/archive')], 'iyawe.documents_disk' => 'archive']);
        Storage::fake('archive');
        Storage::fake('local');
    }

    private function staff(): array
    {
        $company = Company::factory()->create(['name' => 'Fictional Transport']);
        CompanyAccess::provision($company);
        Location::create(['company_id' => $company->id, 'name' => 'Test service location', 'active' => true]);
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);
        $this->actingAs($user);

        return [$company, $user];
    }

    private function signature(): string
    {
        $im = imagecreatetruecolor(320, 180);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        $ink = imagecolorallocate($im, 23, 43, 77);
        imagesetthickness($im, 3);
        imageline($im, 20, 120, 80, 40, $ink);
        imageline($im, 80, 40, 140, 130, $ink);
        imageline($im, 140, 130, 260, 60, $ink);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function test_approved_template_publication_is_authorised_audited_idempotent_and_preserves_custom_versions(): void
    {
        [$company,$user] = $this->staff();
        $this->get('/consents')->assertSee('formally approved')->assertDontSee('production approval pending');
        $this->artisan('iyawe:consent-publish', ['company' => $company->slug, 'language' => 'rw', '--approved-by' => $user->email])->assertSuccessful();
        $version = ConsentVersion::firstOrFail();
        $this->assertSame(config('consent.rw.declaration'), $version->declaration);
        $this->assertSame(6, substr_count($version->declaration, "\n- "));
        $this->assertSame('approved', $version->publication_evidence['template_approval']['status']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'company_id' => $company->id, 'action' => 'consent.version.published']);
        $this->artisan('iyawe:consent-publish', ['company' => $company->slug, 'language' => 'rw', '--approved-by' => $user->email])->assertSuccessful();
        $this->assertDatabaseCount('consent_versions', 1);
        $data = ['language' => 'rw', 'version' => 'custom-rw-v2', 'declaration' => 'Separately approved wording', 'receipt' => 'Separate receipt', 'approved' => 1];
        $this->post('/consents', $data)->assertSessionHasErrors('replace_current');
        $this->assertTrue($version->fresh()->active);
        $this->post('/consents', $data + ['replace_current' => 1])->assertSessionHasNoErrors();
        $this->artisan('iyawe:consent-publish', ['company' => $company->slug, 'language' => 'rw', '--approved-by' => $user->email])->assertFailed();
        $this->assertDatabaseHas('consent_versions', ['version' => 'custom-rw-v2', 'active' => true]);
        $other = User::factory()->create();
        $this->artisan('iyawe:consent-publish', ['company' => $company->slug, 'language' => 'en', '--approved-by' => $other->email])->assertFailed();
    }

    public function test_both_languages_complete_all_steps_on_configured_private_storage_and_preserve_pdfs(): void
    {
        [$company,$user] = $this->staff();
        app(DocumentBranding::class)->update($company, ['brand_legal_name' => 'Fictional Legal Ltd', 'brand_footer' => 'Fictional footer']);
        foreach (['en', 'rw'] as $language) {
            $template = config('consent.'.$language);
            $this->post('/consents', ['language' => $language, 'version' => $template['version'], 'declaration' => $template['declaration'], 'receipt' => $template['receipt'], 'approved' => 1])->assertSessionHasNoErrors();
            $version = ConsentVersion::where('language', $language)->firstOrFail();
            $key = (string) Str::uuid();
            $this->post('/swaps', ['submission_key' => $key])->assertRedirect();
            $this->post('/swaps', ['submission_key' => $key])->assertRedirect();
            $swap = CardSwap::where('submission_key', $key)->firstOrFail();
            $this->assertSame(1, CardSwap::where('submission_key', $key)->count());
            $this->postJson('/swaps/'.$swap->id.'/steps/1', ['revision' => 0])->assertUnprocessable()->assertJsonValidationErrors(['national_id', 'commuter_name']);
            $this->postJson('/swaps/'.$swap->id.'/steps/1', ['location_id' => Location::where('company_id', $company->id)->value('id'), 'revision' => 0, 'identification_type' => $language === 'en' ? 'passport' : 'foreign_id', 'swap_date' => today()->format('Y-m-d'), 'commuter_name' => 'Fictional Rider', 'phone' => '+250788123456', 'national_id' => '0000000000000123', 'email' => ''])->assertOk();
            $this->postJson('/swaps/'.$swap->id.'/steps/2', ['revision' => 1, 'old_card' => '00000123', 'new_card' => '00000456', 'old_balance' => '1000', 'new_balance' => '1000', 'reason' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('reason_other');
            $this->postJson('/swaps/'.$swap->id.'/steps/2', ['revision' => 1, 'old_card' => '00000123', 'new_card' => '00000456', 'old_balance' => '1000', 'new_balance' => '1000', 'reason' => 'other', 'reason_other' => 'Fictional acceptance reason'])->assertOk();
            $this->post('/swaps/'.$swap->id.'/customer', ['language' => $language])->assertRedirect();
            $this->get('/swaps/'.$swap->id.'/continue')->assertOk()->assertSee('lang="'.$language.'"', false)->assertSee($template['declaration']);
            $this->postJson('/swaps/'.$swap->id.'/steps/3', ['revision' => 2, 'consent_version_id' => $version->id, 'consent_name' => 'Fictional Rider', 'acknowledged' => 1, 'signature' => $this->signature()])->assertOk();
            $this->get('/swaps/'.$swap->id.'/continue')->assertRedirect('/swaps/'.$swap->id.'/customer/done');
            $this->post('/swaps/'.$swap->id.'/customer/return', ['current_password' => 'password'])->assertSessionHasNoErrors();
            $this->patchJson('/swaps/'.$swap->id.'/draft', ['revision' => 3, 'step' => 1, 'national_id' => 'Tampered'])->assertConflict();
            $this->postJson('/swaps/'.$swap->id.'/steps/4', ['revision' => 3, 'outcome' => 'completed', 'physical_confirmation' => 1, 'signature' => $this->signature()])->assertOk();
            $this->postJson('/swaps/'.$swap->id.'/steps/5', ['revision' => 4, 'receipt_acknowledged' => 1, 'signature' => $this->signature()])->assertOk();
            $swap->refresh();
            $this->assertSame('completed', $swap->status);
            $this->assertSame($language, $swap->consent_language);
            $this->assertSame($language === 'en' ? 'passport' : 'foreign_id', $swap->identification_type);
            $this->assertSame($swap->identification_type, $swap->signatures->firstWhere('purpose', 'consent')->payload_snapshot['identification_type']);
            $this->assertSame('00000123', $swap->old_card);
            $this->assertSame('0000000000000123', $swap->national_id);
            $this->assertCount(3, $swap->signatures);
            foreach ($swap->signatures as $sig) {
                Storage::disk('archive')->assertExists($sig->path);
                Storage::disk('local')->assertMissing($sig->path);
                $this->get('/swaps/'.$swap->id.'/signatures/'.$sig->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            }
            $pdf = $this->get('/swaps/'.$swap->id.'/pdf')->assertOk();
            $this->assertStringContainsString('/FontFile2', $pdf->getContent());
            $original = $pdf->getContent();
            $swap->refresh();
            $this->assertSame(hash('sha256', $original), $swap->pdf_hash);
            app(DocumentBranding::class)->update($company, ['brand_legal_name' => 'Later Company Name']);
            $this->assertSame($original, $this->get('/swaps/'.$swap->id.'/pdf?inline=1')->assertOk()->getContent());
            $images = array_fill_keys(['consent', 'staff', 'receipt'], $this->signature());
            $branding = app(DocumentBranding::class)->historical($swap);
            $html = view('swaps.pdf', ['swap' => $swap->load('consentVersion'), 'images' => $images, 'branding' => $branding, 'fontAvailable' => true])->render();
            $this->assertStringContainsString(e($template['declaration']), $html);
            $this->assertStringContainsString(e($template['receipt']), $html);
            $this->assertStringContainsString('1000.00', $html);
            $this->assertStringContainsString(e($swap->identificationTypeLabel()), $html);
            Storage::disk('archive')->put($swap->pdf_path, Crypt::encryptString('tampered'));
            $this->get('/swaps/'.$swap->id.'/pdf')->assertStatus(500);
            Storage::disk('archive')->delete($swap->pdf_path);
            $this->get('/swaps/'.$swap->id.'/pdf')->assertStatus(410);
            $this->assertSame($swap->pdf_path, $swap->fresh()->pdf_path);
        }
    }

    public function test_copy_is_dry_by_default_retains_sources_and_refuses_destination_conflicts(): void
    {
        [$company,$user] = $this->staff();
        $swap = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'pdf_path' => 'swaps/fixture/pdf.enc', 'pdf_hash' => hash('sha256', 'PDF fixture')]);
        $cipher = Crypt::encryptString('PDF fixture');
        Storage::disk('local')->put($swap->pdf_path, $cipher);
        $this->artisan('iyawe:documents-copy', ['--from' => 'local', '--to' => 'archive'])->assertSuccessful();
        Storage::disk('archive')->assertMissing($swap->pdf_path);
        $this->artisan('iyawe:documents-copy', ['--from' => 'local', '--to' => 'archive', '--write' => true])->assertSuccessful();
        $this->assertSame($cipher, Storage::disk('archive')->get($swap->pdf_path));
        Storage::disk('local')->assertExists($swap->pdf_path);
        $this->artisan('iyawe:documents-copy', ['--from' => 'local', '--to' => 'archive', '--write' => true])->assertSuccessful();
        Storage::disk('archive')->put($swap->pdf_path, 'different');
        $this->artisan('iyawe:documents-copy', ['--from' => 'local', '--to' => 'archive', '--write' => true])->assertFailed();
        $this->assertSame('different', Storage::disk('archive')->get($swap->pdf_path));
        $this->artisan('iyawe:documents-copy', ['--from' => 'local', '--to' => 'public', '--write' => true])->assertFailed();
    }

    public function test_unsafe_document_disks_and_cloud_local_storage_are_rejected(): void
    {
        config(['iyawe.documents_disk' => 'public']);
        try {
            app(PrivateDocuments::class)->disk();
            $this->fail('Public disk accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('private', $error->getMessage());
        }
        $this->app->detectEnvironment(fn () => 'staging');
        config(['iyawe.documents_disk' => 'local']);
        $this->expectException(\RuntimeException::class);
        app(PrivateDocuments::class)->disk();
    }

    public function test_https_cookie_flags_session_regeneration_and_expired_session_denial(): void
    {
        config(['session.driver' => 'database', 'session.secure' => true, 'session.encrypt' => true]);
        $user = User::factory()->create();
        $response = $this->get('https://localhost/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $cookies = $response->headers->getCookies();
        $session = collect($cookies)->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($session->isSecure());
        $this->assertTrue($session->isHttpOnly());
        $this->assertSame('lax', $session->getSameSite());
        $old = app('session.store')->getId();
        $this->post('https://localhost/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertNotSame($old, app('session.store')->getId());
        $this->post('https://localhost/logout')->assertRedirect();
        $this->assertGuest();
        $this->getJson('https://localhost/reports')->assertUnauthorized();
    }

    public function test_csrf_is_enforced_when_the_framework_test_bypass_is_disabled(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/login', ['email' => 'fictional@example.test', 'password' => 'wrong'])->assertStatus(419);
    }

    public function test_company_branding_xss_is_escaped_and_csv_formula_is_neutralised(): void
    {
        [$company] = $this->staff();
        $this->put('/settings/branding', ['brand_legal_name' => '<script>alert(1)</script>', 'brand_report_title' => '=1+1'])->assertSessionHasNoErrors();
        $this->get('/reports')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $csv = $this->get('/reports/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->get("/records?search_by=reference&search=' OR 1=1 --")->assertOk()->assertDontSee('SQLSTATE');
    }

    public function test_staging_readiness_fails_closed_on_local_configuration_without_secrets(): void
    {
        $this->artisan('iyawe:staging-check')->expectsOutputToContain('FAIL Staging environment')->assertFailed();
    }

    public function test_all_card_reasons_and_balance_validation_preserve_string_identifiers(): void
    {
        [$company,$user] = $this->staff();
        foreach (['lost', 'damaged', 'faulty', 'other'] as $reason) {
            $swap = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'step' => 2]);
            $data = ['revision' => 0, 'old_card' => '00000001', 'new_card' => '00000002', 'old_balance' => '123', 'new_balance' => '123', 'reason' => $reason, 'reason_other' => $reason === 'other' ? 'Fictional reason' : null];
            $this->postJson('/swaps/'.$swap->id.'/steps/2', array_replace($data, ['old_balance' => '-1']))->assertUnprocessable()->assertJsonValidationErrors('old_balance');
            $this->postJson('/swaps/'.$swap->id.'/steps/2', array_replace($data, ['new_balance' => '12.345']))->assertUnprocessable()->assertJsonValidationErrors('new_balance');
            $this->postJson('/swaps/'.$swap->id.'/steps/2', array_replace($data, ['new_card' => '00000001']))->assertUnprocessable()->assertJsonValidationErrors('old_card');
            $this->postJson('/swaps/'.$swap->id.'/steps/2', $data)->assertOk();
            $this->assertSame('00000001', $swap->fresh()->old_card);
            $this->assertSame($reason, $swap->fresh()->reason);
            $this->assertSame(3, $swap->fresh()->step);
        }
    }

    public function test_safe_staging_configuration_passes_without_claiming_live_resource_probes(): void
    {
        $previous = config('database.default');
        $this->app->detectEnvironment(fn () => 'staging');
        config(['app.debug' => false, 'app.url' => 'https://staging.example.test', 'database.default' => 'mysql', 'session.driver' => 'database', 'session.secure' => true, 'session.http_only' => true, 'session.encrypt' => true, 'session.same_site' => 'lax', 'cache.default' => 'database', 'queue.default' => 'sync', 'iyawe.documents_disk' => 's3', 'filesystems.disks.s3.driver' => 's3', 'filesystems.disks.s3.bucket' => 'fictional-private-bucket', 'filesystems.disks.s3.url' => null, 'iyawe.password_reset_enabled' => true, 'logging.default' => 'stderr', 'logging.channels.stderr.level' => 'warning']);
        try {
            $this->artisan('iyawe:staging-check')->expectsOutputToContain('PASS Private persistent document disk')->assertSuccessful();
        } finally {
            config(['database.default' => $previous]);
        }
    }

    public function test_database_session_handler_rejects_expired_payloads(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 2]);
        $handler = app('session')->driver('database')->getHandler();
        DB::table('sessions')->insert([
            ['id' => 'fictional-current-session', 'payload' => base64_encode('fictional-current-payload'), 'last_activity' => now()->timestamp],
            ['id' => 'fictional-expired-session', 'payload' => base64_encode('fictional-expired-payload'), 'last_activity' => now()->subMinutes(3)->timestamp],
        ]);
        $this->assertSame('fictional-current-payload', $handler->read('fictional-current-session'));
        $this->assertSame('', $handler->read('fictional-expired-session'));
    }
}
