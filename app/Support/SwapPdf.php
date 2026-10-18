<?php

namespace App\Support;

use App\Models\CardSwap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SwapPdf
{
    public function bytes(CardSwap $record): string
    {
        return DB::transaction(function () use ($record) {
            $swap = CardSwap::where('company_id', $record->company_id)->lockForUpdate()->findOrFail($record->id);
            abort_unless($swap->status === 'completed', 409);
            if ($swap->pdf_path) {
                abort_unless(Storage::disk('local')->exists($swap->pdf_path), 410, __('The archived PDF is unavailable. Contact your administrator.'));
                $bytes = Crypt::decryptString(Storage::disk('local')->get($swap->pdf_path));
                abort_unless(hash_equals($swap->pdf_hash, hash('sha256', $bytes)), 500);

                return $bytes;
            }
            $swap->load(['consentVersion', 'location', 'signatures']);
            $images = [];
            foreach (['consent', 'staff', 'receipt'] as $purpose) {
                $signature = $swap->signatures->where('purpose', $purpose)->sortByDesc('revision')->first();
                abort_unless($signature, 409);
                $png = Crypt::decryptString(Storage::disk('local')->get($signature->path));
                abort_unless(hash_equals($signature->sha256, hash('sha256', $png)), 500);
                $images[$purpose] = 'data:image/png;base64,'.base64_encode($png);
            }
            $fontFiles = glob(resource_path('fonts/Montserrat-*.ttf'));
            $fontAvailable = count($fontFiles) >= 2;
            $branding = app(DocumentBranding::class)->historical($swap);
            $originalLocale = app()->getLocale();
            try {
                app()->setLocale($swap->consent_language);
                $bytes = Pdf::loadView('swaps.pdf', compact('swap', 'images', 'fontAvailable', 'branding'))->setPaper('a4')->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => [resource_path(), storage_path(), public_path()]])->output();
            } finally {
                app()->setLocale($originalLocale);
            }
            $path = 'swaps/'.$swap->company_id.'/'.$swap->id.'/acknowledgement.enc';
            if (! Storage::disk('local')->put($path, Crypt::encryptString($bytes))) {
                throw new \RuntimeException('Unable to securely store PDF.');
            }
            $swap->pdf_path = $path;
            $swap->pdf_hash = hash('sha256', $bytes);
            $swap->save();
            app(SwapWorkflow::class)->event($swap, 'pdf.generated');

            return $bytes;
        });
    }
}
