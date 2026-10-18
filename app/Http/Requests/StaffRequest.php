<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->attributes->get('company');

        return $company && $this->user()->can('administer', [$company, 'staff.manage']);
    }

    public function rules(): array
    {
        $editing = $this->route('user') !== null;

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => [$editing ? 'prohibited' : 'required', 'email', 'max:254'],
            'password' => [$editing ? 'prohibited' : 'required', Password::min(12)->letters()->numbers()],
            'active' => ['required', 'boolean'],
            'roles' => ['array'], 'roles.*' => ['integer', Rule::exists('roles', 'id')->where('company_id', $this->attributes->get('company')->id)],
        ];
    }
}
