<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Company::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('companies')->ignore($this->route('company'))],
            'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'in:active,suspended'],
        ];
    }
}
