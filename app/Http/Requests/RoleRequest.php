<?php

namespace App\Http\Requests;

use App\Support\CompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->attributes->get('company');

        return $company && $this->user()->can('administer', [$company, 'roles.manage']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::notIn(['Company Administrator', 'Field Staff', 'System Administrator']), Rule::unique('roles')->where('company_id', $this->attributes->get('company')->id)->ignore($this->route('role'))],
            'permissions' => ['array'], 'permissions.*' => ['string', Rule::in(CompanyAccess::PERMISSIONS)],
        ];
    }
}
