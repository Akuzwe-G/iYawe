<?php

namespace App\Http\Requests;

use App\Support\CommuterIdentifiers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DraftRequest extends FormRequest
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
        return CommuterIdentifiers::messages(is_string($this->input('identification_type')) ? $this->input('identification_type') : null);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $old = $this->input('old_card');
            $new = $this->input('new_card');
            if (is_string($old) && is_string($new) && strlen($old) === 8 && $old === $new) {
                $validator->errors()->add('new_card', __('The existing and replacement card numbers must be different.'));
            }
        }];
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'], 'step' => ['required', 'integer', 'in:1,2'],
            'swap_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'commuter_name' => ['sometimes', 'nullable', 'string', 'max:180'], 'phone' => ['sometimes', 'nullable', 'string', 'max:25'], 'email' => ['sometimes', 'nullable', 'string', 'max:254'], 'identification_type' => ['sometimes', ...CommuterIdentifiers::typeRules(true)], 'national_id' => ['sometimes', ...CommuterIdentifiers::numberRules(is_string($this->input('identification_type')) ? $this->input('identification_type') : null, true)],
            'location_id' => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $this->attributes->get('company')?->id)->where('active', true)],
            'old_card' => ['sometimes', ...CommuterIdentifiers::cardRules(true)], 'new_card' => ['sometimes', ...CommuterIdentifiers::cardRules(true)],
            'old_balance' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'], 'new_balance' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'reason' => ['sometimes', 'nullable', 'in:lost,damaged,faulty,other'], 'reason_other' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
