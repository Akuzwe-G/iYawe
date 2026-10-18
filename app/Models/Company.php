<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'email', 'phone', 'status'];

    protected function casts(): array
    {
        return ['document_branding' => 'encrypted:array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('active', 'pending', 'display_name')->withTimestamps();
    }
}
