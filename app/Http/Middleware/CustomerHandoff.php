<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerHandoff
{
    public function handle(Request $request, Closure $next): Response
    {
        $lock = $request->session()->get('customer_handoff');
        if ($lock) {
            if (! $request->routeIs('locale') && $request->attributes->get('company')?->id !== (int) $lock['company_id']) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => __('Session expired. Please sign in again.')], 401)->header('Cache-Control', 'no-store, private')
                    : redirect()->route('login');
            }
            $same = (int) $request->route('swap') === (int) $lock['swap_id'] && (int) $request->session()->get('company_id') === (int) $lock['company_id'];
            $allowed = $request->routeIs('logout') || ($same && ($request->routeIs('swaps.edit', 'customer.done', 'customer.return') || ($request->routeIs('swaps.advance') && (int) $request->route('step') === (int) $lock['step'])));
            if (! $allowed) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => __('Return the phone to staff to unlock navigation.')], 423)->header('Cache-Control', 'no-store, private');
                }

                return redirect()->route('customer.done', $lock['swap_id']);
            }
            app()->setLocale($lock['locale']);
        }

        return $next($request);
    }
}
