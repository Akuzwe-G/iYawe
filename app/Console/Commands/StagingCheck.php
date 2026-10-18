<?php

namespace App\Console\Commands;

use App\Support\PrivateDocuments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Throwable;

class StagingCheck extends Command
{
    protected $signature = 'iyawe:staging-check {--probe-database : Check connection and required migrated tables} {--probe-storage : Write/read/delete a synthetic encrypted canary}';

    protected $description = 'Fail closed on unsafe staging configuration without printing secrets';

    public function handle(): int
    {
        $disk = config('iyawe.documents_disk');
        $settings = config('filesystems.disks.'.$disk, []);
        try {
            $validKey = strlen(app('encrypter')->getKey()) === 32;
        } catch (Throwable) {
            $validKey = false;
        }
        $checks = [
            'Staging environment' => app()->environment('staging'),
            'Debug disabled' => ! config('app.debug'),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Valid 32-byte encryption key' => $validKey,
            'GD raster image support' => extension_loaded('gd'),
            'MySQL connection configured' => config('database.default') === 'mysql',
            'Server-side database sessions' => config('session.driver') === 'database',
            'Secure HTTP-only encrypted sessions' => config('session.secure') && config('session.http_only') && config('session.encrypt') && in_array(config('session.same_site'), ['lax', 'strict']),
            'Shared database cache' => config('cache.default') === 'database',
            'Synchronous or database queue' => in_array(config('queue.default'), ['sync', 'database']),
            'Private persistent document disk' => ($settings['driver'] ?? null) === 's3' && ! empty($settings['bucket']) && empty($settings['url']) && ($settings['visibility'] ?? null) !== 'public',
            'S3 adapter installed' => class_exists(AwsS3V3Adapter::class),
            'Password recovery transport enabled' => config('iyawe.password_reset_enabled'),
            'Warning-level stderr logging configured' => config('logging.default') === 'stderr' && in_array(config('logging.channels.stderr.level'), ['warning', 'error', 'critical', 'alert', 'emergency']),
            'PWA build present' => is_file(public_path('build/manifest.json')) && is_file(public_path('sw.js')) && is_file(public_path('manifest.webmanifest')),
        ];
        if ($this->option('probe-database')) {
            try {
                DB::select('SELECT 1');
                foreach (['users', 'companies', 'consent_versions', 'card_swaps', 'swap_signatures', 'sessions', 'cache', 'jobs'] as $table) {
                    if (! Schema::hasTable($table)) {
                        throw new \RuntimeException;
                    }
                }$checks['Database and migrated tables'] = true;
            } catch (Throwable) {
                $checks['Database and migrated tables'] = false;
            }
        }
        if ($this->option('probe-storage')) {
            $path = '_readiness/'.Str::uuid().'.enc';
            try {
                $storage = app(PrivateDocuments::class)->disk();
                $value = 'iyawe-staging-canary-'.Str::uuid();
                $stored = $storage->put($path, Crypt::encryptString($value));
                $checks['Private object storage round trip'] = $stored && hash_equals($value, app(PrivateDocuments::class)->read($path, hash('sha256', $value)));
            } catch (Throwable) {
                $checks['Private object storage round trip'] = false;
            } finally {
                if (isset($storage)) {
                    try {
                        $checks['Private canary cleanup'] = $storage->delete($path);
                    } catch (Throwable) {
                        $checks['Private canary cleanup'] = false;
                    }
                }
            }
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS ' : 'FAIL ').$label);
        }

        return in_array(false, array_values($checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
