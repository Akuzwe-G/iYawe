<?php

namespace App\Http\Requests;

use App\Support\CardBalances;
use App\Support\CommuterIdentifiers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->attributes->get('company') !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(CommuterIdentifiers::normaliseCards($this->only(['old_card', 'new_card'])));
    }

    public function messages(): array
    {
        return CommuterIdentifiers::messages(is_string($this->input('identification_type')) ? $this->input('identification_type') : null) + (CardBalances::forRequest($this) ? CardBalances::messages() : []) + [
            'location_id.required' => __('Choose a location to continue.'),
            'location_id.integer' => __('Choose an active location for your company.'),
            'location_id.exists' => __('Choose an active location for your company.'),
        ];
    }

    public function rules(): array
    {
        $company = $this->attributes->get('company')->id;
        $balanceRules = CardBalances::forRequest($this) ? CardBalances::rules() : ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'];
        $common = ['revision' => ['required', 'integer', 'min:0']];

        return $common + match ((int) $this->route('step')) {
            1 => ['swap_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'commuter_name' => ['required', 'string', 'max:180'], 'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,25}$/'], 'email' => ['nullable', 'email', 'max:254'], 'identification_type' => CommuterIdentifiers::typeRules(), 'national_id' => CommuterIdentifiers::numberRules(is_string($this->input('identification_type')) ? $this->input('identification_type') : null), 'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('company_id', $company)->where('active', true)]],
            2 => ['old_card' => [...CommuterIdentifiers::cardRules(), 'different:new_card'], 'new_card' => [...CommuterIdentifiers::cardRules(), 'different:old_card'], 'old_balance' => $balanceRules, 'new_balance' => $balanceRules, 'reason' => ['required', Rule::in(['lost', 'damaged', 'faulty', 'other'])], 'reason_other' => ['nullable', 'required_if:reason,other', 'string', 'max:1000']],
            3 => ['consent_version_id' => ['required', 'integer', Rule::exists('consent_versions', 'id')->where('company_id', $company)->where('active', true)], 'consent_name' => ['required', 'string', 'max:180'], 'acknowledged' => ['accepted'], 'signature' => ['required', 'string', 'max:750000']],
            4 => ['outcome' => ['required', Rule::in(['completed', 'pending', 'unsuccessful'])], 'physical_confirmation' => ['required_if:outcome,completed', 'accepted_if:outcome,completed'], 'remarks' => ['nullable', 'required_if:outcome,unsuccessful', 'string', 'max:2000'], 'signature' => ['required', 'string', 'max:750000']],
            5 => ['receipt_acknowledged' => ['accepted'], 'signature' => ['required', 'string', 'max:750000']],
            default => [],
        };
    }
}
