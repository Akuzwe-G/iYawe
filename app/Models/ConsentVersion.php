<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'active' => 'boolean'];
    }
}
