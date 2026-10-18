<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftRequest;
use App\Models\CardSwap;
use App\Support\SwapQuery;
use App\Support\SwapWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DraftController extends Controller
{
    public function index(Request $request, SwapQuery $queries): View
    {
        $drafts = $queries->visible($request)->where('created_by', $request->user()->id)->where('status', 'draft')->latest('updated_at')->paginate(15);

        return view('swaps.drafts', compact('drafts'));
    }

    public function save(DraftRequest $request, SwapQuery $queries, SwapWorkflow $workflow, int $swap): JsonResponse
    {
        $record = $queries->visible($request)->findOrFail($swap);
        Gate::authorize('update', $record);
        $data = $request->validated();
        $record = DB::transaction(function () use ($record, $data, $workflow) {
            $record = CardSwap::where('company_id', $record->company_id)->lockForUpdate()->findOrFail($record->id);
            abort_unless($record->status === 'draft' && ! $record->consent_signed_at && ! $record->signatures()->exists(), 409, __('Signed records cannot be autosaved.'));
            abort_unless($record->revision === (int) $data['revision'] && $record->step === (int) $data['step'], 409, __('This record changed. Reload before continuing.'));
            $keys = $record->step === 1 ? ['swap_date', 'commuter_name', 'phone', 'email', 'identification_type', 'national_id', 'location_id'] : ['old_card', 'new_card', 'old_balance', 'new_balance', 'reason', 'reason_other'];
            $changed = false;
            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    $value = $data[$key];
                    if (in_array($key, ['old_balance', 'new_balance']) && $value !== null) {
                        $value = number_format((float) $value, 2, '.', '');
                    }
                    $old = $key === 'swap_date' ? $record->swap_date?->format('Y-m-d') : $record->$key;
                    if ((string) $old !== (string) $value) {
                        $record->$key = $value;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                foreach (['national_id' => 'national_id_hash', 'commuter_name' => 'name_hash', 'old_card' => 'old_card_hash', 'new_card' => 'new_card_hash'] as $key => $hash) {
                    $record->$hash = $record->$key ? CardSwap::fingerprint($record->$key) : null;
                }
                $record->revision++;
                $record->save();
                $workflow->event($record, 'draft.autosaved');
            }

            return $record;
        });

        return response()->json(['saved' => true, 'revision' => $record->revision, 'saved_at' => $record->updated_at->toIso8601String()])->header('Cache-Control', 'no-store, private');
    }

    public function discard(Request $request, SwapQuery $queries, SwapWorkflow $workflow, int $swap): RedirectResponse
    {
        $record = $queries->visible($request)->findOrFail($swap);
        Gate::authorize('update', $record);
        $request->validate(['revision' => ['required', 'integer']]);
        DB::transaction(function () use ($record, $workflow, $request) {
            $record = CardSwap::where('company_id', $record->company_id)->lockForUpdate()->findOrFail($record->id);
            abort_unless($record->status === 'draft' && ! $record->consent_signed_at && ! $record->signatures()->exists() && $record->revision === $request->integer('revision'), 409);
            $workflow->event($record, 'draft.discarded');
            $record->delete();
        });

        return redirect()->route('drafts.index')->with('status', __('Draft discarded. Its audit history is retained.'));
    }
}
