<?php

namespace App\Http\Requests;

use App\Support\DocumentBranding;
use Illuminate\Foundation\Http\FormRequest;

class BrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->attributes->get('company');

        return $company && $this->user()->can('administer', [$company, 'branding.manage']);
    }

    public function rules(): array
    {
        return DocumentBranding::rules();
    }
}
