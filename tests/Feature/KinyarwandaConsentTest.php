<?php

namespace Tests\Feature;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\ConsentVersion;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KinyarwandaConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplied_kinyarwanda_consent_can_be_published_and_used_with_english_interface(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $user = User::factory()->create(['locale' => 'en']);
        $company->users()->attach($user->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);
        $this->actingAs($user);
        foreach (['en', 'rw'] as $language) {
            $template = config('consent.'.$language);
            $this->post('/consents', ['language' => $language, 'version' => $template['version'], 'declaration' => $template['declaration'], 'receipt' => $template['receipt'], 'approved' => 1])->assertSessionHasNoErrors();
        }
        $version = ConsentVersion::where('company_id', $company->id)->where('language', 'rw')->firstOrFail();
        $this->assertSame('tapgo-rw-1.0', $version->version);
        $this->assertSame(config('consent.rw.declaration'), $version->declaration);
        $this->assertSame(6, substr_count($version->declaration, "\n- "));
        $this->assertSame('Ndemeza ko nahawe kandi nakiriye ikarita nshya isimbura iya mbere, kandi ko igikorwa cyo guhindurirwa ikarita cyarangiye.', $version->receipt);
        $this->assertDatabaseHas('consent_versions', ['language' => 'en', 'active' => true]);
        $swap = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'step' => 3, 'commuter_name' => 'Sample Commuter']);
        $this->get('/swaps/'.$swap->id.'/continue')->assertOk()->assertSee('lang="en"', false)->assertSee('IFISHI YO KWEMERA NO GUTANGA UBURENGANZIRA')->assertSee($version->declaration);
        $this->post('/consents', ['language' => 'rw', 'version' => 'tapgo-rw-1.0', 'declaration' => 'Changed wording', 'receipt' => 'Changed receipt', 'approved' => 1])->assertSessionHasErrors('version');
        $this->assertSame(config('consent.rw.declaration'), $version->fresh()->declaration);

        $swap->forceFill(['status' => 'completed', 'consent_version_id' => $version->id, 'consent_language' => 'rw', 'consent_name' => 'Sample Commuter', 'company_name_snapshot' => $company->name, 'staff_name_snapshot' => $user->name, 'verified_by' => $user->id, 'verified_at' => now(), 'physical_completed_at' => now(), 'completed_at' => now(), 'consent_signed_at' => now(), 'old_card' => 'OLD-RW', 'new_card' => 'NEW-RW', 'old_balance' => '100', 'new_balance' => '100', 'reason' => 'damaged', 'phone' => '+250788123456', 'national_id' => 'TEST-RW'])->save();
        $image = imagecreatetruecolor(320, 180);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 20, 120, 200, 40, imagecolorallocate($image, 23, 43, 77));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        foreach (['consent', 'staff', 'receipt'] as $purpose) {
            $path = 'test-rw/'.$purpose.'.enc';
            Storage::disk('local')->put($path, Crypt::encryptString($png));
            $swap->signatures()->create(['company_id' => $company->id, 'captured_by' => $user->id, 'purpose' => $purpose, 'revision' => 1, 'path' => $path, 'sha256' => hash('sha256', $png), 'payload_hash' => hash('sha256', 'fixture'), 'payload_snapshot' => [], 'signed_at' => now()]);
        }
        $pdf = $this->get('/swaps/'.$swap->id.'/pdf');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('Montserrat',$pdf->getContent());
        $this->assertSame('en',app()->getLocale());
    }
}
