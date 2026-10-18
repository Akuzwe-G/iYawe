<?php

namespace App\Models;

use App\Support\CommuterIdentifiers;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardSwap extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    public const STATUSES = ['draft', 'consent_signed', 'pending_processing', 'processing_unsuccessful', 'completed'];

    protected $guarded = ['id'];

    protected $hidden = ['national_id_hash', 'old_card_hash', 'new_card_hash', 'name_hash', 'pdf_path'];

    protected function casts(): array
    {
        return ['branding_snapshot' => 'encrypted:array', 'commuter_name' => 'encrypted', 'phone' => 'encrypted', 'email' => 'encrypted', 'national_id' => 'encrypted', 'old_card' => 'encrypted', 'new_card' => 'encrypted', 'reason_other' => 'encrypted', 'remarks' => 'encrypted', 'consent_name' => 'encrypted', 'company_name_snapshot' => 'encrypted', 'staff_name_snapshot' => 'encrypted', 'old_balance' => 'decimal:2', 'new_balance' => 'decimal:2', 'swap_date' => 'date', 'consent_signed_at' => 'datetime', 'verified_at' => 'datetime', 'physical_completed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function consentVersion(): BelongsTo
    {
        return $this->belongsTo(ConsentVersion::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(SwapSignature::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SwapEvent::class);
    }

    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(preg_replace('/\s+/u', '', trim($value))), config('app.key'));
    }

    public function identificationTypeLabel(): string
    {
        return CommuterIdentifiers::label($this->identification_type);
    }

    public function maskedCard(?string $value): string
    {
        return $value ? str_repeat('•', max(4, mb_strlen($value) - 4)).mb_substr($value, -4) : '—';
    }

    protected function swapDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => $value ? CarbonImmutable::parse($value)->toDateString() : null);
    }
}
