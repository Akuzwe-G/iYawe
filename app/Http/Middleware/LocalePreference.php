<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalePreference
{
    public function handle(Request $request, Closure $next): Response
    {
        $preferences = PlatformSetting::preferences();
        $locale = $request->user()?->locale ?? $request->session()->get('locale', $preferences['default_locale']);
        app()->setLocale(in_array($locale, ['en', 'rw'], true) ? $locale : 'en');
        view()->share('platformPreferences', $preferences);

        return $next($request);
    }
}
