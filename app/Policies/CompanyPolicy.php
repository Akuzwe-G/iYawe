<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->is_system_admin;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->viewAny($user);
    }

    public function administer(User $user, Company $company, string $permission): bool
    {
        return $user->active && $company->status === 'active'
            && (int) getPermissionsTeamId() === $company->id
            && $user->companies()->whereKey($company->id)->wherePivot('active', true)->wherePivot('pending', false)->exists()
            && $user->can($permission);
    }
}
