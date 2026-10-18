<?php

namespace App\Http\Controllers;

use App\Models\CardSwap;
use App\Models\Location;
use App\Support\Audit;
use App\Support\SwapAnalytics;
use App\Support\SwapQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, SwapQuery $queries, SwapAnalytics $analytics): View
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'reports.view']);
        $query = $queries->filters(CardSwap::where('company_id', $company->id), $request);

        return view('reports.index', ['summary' => $analytics->summary($query), 'locations' => Location::where('company_id', $company->id)->get(), 'staff' => $company->users()->get(), 'records' => $request->user()->can('swaps.view') ? $queries->filters($queries->visible($request), $request)->with('creator')->latest()->paginate(20)->withQueryString() : null]);
    }

    public function export(Request $request, SwapQuery $queries): StreamedResponse
    {
        $company = $request->attributes->get('company');
        abort_unless($company, 403);
        Gate::authorize('administer', [$company, 'reports.export']);
        Gate::authorize('administer', [$company, 'reports.view']);
        $query = $queries->filters(CardSwap::where('company_id', $company->id), $request)->with(['creator', 'location']);
        $summary = app(SwapAnalytics::class)->summary($query);
        Audit::record('report.exported', $company->id);

        return response()->streamDownload(function () use ($query, $summary) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [__('Category'), __('Count')], ',', '"', '');
            foreach (['total' => 'Total acknowledgements', 'completed' => 'Completed', 'pending' => 'Pending', 'unsuccessful' => 'Unsuccessful', 'completion_rate' => 'Completion rate'] as $key => $label) {
                fputcsv($output, [__($label), $summary[$key]], ',', '"', '');
            }
            fputcsv($output, [], ',', '"', '');
            fputcsv($output, array_map(fn ($s) => __($s), ['Reference', 'Date', 'Status', 'Reason', 'Staff', 'Location']), ',', '"', '');
            foreach ($query->lazyById(200) as $swap) {
                $values = [$swap->reference, $swap->swap_date?->format('Y-m-d'), __($swap->status), __($swap->reason ?? 'draft'), $swap->creator?->name, $swap->location?->name];
                $values = array_map(fn ($v) => preg_match('/^(?:\s*[=+@\-]|[\t\r\n])/', (string) $v) ? "'".$v : $v, $values);
                fputcsv($output, $values, ',', '"', '');
            }
            fclose($output);
        }, 'iyawe-summary-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
