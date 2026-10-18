<?php

namespace App\Http\Controllers;

use App\Support\SwapQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerModeController extends Controller
{
    public function enter(Request $request, SwapQuery $queries, int $swap): RedirectResponse
    {
        $record = $queries->visible($request)->findOrFail($swap);
        Gate::authorize('update', $record);
        abort_unless(in_array($record->step, [3, 5]), 409);
        $data = $request->validate(['language' => ['required', 'in:en,rw']]);
        $locale = $record->step === 5 ? $record->consent_language : $data['language'];
        $request->session()->put('customer_handoff', ['swap_id' => $record->id, 'company_id' => $record->company_id, 'step' => $record->step, 'locale' => $locale]);

        return redirect()->route('swaps.edit', $record);
    }

    public function done(Request $request, SwapQuery $queries, int $swap): View
    {
        $record = $queries->visible($request)->findOrFail($swap);
        Gate::authorize('view', $record);
        abort_unless((int) $request->session()->get('customer_handoff.swap_id') === $record->id, 403);
        $stage = (int) $request->session()->get('customer_handoff.step');
        $captured = $stage === 3 ? $record->consent_signed_at !== null : $record->completed_at !== null;

        return view('swaps.customer-done', ['swap' => $record, 'captured' => $captured]);
    }

    public function leave(Request $request, SwapQuery $queries, int $swap): RedirectResponse
    {
        $record = $queries->visible($request)->findOrFail($swap);
        Gate::authorize('view', $record);
        abort_unless((int) $request->session()->get('customer_handoff.swap_id') === $record->id, 403);
        $request->validate(['current_password' => ['required', 'current_password']]);
        $request->session()->forget('customer_handoff');
        $request->session()->regenerate();

        return redirect()->route(in_array($record->status, ['completed', 'processing_unsuccessful']) ? 'swaps.show' : 'swaps.edit', $record);
    }
}
