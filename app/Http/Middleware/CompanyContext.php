<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user->active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }
        app()->setLocale($user->locale);
        $companies = $user->companies()->wherePivot('active', true)->wherePivot('pending', false)->where('status', 'active')->get();
        $company = $companies->firstWhere('id', (int) $request->session()->get('company_id'));
        if (! $company && $companies->count() === 1) {
            $company = $companies->first();
            $request->session()->put('company_id', $company->id);
        }
        if (! $company) {
            $request->session()->forget('company_id');
        }
        setPermissionsTeamId($company?->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $request->attributes->set('company', $company);
        view()->share(['activeCompany' => $company, 'availableCompanies' => $companies]);
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        } finally {
            setPermissionsTeamId(null);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
