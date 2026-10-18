<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->attributes->get('company') !== null;
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'], 'step' => ['required', 'integer', 'in:1,2'],
            'swap_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'commuter_name' => ['sometimes', 'nullable', 'string', 'max:180'], 'phone' => ['sometimes', 'nullable', 'string', 'max:25'], 'email' => ['sometimes', 'nullable', 'string', 'max:254'], 'national_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'location_id' => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $this->attributes->get('company')?->id)->where('active', true)],
            'old_card' => ['sometimes', 'nullable', 'string', 'max:80'], 'new_card' => ['sometimes', 'nullable', 'string', 'max:80'],
            'old_balance' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'], 'new_balance' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'reason' => ['sometimes', 'nullable', 'in:lost,damaged,faulty,other'], 'reason_other' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
