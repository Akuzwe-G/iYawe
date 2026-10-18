<?php

namespace App\Console\Commands;

use App\Models\CardSwap;
use App\Models\SwapSignature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CopyPrivateDocuments extends Command
{
    protected $signature = 'iyawe:documents-copy {--from=local} {--to= : Destination private disk} {--write : Copy and verify ciphertext; default validates source only}';

    protected $description = 'Validate and optionally copy referenced encrypted documents without deleting sources or overwriting conflicts';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        foreach ([$from, $to] as $name) {
            $config = config('filesystems.disks.'.$name);
            if (! $name || ! $config || $name === 'public' || ($config['visibility'] ?? null) === 'public' || ! empty($config['url']) || ! in_array($config['driver'] ?? null, ['local', 's3'])) {
                $this->error('Both disks must be configured private disks without public URLs.');

                return self::FAILURE;
            }
        }
        if ($from === $to) {
            $this->error('Source and destination must differ.');

            return self::FAILURE;
        }
        $source = Storage::disk($from);
        $target = Storage::disk($to);
        $count = 0;
        try {
            $documents = (function () {
                foreach (SwapSignature::query()->lazyById(100) as $signature) {
                    yield [$signature->path, $signature->sha256];
                }foreach (CardSwap::withTrashed()->whereNotNull('pdf_path')->lazyById(100) as $swap) {
                    yield [$swap->pdf_path, $swap->pdf_hash];
                }
            })();
            foreach ($documents as [$path,$hash]) {
                if (! $source->exists($path)) {
                    throw new \RuntimeException('Missing source.');
                }
                $cipher = $source->get($path);
                $plain = Crypt::decryptString($cipher);
                if (! hash_equals((string) $hash, hash('sha256', $plain))) {
                    throw new \RuntimeException('Source integrity failure.');
                }
                if ($this->option('write')) {
                    if ($target->exists($path)) {
                        if (! hash_equals(hash('sha256', $cipher), hash('sha256', $target->get($path)))) {
                            throw new \RuntimeException('Destination conflict.');
                        }
                    } elseif (! $target->put($path, $cipher)) {
                        throw new \RuntimeException('Write failed.');
                    }
                    if (! hash_equals(hash('sha256', $cipher), hash('sha256', $target->get($path)))) {
                        throw new \RuntimeException('Copy verification failed.');
                    }
                }
                $count++;
            }
        } catch (Throwable) {
            $this->error('Document validation/copy failed. Sources were retained. Check missing files, integrity, disk permissions and destination conflicts.');

            return self::FAILURE;
        }
        $this->info($count.' referenced documents verified'.($this->option('write') ? ' and copied.' : ' (validation only; no writes).'));

        return self::SUCCESS;
    }
}
