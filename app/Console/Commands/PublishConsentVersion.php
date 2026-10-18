<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\ConsentVersion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishConsentVersion extends Command
{
    protected $signature = 'iyawe:consent-publish {company : Company slug} {language : en or rw} {--approved-by= : Email of the approving company administrator}';

    protected $description = 'Publish an approved, immutable consent version from config/consent.php for one company';

    public function handle(): int
    {
        $language = $this->argument('language');
        $template = config('consent.'.$language);
        $company = Company::where('slug', $this->argument('company'))->where('status', 'active')->first();
        $approver = User::where('email', $this->option('approved-by'))->where('active', true)->first();
        if (! $company || ! $approver || ! in_array($language, ['en', 'rw']) || empty($template['declaration']) || empty($template['receipt'])) {
            $this->error('An active company, approving account, and complete approved language template are required.');

            return self::FAILURE;
        }
        $previous = getPermissionsTeamId();
        try {
            setPermissionsTeamId($company->id);
            $approver->unsetRelation('roles')->unsetRelation('permissions');
            if (! $approver->can('administer', [$company, 'settings.manage'])) {
                $this->error('The approving account must manage settings in this company.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($company, $language, $template, $approver) {
                Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                if (ConsentVersion::where('company_id', $company->id)->where('language', $language)->where('version', $template['version'])->exists()) {
                    return;
                }
                ConsentVersion::where('company_id', $company->id)->where('language', $language)->update(['active' => false]);
                $version = ConsentVersion::create(['company_id' => $company->id, 'language' => $language, 'version' => $template['version'], 'declaration' => $template['declaration'], 'receipt' => $template['receipt'], 'approved_by' => $approver->id, 'approved_at' => now()]);
                Audit::record('consent.version.published', $company->id, $version->id);
            });
        } finally {
            setPermissionsTeamId($previous);
            $approver->unsetRelation('roles')->unsetRelation('permissions');
        }
        $this->info('Consent version available for this company.');

        return self::SUCCESS;
    }
}
