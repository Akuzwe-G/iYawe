<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwapSignature extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path', 'payload_snapshot'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'payload_snapshot' => 'encrypted:array'];
    }
}
