<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Support\Audit;
use App\Support\CompanyAccess;
use App\Support\MembershipManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Company::class);
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,suspended']]);
        $companies = Company::query()->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))->latest()->paginate(15)->withQueryString();

        return view('companies.index', compact('companies'));
    }

    public function form(Request $request, ?int $company = null): View
    {
        Gate::authorize('create', Company::class);

        return view('companies.form', ['company' => $company ? Company::findOrFail($company) : new Company]);
    }

    public function save(CompanyRequest $request, ?int $company = null): RedirectResponse
    {
        $record = $company ? Company::findOrFail($company) : new Company;
        DB::transaction(function () use ($record, $request) {
            $record->fill($request->validated())->save();
            CompanyAccess::provision($record);
            Audit::record('company.saved', $record->id, $record->id);
        });

        return redirect()->route('companies.edit', $record)->with('status', __('Company saved.'));
    }

    public function administrator(Request $request, MembershipManager $memberships, int $company): RedirectResponse
    {
        Gate::authorize('create', Company::class);
        $record = Company::findOrFail($company);
        abort_unless($record->status === 'active', 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'email' => ['required', 'email', 'max:254'], 'password' => ['required', Password::min(12)->letters()->numbers()]]);
        $role = Role::where('company_id', $record->id)->where('name', 'Company Administrator')->firstOrFail();
        $memberships->add($record, $data, [$role->id]);

        return back()->with('status', __('Membership prepared. Existing account holders must accept from their dashboard.'));
    }
}
