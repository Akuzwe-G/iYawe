<?php

namespace App\Support;

use App\Models\CardSwap;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class DocumentBranding
{
    public const FIELDS = ['brand_display_name', 'brand_legal_name', 'brand_address', 'brand_email', 'brand_phone', 'brand_report_title', 'brand_footer'];

    public static function rules(): array
    {
        return [
            'brand_display_name' => ['nullable', 'string', 'max:180'], 'brand_legal_name' => ['nullable', 'string', 'max:240'], 'brand_address' => ['nullable', 'string', 'max:1000'],
            'brand_email' => ['nullable', 'email', 'max:254'], 'brand_phone' => ['nullable', 'string', 'max:40'], 'brand_report_title' => ['nullable', 'string', 'max:240'], 'brand_footer' => ['nullable', 'string', 'max:1500'],
            'brand_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'brand_remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function update(Company $company, array $data, ?UploadedFile $logo = null): void
    {
        $branding = $company->document_branding ?? [];
        foreach (self::FIELDS as $key) {
            if (array_key_exists($key, $data)) {
                $branding[substr($key, 6)] = $data[$key] ?: null;
            }
        }
        if (! empty($data['brand_remove_logo'])) {
            $branding['logo_data_uri'] = null;
        }
        if ($logo) {
            $bytes = file_get_contents($logo->getRealPath());
            $source = @imagecreatefromstring($bytes);
            if (! $source) {
                throw ValidationException::withMessages(['brand_logo' => __('Upload a valid PNG, JPEG or WebP logo.')]);
            }
            $scale = min(1, 720 / imagesx($source), 360 / imagesy($source));
            $width = max(1, (int) round(imagesx($source) * $scale));
            $height = max(1, (int) round(imagesy($source) * $scale));
            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
            ob_start();
            imagepng($target, null, 9);
            $png = ob_get_clean();
            imagedestroy($source);
            imagedestroy($target);
            $branding['logo_data_uri'] = 'data:image/png;base64,'.base64_encode($png);
        }
        $company->document_branding = $branding;
        $company->save();
    }

    public function current(Company $company): array
    {
        $data = $company->document_branding ?? [];
        $display = $data['display_name'] ?? $company->name;

        return ['display_name' => $display, 'legal_name' => $data['legal_name'] ?? $display, 'address' => $data['address'] ?? null, 'email' => $data['email'] ?? $company->email, 'phone' => $data['phone'] ?? $company->phone, 'report_title' => $data['report_title'] ?? null, 'footer' => $data['footer'] ?? null, 'logo_data_uri' => $data['logo_data_uri'] ?? null];
    }

    public function finalise(CardSwap $swap): array
    {
        $company = Company::whereKey($swap->company_id)->lockForUpdate()->firstOrFail();

        return $this->current($company);
    }

    public function historical(CardSwap $swap): array
    {
        if ($swap->branding_snapshot) {
            return $swap->branding_snapshot;
        }
        $name = $swap->company_name_snapshot ?: __('Company');

        return ['display_name' => $name, 'legal_name' => $name, 'address' => null, 'email' => null, 'phone' => null, 'report_title' => null, 'footer' => null, 'logo_data_uri' => null];
    }
}
