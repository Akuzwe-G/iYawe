<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class MembershipManager
{
    public function add(Company $company, array $data, array $roleIds): void
    {
        DB::transaction(function () use ($company, $data, $roleIds) {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $user = User::where('email', strtolower($data['email']))->first();
            $new = ! $user;
            $user ??= User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
            if ($company->users()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages(['email' => __('Unable to prepare this membership.')]);
            }
            $company->users()->attach($user->id, ['active' => $new && (bool) ($data['active'] ?? true), 'pending' => ! $new, 'display_name' => $data['name']]);
            $previous = getPermissionsTeamId();
            try {
                setPermissionsTeamId($company->id);
                $user->unsetRelation('roles')->syncRoles(Role::where('company_id', $company->id)->whereIn('id', $roleIds)->get());
            } finally {
                setPermissionsTeamId($previous);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }
            Audit::record('membership.created', $company->id, $user->id);
        });
    }

    public function update(Company $company, int $userId, array $data, array $roleIds): void
    {
        DB::transaction(function () use ($company, $userId, $data, $roleIds) {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $user = $company->users()->findOrFail($userId);
            $roles = Role::where('company_id', $company->id)->whereIn('id', $roleIds)->get();
            if ($user->active && $user->pivot->active && $user->hasRole('Company Administrator')
                && (! $data['active'] || ! $roles->contains('name', 'Company Administrator'))) {
                $others = $company->users()->wherePivot('active', true)->where('users.active', true)->where('users.id', '!=', $user->id)->get();
                if (! $others->contains(fn (User $member) => $member->hasRole('Company Administrator'))) {
                    throw ValidationException::withMessages(['roles' => __('Keep at least one active Company Administrator.')]);
                }
            }
            if ($user->pivot->pending && $data['active']) {
                throw ValidationException::withMessages(['active' => __('The account owner must accept this invitation.')]);
            }
            $company->users()->updateExistingPivot($user->id, ['display_name' => $data['name'], 'active' => (bool) $data['active']]);
            $user->syncRoles($roles);
            Audit::record('membership.updated', $company->id, $user->id);
        });
    }
}
