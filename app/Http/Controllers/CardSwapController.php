<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwapStepRequest;
use App\Models\CardSwap;
use App\Models\ConsentVersion;
use App\Models\Location;
use App\Models\SwapSignature;
use App\Support\ConsentPublisher;
use App\Support\PrivateDocuments;
use App\Support\SwapPdf;
use App\Support\SwapQuery;
use App\Support\SwapWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CardSwapController extends Controller
{
    private function record(Request $request, int $swap): CardSwap
    {
        return app(SwapQuery::class)->visible($request)->findOrFail($swap);
    }

    public function index(Request $request, SwapQuery $queries): View
    {
        if ($request->boolean('searching') && $request->session()->has('swap_search')) {
            $saved = json_decode(Crypt::decryptString($request->session()->get('swap_search')), true, 512, JSON_THROW_ON_ERROR);
            if ($saved['company_id'] === $request->attributes->get('company')?->id) {
                $request->merge($saved['criteria']);
            }
        }
        $records = $queries->filters($queries->visible($request), $request)->with(['creator', 'location'])->latest()->paginate(15)->appends($request->except(['search', 'search_by']));

        return view('swaps.index', ['records' => $records, 'locations' => Location::where('company_id', $request->attributes->get('company')->id)->get(), 'staff' => $request->attributes->get('company')->users()->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', CardSwap::class);

        return view('swaps.create', ['submissionKey' => (string) Str::uuid()]);
    }

    public function store(Request $request, SwapWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('create', CardSwap::class);
        $request->validate(['submission_key' => ['required', 'uuid']]);
        $company = $request->attributes->get('company');
        $swap = DB::transaction(function () use ($request, $workflow, $company) {
            $swap = CardSwap::withTrashed()->firstOrCreate(['company_id' => $company->id, 'created_by' => $request->user()->id, 'submission_key' => $request->input('submission_key')], ['reference' => 'IY-'.now()->format('Ymd').'-'.Str::ulid(), 'swap_date' => today(), 'status' => 'draft', 'step' => 1]);
            abort_if($swap->trashed(), 409, __('This record changed. Reload before continuing.'));
            if ($swap->wasRecentlyCreated) {
                $workflow->event($swap, 'acknowledgement.created');
            }

            return $swap;
        });

        return redirect()->route('swaps.edit', $swap);
    }

    public function show(Request $request, int $swap): View
    {
        $record = $this->record($request, $swap);
        Gate::authorize('view', $record);

        return view('swaps.show', ['swap' => $record->load(['creator', 'verifier', 'location', 'consentVersion', 'signatures', 'events.actor'])]);
    }

    public function edit(Request $request, int $swap): View|RedirectResponse
    {
        $record = $this->record($request, $swap);
        $customerMode = (int) $request->session()->get('customer_handoff.swap_id') === $record->id;
        if ($customerMode && ($record->step !== (int) $request->session()->get('customer_handoff.step') || $record->status === 'completed')) {
            return redirect()->route('customer.done', $record);
        }
        Gate::authorize('update', $record);

        return view('swaps.wizard', ['customerMode' => $customerMode, 'swap' => $record->load('consentVersion'), 'locations' => Location::where('company_id', $record->company_id)->where('active', true)->get(), 'consents' => ConsentVersion::where('company_id', $record->company_id)->where('active', true)->when($customerMode, fn ($query) => $query->where('language', $request->session()->get('customer_handoff.locale')))->get()]);
    }

    public function advance(SwapStepRequest $request, SwapWorkflow $workflow, int $swap, int $step): RedirectResponse|JsonResponse
    {
        $record = $this->record($request, $swap);
        Gate::authorize($step >= 4 ? 'complete' : 'update', $record);
        if ($step === 3 && $request->session()->has('customer_handoff')) {
            abort_unless(ConsentVersion::where('company_id', $record->company_id)->where('language', $request->session()->get('customer_handoff.locale'))->whereKey($request->integer('consent_version_id'))->exists(), 422, __('Please check the form.'));
        }
        $record = $workflow->advance($record, $step, $request->validated());

        $target = $request->session()->has('customer_handoff') ? route('customer.done', $record) : route(in_array($record->status, ['completed', 'processing_unsuccessful']) ? 'swaps.show' : 'swaps.edit', $record);
        if ($request->expectsJson()) {
            $message = $record->status === 'completed' ? __('Acknowledgement completed') : ($step >= 3 ? __('Signature recorded') : __('Acknowledgement saved.'));
            $request->session()->flash('status', $message);

            return response()->json(['saved' => true, 'redirect' => $target, 'message' => $message])->header('Cache-Control', 'no-store, private');
        }

        return redirect($target)->with('status', __('Acknowledgement saved.'));
    }

    public function back(Request $request, SwapWorkflow $workflow, int $swap): RedirectResponse
    {
        $record = $this->record($request, $swap);
        $customerMode = (int) $request->session()->get('customer_handoff.swap_id') === $record->id;
        if ($customerMode && ($record->step !== (int) $request->session()->get('customer_handoff.step') || $record->status === 'completed')) {
            return redirect()->route('customer.done', $record);
        }
        Gate::authorize('update', $record);
        $request->validate(['revision' => ['required', 'integer'], 'step' => ['required', 'integer', 'in:1,2']]);
        DB::transaction(function () use ($record, $request, $workflow) {
            $record = CardSwap::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'draft' && $record->revision === $request->integer('revision') && $request->integer('step') < $record->step, 409);
            $record->step = $request->integer('step');
            $record->revision++;
            $record->save();
            $workflow->event($record, 'draft.reopened');
        });

        return redirect()->route('swaps.edit', $record);
    }

    public function signature(Request $request, int $swap, int $signature): Response
    {
        $record = $this->record($request, $swap);
        Gate::authorize('view', $record);
        $image = SwapSignature::where('company_id', $record->company_id)->where('card_swap_id', $record->id)->findOrFail($signature);
        $bytes = app(PrivateDocuments::class)->read($image->path, $image->sha256);

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function pdf(Request $request, SwapPdf $pdf, int $swap): Response
    {
        $record = $this->record($request, $swap);
        Gate::authorize('download', $record);
        $bytes = $pdf->bytes($record);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$record->reference.'.pdf"', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function consents(Request $request): View
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'settings.manage']);

        return view('swaps.consents', ['versions' => ConsentVersion::where('company_id', $company->id)->latest()->get()]);
    }

    public function publishConsent(Request $request): RedirectResponse
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'settings.manage']);
        $data = $request->validate(['language' => ['required', 'in:en,rw'], 'version' => ['required', 'string', 'max:80'], 'declaration' => ['required', 'string', 'max:20000'], 'receipt' => ['required', 'string', 'max:4000'], 'approved' => ['accepted'], 'replace_current' => ['nullable', 'boolean']]);
        app(ConsentPublisher::class)->publish($company, $request->user(), $data, $request->boolean('replace_current'));

        return back()->with('status', __('Consent version published.'));
    }

    public function search(Request $request, SwapQuery $queries): RedirectResponse
    {
        $queries->filters($queries->visible($request), $request);
        $criteria = $request->only(['search', 'search_by', 'identification_type', 'from', 'to', 'status', 'reason', 'staff', 'location']);
        $request->session()->put('swap_search', Crypt::encryptString(json_encode(['company_id' => $request->attributes->get('company')->id, 'criteria' => $criteria], JSON_THROW_ON_ERROR)));

        return redirect()->route('swaps.index', ['searching' => 1]);
    }
}
