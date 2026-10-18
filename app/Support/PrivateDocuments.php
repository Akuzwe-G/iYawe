<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PrivateDocuments
{
    public function disk(?string $name = null): Filesystem
    {
        $name ??= config('iyawe.documents_disk', 'local');
        $settings = config('filesystems.disks.'.$name);
        if (! $settings || $name === 'public' || ($settings['visibility'] ?? null) === 'public' || ! empty($settings['url']) || ! in_array($settings['driver'] ?? null, ['local', 's3'], true)) {
            throw new RuntimeException('A private local or S3 document disk is required.');
        }
        if (app()->environment('staging', 'production') && ($settings['driver'] ?? null) !== 's3') {
            throw new RuntimeException('Persistent object storage is required for cloud documents.');
        }

        return Storage::disk($name);
    }

    public function read(string $path, string $hash): string
    {
        $disk = $this->disk();
        abort_unless($disk->exists($path), 410, __('The archived document is unavailable. Contact your administrator.'));
        $encrypted = $disk->get($path);
        try {
            $bytes = Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            abort(500, __('Document integrity verification failed.'));
        }
        abort_unless(hash_equals($hash, hash('sha256', $bytes)), 500, __('Document integrity verification failed.'));

        return $bytes;
    }
}
