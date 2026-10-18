<?php

namespace App\Policies;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\User;

class CardSwapPolicy
{
    public function create(User $user): bool
    {
        return $this->companyPermission($user, 'swaps.create');
    }

    private function companyPermission(User $user, string $permission): bool
    {
        $company = Company::find(getPermissionsTeamId());

        return $company && $user->can('administer', [$company, $permission]);
    }

    public function view(User $user, CardSwap $swap): bool
    {
        return (int) getPermissionsTeamId() === $swap->company_id && $this->companyPermission($user, 'swaps.view') && ($swap->created_by === $user->id || $user->can('swaps.view-all'));
    }

    public function update(User $user, CardSwap $swap): bool
    {
        return $this->view($user, $swap) && ! in_array($swap->status, ['completed', 'processing_unsuccessful']) && $user->can('swaps.update-pending');
    }

    public function complete(User $user, CardSwap $swap): bool
    {
        return $this->update($user, $swap) && $user->can('swaps.complete');
    }

    public function download(User $user, CardSwap $swap): bool
    {
        return $this->view($user, $swap) && $swap->status === 'completed' && $user->can('swaps.download');
    }
}
