<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\Company;
use App\Models\User;
use App\Support\MembershipManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    private function company(Request $request): Company
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'staff.manage']);

        return $company;
    }

    public function index(Request $request): View
    {
        $company = $this->company($request);
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $users = $company->users()->with('roles')->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('company_user.display_name', 'like', '%'.$request->input('search').'%')->orWhere('email', 'like', '%'.$request->input('search').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('company_user.active', $request->input('status') === 'active'))->paginate(15)->withQueryString();

        return view('staff.index', compact('users'));
    }

    public function form(Request $request, ?int $user = null): View
    {
        $company = $this->company($request);
        $staff = $user ? $company->users()->findOrFail($user) : new User;
        $this->checkAssignable($request, $staff);

        return view('staff.form', [
            'staff' => $staff,
            'roles' => Role::where('company_id', $company->id)->when(! $request->user()->can('roles.manage'), fn ($q) => $q->where('name', 'Field Staff'))->get(),
        ]);
    }

    private function checkAssignable(Request $request, User $user): void
    {
        if (! $request->user()->can('roles.manage') && $user->exists) {
            abort_if($user->roles->contains(fn ($role) => $role->name !== 'Field Staff'), 403);
        }
    }

    public function save(StaffRequest $request, MembershipManager $memberships, ?int $user = null): RedirectResponse
    {
        $company = $this->company($request);
        $data = $request->validated();
        $roles = Role::where('company_id', $company->id)->whereIn('id', $data['roles'] ?? [])->get();
        if (! $request->user()->can('roles.manage')) {
            abort_if($roles->contains(fn ($role) => $role->name !== 'Field Staff'), 403);
        }
        if ($user) {
            $this->checkAssignable($request, $company->users()->findOrFail($user));
            $memberships->update($company, $user, $data, $roles->modelKeys());
        } else {
            $memberships->add($company, $data, $roles->modelKeys());
        }

        return redirect()->route('staff.index')->with('status', __('Membership prepared. Existing account holders must accept from their dashboard.'));
    }
}
