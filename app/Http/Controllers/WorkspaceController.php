<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Support\Audit;
use App\Support\SwapAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    private function company(Request $request, string $permission): Company
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, $permission]);

        return $company;
    }

    public function dashboard(Request $request): View
    {
        $company = $request->attributes->get('company');

        return view('dashboard', app(SwapAnalytics::class)->dashboard($request) + [
            'totalCompanies' => $request->user()->is_system_admin ? Company::count() : 0,
            'activeCompanies' => $request->user()->is_system_admin ? Company::where('status', 'active')->count() : 0,
            'suspendedCompanies' => $request->user()->is_system_admin ? Company::where('status', 'suspended')->count() : 0,
            'recentCompanies' => $request->user()->is_system_admin ? Company::latest()->take(5)->get() : collect(),
            'staffCount' => $company && $request->user()->can('staff.manage') ? $company->users()->count() : 0,
            'activeStaffCount' => $company && $request->user()->can('staff.manage') ? $company->users()->wherePivot('active', true)->where('users.active', true)->count() : 0,
            'invitations' => $request->user()->companies()->wherePivot('pending', true)->where('status', 'active')->get(),
        ]);
    }

    public function switchCompany(Request $request): RedirectResponse
    {
        $request->validate(['company_id' => ['required', 'integer']]);
        $company = $request->user()->companies()->wherePivot('active', true)->wherePivot('pending', false)->where('status', 'active')->findOrFail($request->integer('company_id'));
        $request->session()->put('company_id', $company->id);
        $request->session()->regenerate();
        Audit::record('company.switched', $company->id, $company->id);

        return redirect()->route('dashboard');
    }

    public function acceptInvitation(Request $request, int $company): RedirectResponse
    {
        DB::transaction(function () use ($request, $company) {
            Company::whereKey($company)->lockForUpdate()->firstOrFail();
            $record = $request->user()->companies()->where('status', 'active')->wherePivot('pending', true)->findOrFail($company);
            $record->users()->updateExistingPivot($request->user()->id, ['active' => true, 'pending' => false]);
            Audit::record('membership.accepted', $record->id, $request->user()->id);
        });

        return redirect()->route('dashboard')->with('status', __('Membership accepted.'));
    }

    public function locations(Request $request): View
    {
        $company = $this->company($request, 'locations.manage');

        return view('locations.index', ['locations' => Location::where('company_id', $company->id)->paginate(15)]);
    }

    public function saveLocation(Request $request, ?int $location = null): RedirectResponse
    {
        $company = $this->company($request, 'locations.manage');
        $record = $location ? Location::where('company_id', $company->id)->findOrFail($location) : new Location;
        $data = $request->validate(['name' => ['required', 'string', 'max:180', Rule::unique('locations')->where('company_id', $company->id)->ignore($record->id)], 'address' => ['nullable', 'string', 'max:255'], 'active' => ['required', 'boolean']]);
        DB::transaction(function () use ($record, $data, $company) {
            $record->fill([...$data, 'company_id' => $company->id])->save();
            Audit::record('location.saved', $company->id, $record->id);
        });

        return back()->with('status', __('Location saved.'));
    }

    public function settings(Request $request): View
    {
        return view('settings');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($request->user()->id)], 'current_password' => ['required', 'current_password'], 'password' => ['nullable', 'confirmed', Password::min(12)->letters()->numbers()], 'locale' => ['required', 'in:en,rw']]);
        $user = $request->user();
        $user->name = $data['name'];
        $user->email = strtolower($data['email']);
        $user->locale = $data['locale'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        DB::transaction(function () use ($user) {
            $user->save();
            Audit::record('account.updated', null, $user->id);
        });

        return back()->with('status', __('Settings saved.'));
    }

    public function saveCompanySettings(Request $request): RedirectResponse
    {
        $company = $this->company($request, 'settings.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40']]);
        DB::transaction(function () use ($company, $data) {
            $company->update($data);
            Audit::record('company.settings.updated', $company->id, $company->id);
        });

        return back()->with('status', __('Settings saved.'));
    }

    public function savePlatformSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_system_admin, 403);
        $data = $request->validate(['default_locale' => ['required', 'in:en,rw'], 'support_email' => ['nullable', 'email', 'max:254']]);
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            Audit::record('platform.settings.updated');
        });

        return back()->with('status', __('Settings saved.'));
    }
}
