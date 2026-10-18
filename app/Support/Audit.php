<?php

namespace App\Support;

use App\Models\ActivityLog;

class Audit
{
    public static function record(string $action, ?int $company = null, ?int $subject = null): void
    {
        ActivityLog::create(['user_id' => auth()->id(), 'company_id' => $company, 'action' => $action, 'subject_id' => $subject]);
    }
}
