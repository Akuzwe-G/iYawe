<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\ConsentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsentPublisher
{
    public function publish(Company $company, User $approver, array $data, bool $replace = false, string $source = 'company-ui'): ConsentVersion
    {
        abort_unless($approver->can('administer', [$company, 'settings.manage']), 403);

        return DB::transaction(function () use ($company, $approver, $data, $replace, $source) {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $versions = ConsentVersion::where('company_id', $company->id)->where('language', $data['language']);
            $same = (clone $versions)->where('version', $data['version'])->first();
            if ($same) {
                if ($same->active && $same->declaration === $data['declaration'] && $same->receipt === $data['receipt']) {
                    return $same;
                }
                throw ValidationException::withMessages(['version' => __('Use a new version number. Signed versions are immutable.')]);
            }
            if ((clone $versions)->where('active', true)->exists() && ! $replace) {
                throw ValidationException::withMessages(['replace_current' => __('Explicitly authorise replacement of the current company declaration.')]);
            }
            $template = config('consent.'.$data['language']);
            $isTemplate = $template && $data['declaration'] === $template['declaration'] && $data['receipt'] === $template['receipt'];
            $evidence = ['source' => $source, 'replacement_authorised' => $replace, 'declaration_sha256' => hash('sha256', $data['declaration']), 'receipt_sha256' => hash('sha256', $data['receipt'])];
            if ($isTemplate && isset($template['approval'])) {
                $evidence['template_approval'] = $template['approval'];
            }
            (clone $versions)->where('active', true)->update(['active' => false]);
            $version = ConsentVersion::create(['company_id' => $company->id, 'language' => $data['language'], 'version' => $data['version'], 'declaration' => $data['declaration'], 'receipt' => $data['receipt'], 'approved_by' => $approver->id, 'approved_at' => now(), 'publication_evidence' => $evidence]);
            ActivityLog::create(['user_id' => $approver->id, 'company_id' => $company->id, 'action' => 'consent.version.published', 'subject_id' => $version->id]);

            return $version;
        });
    }
}
