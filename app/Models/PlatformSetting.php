<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function preferences(): array
    {
        return array_merge(['default_locale' => 'en', 'support_email' => null], static::pluck('value', 'key')->all());
    }
}
