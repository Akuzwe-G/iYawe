<?php

namespace Tests\Feature;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\User;
use App\Support\CardBalances;
use App\Support\CompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CardBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function record(array $attributes = []): CardSwap
    {
        $this->withoutVite();
        Storage::fake('local');
        $company = Company::factory()->create();
        CompanyAccess::provision($company);
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['active' => true]);
        setPermissionsTeamId($company->id);
        $user->assignRole(Role::where('company_id', $company->id)->where('name', 'Company Administrator')->firstOrFail());
        setPermissionsTeamId(null);
        $this->actingAs($user);

        return CardSwap::factory()->create($attributes + ['company_id' => $company->id, 'created_by' => $user->id, 'step' => 2]);
    }

    private function cards(mixed $old, mixed $new): array
    {
        return ['revision' => 0, 'old_card' => '00000001', 'new_card' => '00000002', 'old_balance' => $old, 'new_balance' => $new, 'reason' => 'lost'];
    }

    private function signature(): string
    {
        $image = imagecreatetruecolor(320, 180);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagesetthickness($image, 3);
        imageline($image, 20, 120, 260, 40, imagecolorallocate($image, 23, 43, 77));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function consentSignature(CardSwap $record): void
    {
        $record->signatures()->create(['company_id' => $record->company_id, 'captured_by' => $record->created_by, 'purpose' => 'consent', 'revision' => 1, 'path' => 'consent.enc', 'sha256' => hash('sha256', 'original'), 'payload_hash' => hash('sha256', 'original'), 'payload_snapshot' => ['old_balance' => $record->old_balance], 'signed_at' => now()]);
    }

    public function test_both_balances_accept_inclusive_whole_number_boundaries(): void
    {
        foreach ([100, '49999', '00100'] as $value) {
            $record = $this->record();
            $this->assertSame(1, $record->balance_validation_version);
            $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards($value, $value))->assertOk();
            $this->assertSame(number_format((int) $value, 2, '.', ''), $record->fresh()->old_balance);
            $this->assertSame($record->fresh()->old_balance, $record->fresh()->new_balance);
        }
    }

    public function test_both_balances_reject_out_of_range_fractional_and_non_numeric_values(): void
    {
        $record = $this->record(['old_balance' => '500', 'new_balance' => '500']);
        foreach ([99, 50000, 0, -1, '100.01', '100.00', '99.99', 'abc', '1e3', '1,000', '', null, ['100'], true, 100.25] as $value) {
            foreach (['old_balance', 'new_balance'] as $field) {
                $this->postJson('/swaps/'.$record->id.'/steps/2', array_replace($this->cards('100', '100'), [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->assertSame(0, $record->fresh()->revision);
        $this->assertSame('500.00', $record->fresh()->old_balance);
        $this->assertSame('500.00', $record->fresh()->new_balance);
    }

    public function test_mismatch_is_rejected_without_erasing_inputs_or_updating_the_record(): void
    {
        $record = $this->record();
        $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards('100', '49999'))->assertUnprocessable()->assertJsonPath('errors.new_balance.0', CardBalances::EQUAL_MESSAGE);
        $this->from('/swaps/'.$record->id.'/continue')->post('/swaps/'.$record->id.'/steps/2', $this->cards('100.5', '123'))
            ->assertRedirect('/swaps/'.$record->id.'/continue')->assertSessionHasInput('old_balance', '100.5')->assertSessionHasInput('new_balance', '123');
        $this->get('/swaps/'.$record->id.'/continue')->assertOk()->assertSee('value="100.5"', false)->assertSee('value="123"', false);
        $this->assertSame(2, $record->fresh()->step);
        $this->assertNull($record->fresh()->old_balance);
    }

    public function test_drafts_can_save_separate_whole_balances_but_cannot_advance_until_equal(): void
    {
        $record = $this->record();
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 0, 'step' => 2, 'old_balance' => '100', 'new_balance' => '200'])->assertOk();
        $this->assertSame('100.00', $record->fresh()->old_balance);
        $this->get('/swaps/'.$record->id.'/continue')->assertOk()->assertSee('value="100"', false)->assertDontSee('value="100.00"', false);
        $this->patchJson('/swaps/'.$record->id.'/draft', ['revision' => 1, 'step' => 2, 'new_balance' => '200.5', 'balance_validation_version' => null])->assertUnprocessable()->assertJsonValidationErrors('new_balance');
        $this->postJson('/swaps/'.$record->id.'/steps/2', array_replace($this->cards('100', '200'), ['revision' => 1, 'balance_validation_version' => null]))->assertUnprocessable()->assertJsonValidationErrors('new_balance');
        $this->assertSame('200.00', $record->fresh()->new_balance);
        $this->assertSame(1, $record->fresh()->balance_validation_version);
        $this->assertSame(2, $record->fresh()->step);
    }

    public function test_physical_completion_rechecks_saved_balances_before_writing_signatures(): void
    {
        foreach ([['100', '200'], ['99', '99'], ['50000', '50000'], ['100.25', '100.25']] as [$old, $new]) {
            $record = $this->record(['step' => 4, 'status' => 'consent_signed', 'consent_signed_at' => now(), 'old_balance' => $old, 'new_balance' => $new]);
            $this->consentSignature($record);
            $original = $record->signatures()->firstOrFail()->getRawOriginal('payload_snapshot');
            $this->postJson('/swaps/'.$record->id.'/steps/4', ['revision' => 0, 'outcome' => 'completed', 'physical_confirmation' => 1, 'signature' => $this->signature()])->assertUnprocessable();
            $this->assertNull($record->fresh()->physical_completed_at);
            $this->assertSame(1, $record->signatures()->count());
            $this->assertSame($original, $record->signatures()->firstOrFail()->getRawOriginal('payload_snapshot'));
        }
        $record = $this->record(['step' => 4, 'status' => 'consent_signed', 'consent_signed_at' => now(), 'old_balance' => '49999', 'new_balance' => '49999']);
        $this->consentSignature($record);
        $this->postJson('/swaps/'.$record->id.'/steps/4', ['revision' => 0, 'outcome' => 'completed', 'physical_confirmation' => 1, 'signature' => $this->signature()])->assertOk();
        $this->assertSame(5, $record->fresh()->step);
    }

    public function test_receipt_completion_cannot_bypass_the_saved_balance_checks(): void
    {
        $record = $this->record(['step' => 5, 'status' => 'pending_processing', 'physical_completed_at' => now(), 'verified_at' => now(), 'old_balance' => '100', 'new_balance' => '101']);
        $record->signatures()->create(['company_id' => $record->company_id, 'captured_by' => $record->created_by, 'purpose' => 'staff', 'revision' => 1, 'path' => 'staff.enc', 'sha256' => hash('sha256', 'original'), 'payload_hash' => hash('sha256', 'original'), 'payload_snapshot' => [], 'signed_at' => now()]);
        $this->postJson('/swaps/'.$record->id.'/steps/5', ['revision' => 0, 'receipt_acknowledged' => 1, 'signature' => $this->signature()])->assertUnprocessable()->assertJsonValidationErrors('new_balance');
        $this->assertNull($record->fresh()->completed_at);
        $this->assertSame('pending_processing', $record->fresh()->status);
        $this->assertSame(1, $record->signatures()->count());
    }

    public function test_bilingual_balance_messages(): void
    {
        $record = $this->record();
        foreach (['en', 'rw'] as $locale) {
            auth()->user()->forceFill(['locale' => $locale])->save();
            $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards('99', '100'))->assertUnprocessable()->assertJsonPath('errors.old_balance.0', __(CardBalances::MESSAGE, [], $locale));
            $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards('100', '101'))->assertUnprocessable()->assertJsonPath('errors.new_balance.0', __(CardBalances::EQUAL_MESSAGE, [], $locale));
        }
    }

    public function test_historical_balances_and_archived_pdf_are_not_rewritten_or_revalidated(): void
    {
        $record = $this->record(['balance_validation_version' => null]);
        $this->postJson('/swaps/'.$record->id.'/steps/2', $this->cards('50.25', '75.50'))->assertOk();
        $this->assertNull($record->fresh()->balance_validation_version);
        $this->assertSame('50.25', $record->fresh()->old_balance);
        $this->assertSame('75.50', $record->fresh()->new_balance);
        $this->get('/swaps/'.$record->id.'/continue')->assertOk();
        $record->forceFill(['status' => 'completed', 'pdf_path' => 'historical.pdf.enc', 'pdf_hash' => hash('sha256', '%PDF-original')])->save();
        Storage::disk('local')->put($record->pdf_path, Crypt::encryptString('%PDF-original'));
        $this->assertSame('%PDF-original', $this->get('/swaps/'.$record->id.'/pdf')->assertOk()->getContent());
        $this->assertSame('50.25', $record->fresh()->old_balance);
        $this->assertSame('75.50', $record->fresh()->new_balance);
    }

    public function test_pre_existing_signed_record_can_finish_under_its_original_balance_policy(): void
    {
        $record = $this->record(['balance_validation_version' => null, 'step' => 4, 'status' => 'consent_signed', 'consent_signed_at' => now(), 'old_balance' => '50.25', 'new_balance' => '75.50']);
        $this->consentSignature($record);
        $original = $record->signatures()->firstOrFail()->getRawOriginal('payload_snapshot');
        $this->postJson('/swaps/'.$record->id.'/steps/4', ['revision' => 0, 'outcome' => 'completed', 'physical_confirmation' => 1, 'signature' => $this->signature()])->assertOk();
        $this->assertSame(5, $record->fresh()->step);
        $this->assertSame('50.25', $record->fresh()->old_balance);
        $this->assertSame('75.50', $record->fresh()->new_balance);
        $this->assertSame($original, $record->signatures()->where('purpose', 'consent')->firstOrFail()->getRawOriginal('payload_snapshot'));
    }

    public function test_new_records_are_versioned_without_a_database_default_that_relabels_history(): void
    {
        $record = $this->record();
        $this->assertSame(1, $record->fresh()->balance_validation_version);
        $attributes = $record->getRawOriginal();
        unset($attributes['id'], $attributes['balance_validation_version']);
        $attributes['reference'] = 'IY-LEGACY-BALANCES';
        $attributes['submission_key'] = (string) Str::uuid();
        $id = DB::table('card_swaps')->insertGetId($attributes);
        $legacy = CardSwap::findOrFail($id);
        $this->assertNull($legacy->balance_validation_version);
        $this->assertFalse($legacy->usesWholeRwfBalances());
    }
}
