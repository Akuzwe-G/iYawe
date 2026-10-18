<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Company;
use App\Support\Audit;
use App\Support\CompanyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private function company(Request $request): Company
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'roles.manage']);

        return $company;
    }

    private function editable(Company $company, ?int $role): Role
    {
        $record = $role ? Role::where('company_id', $company->id)->findOrFail($role) : new Role;
        abort_if(in_array($record->name, ['Company Administrator', 'Field Staff']), 403);

        return $record;
    }

    public function index(Request $request): View
    {
        $company = $this->company($request);

        return view('roles.index', ['roles' => Role::where('company_id', $company->id)->with('permissions')->get()]);
    }

    public function form(Request $request, ?int $role = null): View
    {
        return view('roles.form', ['role' => $this->editable($this->company($request), $role), 'catalogue' => CompanyAccess::PERMISSIONS]);
    }

    public function save(RoleRequest $request, ?int $role = null): RedirectResponse
    {
        $company = $this->company($request);
        $record = $this->editable($company, $role);
        $data = $request->validated();
        DB::transaction(function () use ($record, $company, $data) {
            $record->forceFill(['name' => $data['name'], 'company_id' => $company->id, 'guard_name' => 'web'])->save();
            $record->syncPermissions($data['permissions'] ?? []);
            Audit::record('role.saved', $company->id, $record->id);
        });

        return redirect()->route('roles.index')->with('status', __('Role saved.'));
    }
}
