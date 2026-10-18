<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Support\ConsentPublisher;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class PublishConsentVersion extends Command
{
    protected $signature = 'iyawe:consent-publish {company : Company slug} {language : en or rw} {--approved-by= : Authorised approving member email} {--replace-current : Explicitly replace the current company language version}';

    protected $description = 'Publish the supplied approved template for one company, preserving existing declarations unless replacement is explicit';

    public function handle(): int
    {
        $language = $this->argument('language');
        $template = in_array($language, ['en', 'rw']) ? config('consent.'.$language) : null;
        $company = Company::where('slug', $this->argument('company'))->where('status', 'active')->first();
        $approver = User::where('email', $this->option('approved-by'))->where('active', true)->first();
        if (! $company || ! $approver || ! $template) {
            $this->error('An active company, authorised approving member and language template are required.');

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
            app(ConsentPublisher::class)->publish($company, $approver, ['language' => $language, 'version' => $template['version'], 'declaration' => $template['declaration'], 'receipt' => $template['receipt']], (bool) $this->option('replace-current'), 'company-cli');
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        } finally {
            setPermissionsTeamId($previous);
            $approver->unsetRelation('roles')->unsetRelation('permissions');
        }
        $this->info('Approved template published for this company; an identical active version is left unchanged.');

        return self::SUCCESS;
    }
}
