<?php

namespace Tests\Feature;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\ConsentVersion;
use App\Models\Location;
use App\Models\User;
use App\Support\CommuterIdentifiers;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommuterIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): array
    {
        $this->withoutVite();
        Storage::fake('local');
        $company = Company::factory()->create();
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

    private function record(int $step = 1): CardSwap
    {
        [$company,$user] = $this->staff();

        return CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'step' => $step]);
    }

    private function commuter(string $type, mixed $number): array
    {
        return ['location_id' => Location::where('company_id', auth()->user()->companies()->firstOrFail()->id)->value('id'), 'revision' => 0, 'swap_date' => today()->format('Y-m-d'), 'commuter_name' => 'Fictional Commuter', 'phone' => '+250788123456', 'identification_type' => $type, 'national_id' => $number];
    }

    private function cards(mixed $old, mixed $new = '00CD5678'): array
    {
        return ['revision' => 0, 'old_card' => $old, 'new_card' => $new, 'old_balance' => '100', 'new_balance' => '100', 'reason' => 'lost'];
    }

    public function test_valid_card_numbers_are_uppercase_strings_with_leading_zeroes(): void
    {
        foreach (['00000000', '00ab12cd', 'ABCDEF01', '12345678'] as $value) {
            $record = $this->record(2);
            $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards($value))->assertOk();
            $this->assertSame(strtoupper($value), $record->fresh()->old_card);
            $this->assertSame('00CD5678', $record->fresh()->new_card);
            $this->assertNotSame(strtoupper($value), $record->fresh()->getRawOriginal('old_card'));
        }
    }

    public function test_bad_card_formats_and_case_insensitive_duplicates_are_rejected(): void
    {
        $record = $this->record(2);
        foreach (['1234567', '123456789', '00GH1234', '00AB 123', '00AB-123', '０１２３４５６７', 12345678, ['00AB1234']] as $value) {
            $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards($value))->assertUnprocessable()->assertJsonValidationErrors('old_card');
            $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards('00AB1234', $value))->assertUnprocessable()->assertJsonValidationErrors('new_card');
        }
        $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards('00ab1234', '00AB1234'))->assertUnprocessable()->assertJsonValidationErrors(['old_card', 'new_card']);
        $this->assertSame(0, $record->fresh()->revision);
    }

    public function test_rwandan_id_accepts_only_sixteen_ascii_digits_without_other_identity_assumptions(): void
    {
        $record = $this->record();
        foreach (['123456789012345', '12345678901234567', '123456789012345X', '１２３４５６７８９０１２３４５６', 1234567890123456] as $value) {
            $this->postJson('/swaps/'.$record->id.'/steps/1', $this->commuter('rwandan_national_id', $value))->assertUnprocessable()->assertJsonValidationErrors('national_id');
        }
        $this->postJson('/swaps/'.$record->id.'/steps/1', $this->commuter('rwandan_national_id', '0000000000000000'))->assertOk();
        $this->assertSame('0000000000000000', $record->fresh()->national_id);
        $this->assertSame('rwandan_national_id', $record->fresh()->identification_type);
    }

    public function test_passport_and_foreign_identification_formats_and_boundaries(): void
    {
        $cases = [
            ['passport', 'Ab-01', true], ['passport', str_repeat('A', 20), true], ['passport', 'A--01', true], ['passport', 'AB12', false], ['passport', str_repeat('A', 21), false], ['passport', 'AB 123', false], ['passport', '-AB123', false], ['passport', 'AB123-', false], ['passport', 'AB/123', false],
            ['foreign_id', 'Ab 01', true], ['foreign_id', 'AB-12 34', true], ['foreign_id', str_repeat('B', 30), true], ['foreign_id', 'AB12', false], ['foreign_id', str_repeat('B', 31), false], ['foreign_id', 'AB_123', false], ['foreign_id', 'AB/123', false], ['foreign_id', 'AB123-', false], ['foreign_id', '-AB123', false],
        ];
        foreach ($cases as [$type,$number,$valid]) {
            $record = $this->record();
            $response = $this->postJson('/swaps/'.$record->id.'/steps/1', $this->commuter($type, $number));
            if ($valid) {
                $response->assertOk();
                $this->assertSame($number, $record->fresh()->national_id);
            } else {
                $response->assertUnprocessable()->assertJsonValidationErrors('national_id');
            }
        }
    }

    public function test_type_is_required_and_errors_are_friendly_in_both_languages(): void
    {
        $record = $this->record();
        $data = $this->commuter('passport', 'AB123');
        unset($data['identification_type']);
        $this->postJson('/swaps/'.$record->id.'/steps/1', $data)->assertUnprocessable()->assertJsonValidationErrors('identification_type');
        $this->postJson('/swaps/'.$record->id.'/steps/1', $this->commuter('invented', 'AB123'))->assertUnprocessable()->assertJsonValidationErrors('identification_type');
        $user = auth()->user();
        $user->forceFill(['locale' => 'rw'])->save();
        $this->postJson('/swaps/'.$record->id.'/steps/1', $this->commuter('rwandan_national_id', '123'))->assertUnprocessable()->assertJsonPath('errors.national_id.0', __('Enter exactly 16 digits for the Rwandan National ID.', [], 'rw'));
        $this->assertSame(0, $record->fresh()->revision);
    }

    public function test_partial_drafts_remain_recoverable_and_normalise_cards_without_advancing(): void
    {
        $record = $this->record();
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 0, 'step' => 1, 'identification_type' => 'passport', 'national_id' => 'AB-'])->assertOk();
        $this->assertSame('AB-', $record->fresh()->national_id);
        $this->assertSame(1, $record->fresh()->step);
        $this->get('/swaps/'.$record->id.'/continue')->assertOk()->assertSee('value="AB-"', false)->assertSee('value="passport" selected', false);
        $record->forceFill(['step' => 2])->save();
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 1, 'step' => 2, 'old_card' => '00af', 'new_card' => '00be'])->assertOk();
        $this->assertSame('00AF', $record->fresh()->old_card);
        $this->assertSame(2, $record->fresh()->step);
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 2, 'step' => 2, 'old_card' => '00ZZ'])->assertUnprocessable()->assertJsonValidationErrors('old_card');
    }

    public function test_typed_search_preserves_legacy_search_and_csv_does_not_add_identification_numbers(): void
    {
        [$company,$user] = $this->staff();
        $records = [];
        foreach ([null, 'passport', 'foreign_id'] as $type) {
            $records[] = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'identification_type' => $type, 'national_id' => 'AB-123', 'national_id_hash' => CardSwap::fingerprint('AB-123')]);
        }
        $this->post('/records/search', ['search_by' => 'identification_number', 'identification_type' => 'passport', 'search' => 'ab-123'])->assertRedirect('/records?searching=1');
        $this->get('/records?searching=1')->assertSee($records[1]->reference)->assertDontSee($records[0]->reference)->assertDontSee($records[2]->reference);
        $this->post('/records/search', ['search_by' => 'national_id', 'identification_type' => 'legacy', 'search' => 'AB-123'])->assertRedirect();
        $this->get('/records?searching=1')->assertSee($records[0]->reference)->assertDontSee($records[1]->reference);
        $this->get('/reports?identification_type=passport')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);
        $csv = $this->get('/reports/export?identification_type=passport')->assertOk()->streamedContent();
        $this->assertStringContainsString('Passport', $csv);
        $this->assertStringNotContainsString('AB-123', $csv);
        $this->get('/swaps/'.$records[1]->id)->assertOk()->assertSee('Passport')->assertSee('AB-123');
        $this->get('/swaps/'.$records[0]->id)->assertOk()->assertSee('Legacy identification (type unspecified)');
    }

    public function test_legacy_completed_pdf_is_unchanged_and_unsigned_legacy_details_require_review(): void
    {
        [$company,$user] = $this->staff();
        $bytes = '%PDF-legacy-immutable';
        $record = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'balance_validation_version' => null, 'status' => 'completed', 'national_id' => 'LEGACY-ID', 'old_card' => 'OLD-12345', 'pdf_path' => 'legacy/example.enc', 'pdf_hash' => hash('sha256', $bytes)]);
        Storage::disk('local')->put($record->pdf_path, Crypt::encryptString($bytes));
        $this->assertSame($bytes, $this->get('/swaps/'.$record->id.'/pdf')->assertOk()->getContent());
        $this->assertNull($record->fresh()->identification_type);
        $this->assertSame('OLD-12345', $record->fresh()->old_card);
        $version = ConsentVersion::create(['company_id' => $company->id, 'language' => 'en', 'version' => 'test-legacy', 'declaration' => config('consent.en.declaration'), 'receipt' => config('consent.en.receipt'), 'approved_by' => $user->id, 'approved_at' => now()]);
        $unsigned = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'balance_validation_version' => null, 'step' => 3, 'commuter_name' => 'Fictional Commuter', 'national_id' => 'LEGACY-ID', 'old_card' => 'OLD-12345', 'new_card' => 'NEW-12345']);
        $this->postJson('/swaps/'.$unsigned->id.'/steps/3', ['revision' => 0, 'consent_version_id' => $version->id, 'consent_name' => 'Fictional Commuter', 'acknowledged' => 1, 'signature' => 'data:image/png;base64,'])->assertUnprocessable()->assertJsonValidationErrors('identification_type');
        $this->assertNull($unsigned->fresh()->consent_signed_at);
        $this->assertSame(0, $unsigned->signatures()->count());
    }

    public function test_frontend_forms_publish_the_same_rules_and_retain_safe_input_values(): void
    {
        $record = $this->record();
        $response = $this->get('/swaps/'.$record->id.'/continue')->assertOk()->assertSee('name="identification_type"', false)->assertSee('inputmode="numeric"', false)->assertSee('pattern="[0-9]{16}"', false);
        if (getenv('IYAWE_CAPTURE_IDENTIFIERS')) {
            File::ensureDirectoryExists(storage_path('app/identifier-fixtures'));
            File::put(storage_path('app/identifier-fixtures/identification.html'), $response->getContent());
            File::put(storage_path('app/identifier-fixtures/specifications.json'), json_encode(CommuterIdentifiers::specifications(), JSON_THROW_ON_ERROR));
        }
        $record->forceFill(['step' => 2])->save();
        $cards = $this->get('/swaps/'.$record->id.'/continue')->assertOk()->assertSee('pattern="[0-9A-Fa-f]{8}"', false)->assertSee('minlength="8"', false)->assertSee('data-tapgo-card', false);
        if (getenv('IYAWE_CAPTURE_IDENTIFIERS')) {
            File::put(storage_path('app/identifier-fixtures/cards.html'), $cards->getContent());
        }
        if (getenv('IYAWE_CAPTURE_IDENTIFIERS')) {
            auth()->user()->forceFill(['locale' => 'rw'])->save();
            File::put(storage_path('app/identifier-fixtures/cards-rw.html'), $this->get('/swaps/'.$record->id.'/continue')->assertOk()->getContent());
            $record->forceFill(['step' => 1])->save();
            File::put(storage_path('app/identifier-fixtures/identification-rw.html'), $this->get('/swaps/'.$record->id.'/continue')->assertOk()->getContent());
        }

    }

    public function test_already_signed_legacy_records_can_finish_without_changing_consent_or_identifiers(): void
    {
        [$company,$user] = $this->staff();
        $version = ConsentVersion::create(['company_id' => $company->id, 'language' => 'en', 'version' => 'historical', 'declaration' => config('consent.en.declaration'), 'receipt' => config('consent.en.receipt'), 'approved_by' => $user->id, 'approved_at' => now()]);
        $record = CardSwap::factory()->create(['company_id' => $company->id, 'created_by' => $user->id, 'balance_validation_version' => null, 'status' => 'pending_processing', 'step' => 5, 'consent_version_id' => $version->id, 'consent_language' => 'en', 'commuter_name' => 'Legacy Rider', 'consent_name' => 'Legacy Rider', 'national_id' => 'LEGACY-ID', 'old_card' => 'old-legacy', 'new_card' => 'new-legacy', 'consent_signed_at' => now(), 'verified_at' => now(), 'physical_completed_at' => now(), 'staff_name_snapshot' => $user->name]);
        foreach (['consent', 'staff'] as $purpose) {
            $record->signatures()->create(['company_id' => $company->id, 'captured_by' => $user->id, 'purpose' => $purpose, 'revision' => 1, 'path' => 'legacy/'.$purpose.'.enc', 'sha256' => hash('sha256', 'historical'), 'payload_hash' => hash('sha256', 'historical'), 'payload_snapshot' => ['national_id' => 'LEGACY-ID'], 'signed_at' => now()]);
        }
        $consent = $record->signatures()->where('purpose', 'consent')->firstOrFail();
        $original = $consent->getRawOriginal('payload_snapshot');
        $image = imagecreatetruecolor(320, 180);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagesetthickness($image, 3);
        imageline($image, 20, 120, 260, 40, imagecolorallocate($image, 23, 43, 77));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        $this->postJson('/swaps/'.$record->id.'/steps/5', ['revision' => 0, 'receipt_acknowledged' => 1, 'signature' => 'data:image/png;base64,'.base64_encode($png)])->assertOk();
        $this->assertSame('completed', $record->fresh()->status);
        $this->assertNull($record->fresh()->identification_type);
        $this->assertSame('LEGACY-ID', $record->fresh()->national_id);
        $this->assertSame('old-legacy', $record->fresh()->old_card);
        $this->assertSame($original, $consent->fresh()->getRawOriginal('payload_snapshot'));
    }

    public function test_location_is_required_company_scoped_and_active_while_partial_drafts_remain_saveable(): void
    {
        $record = $this->record();
        $data = $this->commuter('passport', 'AB123');
        unset($data['location_id']);
        $this->postJson('/swaps/'.$record->id.'/steps/1', $data)->assertUnprocessable()->assertJsonPath('errors.location_id.0', 'Choose a location to continue.');
        $this->postJson('/swaps/'.$record->id.'/steps/1', $data + ['location_id' => null])->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $inactive = Location::create(['company_id' => $record->company_id, 'name' => 'Inactive location', 'active' => false]);
        $foreign = Location::create(['company_id' => Company::factory()->create()->id, 'name' => 'Other company location', 'active' => true]);
        foreach ([$inactive->id, $foreign->id, 'invalid', 999999] as $location) {
            $this->postJson('/swaps/'.$record->id.'/steps/1', $data + ['location_id' => $location])->assertUnprocessable()->assertJsonValidationErrors('location_id');
        }
        auth()->user()->forceFill(['locale' => 'rw'])->save();
        $this->postJson('/swaps/'.$record->id.'/steps/1', $data)->assertUnprocessable()->assertJsonPath('errors.location_id.0', __('Choose a location to continue.', [], 'rw'));
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 0, 'step' => 1, 'commuter_name' => 'Unsaved commuter', 'location_id' => null])->assertOk();
        $this->assertNull($record->fresh()->location_id);
        $this->assertSame(1, $record->fresh()->step);
        Location::where('company_id', $record->company_id)->update(['active' => false]);
        auth()->user()->forceFill(['locale' => 'en'])->save();
        $this->get('/swaps/'.$record->id.'/continue')->assertOk()
            ->assertSee('name="location_id" required', false)
            ->assertSee('No active locations are available. Ask your company administrator to add one before continuing.');
        $active = Location::create(['company_id' => $record->company_id, 'name' => 'Active service location', 'active' => true]);
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 1, 'step' => 1, 'location_id' => $active->id])->assertOk();
        $this->get('/swaps/'.$record->id.'/continue')->assertOk()->assertSee('value="'.$active->id.'" selected', false);
        $this->postJson('/swaps/'.$record->id.'/steps/1', array_replace($data, ['revision' => 2, 'location_id' => $active->id]))->assertOk();
        $this->assertSame($active->id, $record->fresh()->location_id);
    }

    public function test_unsigned_draft_cannot_bypass_required_location_at_consent(): void
    {
        $record = $this->record(3);
        $record->forceFill(['old_balance' => '100', 'new_balance' => '100', 'commuter_name' => 'Test commuter', 'identification_type' => 'passport', 'national_id' => 'AB123', 'old_card' => '00000001', 'new_card' => '00000002', 'location_id' => null])->save();
        $version = ConsentVersion::create(['company_id' => $record->company_id, 'language' => 'en', 'version' => 'location-test', 'declaration' => 'Test', 'receipt' => 'Test', 'active' => true, 'approved_by' => $record->created_by, 'approved_at' => now()]);
        $this->postJson('/swaps/'.$record->id.'/steps/3', ['revision' => 0, 'consent_version_id' => $version->id, 'consent_name' => $record->commuter_name, 'acknowledged' => 1, 'signature' => 'data:image/png;base64,'])->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->assertNull($record->fresh()->consent_signed_at);
        $this->assertSame(0, $record->fresh()->signatures()->count());
    }
}
