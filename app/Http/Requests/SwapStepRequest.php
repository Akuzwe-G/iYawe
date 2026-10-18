<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->attributes->get('company') !== null;
    }

    public function rules(): array
    {
        $company = $this->attributes->get('company')->id;
        $common = ['revision' => ['required', 'integer', 'min:0']];

        return $common + match ((int) $this->route('step')) {
            1 => ['swap_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'commuter_name' => ['required', 'string', 'max:180'], 'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,25}$/'], 'email' => ['nullable', 'email', 'max:254'], 'national_id' => ['required', 'string', 'min:5', 'max:80'], 'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $company)->where('active', true)]],
            2 => ['old_card' => ['required', 'string', 'max:80', 'different:new_card'], 'new_card' => ['required', 'string', 'max:80'], 'old_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'], 'new_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'], 'reason' => ['required', Rule::in(['lost', 'damaged', 'faulty', 'other'])], 'reason_other' => ['nullable', 'required_if:reason,other', 'string', 'max:1000']],
            3 => ['consent_version_id' => ['required', 'integer', Rule::exists('consent_versions', 'id')->where('company_id', $company)->where('active', true)], 'consent_name' => ['required', 'string', 'max:180'], 'acknowledged' => ['accepted'], 'signature' => ['required', 'string', 'max:750000']],
            4 => ['outcome' => ['required', Rule::in(['completed', 'pending', 'unsuccessful'])], 'physical_confirmation' => ['required_if:outcome,completed', 'accepted_if:outcome,completed'], 'remarks' => ['nullable', 'required_if:outcome,unsuccessful', 'string', 'max:2000'], 'signature' => ['required', 'string', 'max:750000']],
            5 => ['receipt_acknowledged' => ['accepted'], 'signature' => ['required', 'string', 'max:750000']],
            default => [],
        };
    }
}
