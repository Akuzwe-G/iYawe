<?php

namespace App\Support;

use App\Models\CardSwap;
use App\Models\ConsentVersion;
use App\Models\SwapEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SwapWorkflow
{
    public function event(CardSwap $swap, string $type, array $metadata = []): void
    {
        SwapEvent::create(['card_swap_id' => $swap->id, 'company_id' => $swap->company_id, 'actor_id' => auth()->id(), 'type' => $type, 'metadata' => $metadata]);
    }

    public function advance(CardSwap $record, int $step, array $data): CardSwap
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($record, $step, $data, &$paths) {
                $swap = CardSwap::where('company_id', $record->company_id)->lockForUpdate()->findOrFail($record->id);
                abort_unless((int) $data['revision'] === $swap->revision, 409, __('This record changed. Reload before continuing.'));
                abort_if(in_array($swap->status, ['completed', 'processing_unsuccessful']), 409);
                abort_unless($step === $swap->step, 409);
                if ($step === 1) {
                    foreach (['swap_date', 'commuter_name', 'phone', 'email', 'identification_type', 'national_id', 'location_id'] as $key) {
                        $swap->$key = $data[$key] ?? null;
                    }
                    $swap->national_id_hash = CardSwap::fingerprint($data['national_id']);
                    $swap->name_hash = CardSwap::fingerprint($data['commuter_name']);
                    $swap->step = 2;
                    $this->event($swap, 'draft.updated');
                } elseif ($step === 2) {
                    foreach (['old_card', 'new_card', 'old_balance', 'new_balance', 'reason', 'reason_other'] as $key) {
                        $swap->$key = $data[$key] ?? null;
                    }
                    if (CardSwap::fingerprint($data['old_card']) === CardSwap::fingerprint($data['new_card'])) {
                        throw ValidationException::withMessages(['new_card' => __('Old and new cards must differ.')]);
                    }
                    $swap->old_card_hash = CardSwap::fingerprint($data['old_card']);
                    $swap->new_card_hash = CardSwap::fingerprint($data['new_card']);
                    $swap->step = 3;
                    $this->event($swap, 'draft.updated');
                } elseif ($step === 3) {
                    $identifiers = Validator::make($swap->only(['identification_type', 'national_id', 'old_card', 'new_card']), [
                        'identification_type' => CommuterIdentifiers::typeRules(),
                        'national_id' => CommuterIdentifiers::numberRules($swap->identification_type),
                        'old_card' => [...CommuterIdentifiers::cardRules(), 'different:new_card'],
                        'new_card' => [...CommuterIdentifiers::cardRules(), 'different:old_card'],
                    ]);
                    if ($identifiers->fails()) {
                        throw ValidationException::withMessages(['identification_type' => __('Review commuter and card details before signing. Use Back to update this unsigned draft.')]);
                    }
                    if (! $swap->location || ! $swap->location->active || $swap->location->company_id !== $swap->company_id) {
                        throw ValidationException::withMessages(['location_id' => __('Choose an active location before signing. Use Back to update this unsigned draft.')]);
                    }
                    $consent = ConsentVersion::where('company_id', $swap->company_id)->where('active', true)->findOrFail($data['consent_version_id']);
                    if (mb_strtolower(trim($data['consent_name'])) !== mb_strtolower(trim($swap->commuter_name))) {
                        throw ValidationException::withMessages(['consent_name' => __('The signing name must match the commuter name.')]);
                    }
                    $swap->consent_version_id = $consent->id;
                    $swap->consent_language = $consent->language;
                    $swap->consent_name = $data['consent_name'];
                    $swap->company_name_snapshot = $swap->company->name;
                    $swap->consent_signed_at = now();
                    $swap->status = 'consent_signed';
                    $swap->step = 4;
                    $this->signature($swap, 'consent', $data['signature'], $paths);
                    $this->event($swap, 'consent.signed', ['version' => $consent->version, 'language' => $consent->language]);
                } elseif ($step === 4) {
                    abort_unless($swap->consent_signed_at && $swap->signatures()->where('purpose', 'consent')->exists() && ! $swap->physical_completed_at, 409);
                    $swap->verified_by = auth()->id();
                    $swap->staff_name_snapshot = auth()->user()->name;
                    $swap->verified_at = now();
                    $swap->remarks = $data['remarks'] ?? null;
                    $swap->status = $data['outcome'] === 'unsuccessful' ? 'processing_unsuccessful' : 'pending_processing';
                    if ($data['outcome'] === 'completed') {
                        $swap->physical_completed_at = now();
                        $swap->step = 5;
                    }
                    $this->signature($swap, 'staff', $data['signature'], $paths);
                    $this->event($swap, 'staff.verified', ['outcome' => $data['outcome']]);
                    $this->event($swap, $data['outcome'] === 'completed' ? 'processing.physically_completed' : ($data['outcome'] === 'pending' ? 'processing.pending' : 'processing.unsuccessful'));
                } else {
                    abort_unless($step === 5 && $swap->physical_completed_at && $swap->verified_at && $swap->signatures()->where('purpose', 'staff')->exists(), 409);
                    $swap->branding_snapshot = app(DocumentBranding::class)->finalise($swap);
                    $swap->completed_at = now();
                    $swap->status = 'completed';
                    $swap->step = 5;
                    $this->signature($swap, 'receipt', $data['signature'], $paths);
                    $this->event($swap, 'receipt.confirmed');
                    $this->event($swap, 'acknowledgement.completed');
                }
                $swap->revision++;
                $swap->save();

                return $swap;
            });
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                app(PrivateDocuments::class)->disk()->delete($path);
            }
            throw $exception;
        }
    }

    private function signature(CardSwap $swap, string $purpose, string $uri, array &$paths): void
    {
        if (! str_starts_with($uri, 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['signature' => __('Please provide a valid signature.')]);
        }
        $png = base64_decode(substr($uri, 22), true);
        $info = $png ? @getimagesizefromstring($png) : false;
        if (! $info || $info[2] !== IMAGETYPE_PNG || strlen($png) > 524288 || $info[0] > 2048 || $info[1] > 2048 || $info[0] < 100 || $info[1] < 60) {
            throw ValidationException::withMessages(['signature' => __('Please provide a valid signature.')]);
        }
        $image = @imagecreatefromstring($png);
        $ink = 0;
        $minX = $info[0];
        $maxX = 0;
        $minY = $info[1];
        $maxY = 0;
        $stride = max(1, (int) floor(max($info[0], $info[1]) / 500));
        if ($image) {
            for ($y = 0; $y < $info[1]; $y += $stride) {
                for ($x = 0; $x < $info[0]; $x += $stride) {
                    $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if ($color['alpha'] < 100 && min($color['red'], $color['green'], $color['blue']) < 200) {
                        $ink++;
                        $minX = min($minX, $x);
                        $maxX = max($maxX, $x);
                        $minY = min($minY, $y);
                        $maxY = max($maxY, $y);
                    }
                }
            }
            imagedestroy($image);
        }
        if ($ink < 20 || $maxX - $minX < 12 || $maxY - $minY < 6) {
            throw ValidationException::withMessages(['signature' => __('Please sign clearly before continuing.')]);
        }
        $path = 'swaps/'.$swap->company_id.'/'.$swap->id.'/'.Str::uuid().'.enc';
        if (! app(PrivateDocuments::class)->disk()->put($path, Crypt::encryptString($png))) {
            throw new \RuntimeException('Unable to securely store signature.');
        }
        $paths[] = $path;
        $snapshot = $swap->attributesToArray() + ['location_name_snapshot' => $swap->location?->name, 'signature_purpose' => $purpose];
        $swap->signatures()->create(['company_id' => $swap->company_id, 'captured_by' => auth()->id(), 'purpose' => $purpose, 'revision' => $swap->revision + 1, 'path' => $path, 'sha256' => hash('sha256', $png), 'payload_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'payload_snapshot' => $snapshot, 'signed_at' => now()]);
    }
}
