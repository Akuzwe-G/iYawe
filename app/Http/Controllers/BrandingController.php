<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrandingRequest;
use App\Models\Company;
use App\Support\Audit;
use App\Support\DocumentBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BrandingController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'branding.manage']);

        return view('branding.edit', ['company' => $company]);
    }

    public function update(BrandingRequest $request, DocumentBranding $branding): RedirectResponse
    {
        $company = $request->attributes->get('company');
        DB::transaction(function () use ($company, $request, $branding) {
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $branding->update($company, $request->validated(), $request->file('brand_logo'));
            Audit::record('company.branding.updated', $company->id, $company->id);
        });

        return back()->with('status', __('Document branding saved.'));
    }
}
