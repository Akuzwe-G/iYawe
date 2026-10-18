<?php

namespace App\Support;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SwapAnalytics
{
    public function range(Request $request): array
    {
        $request->validate(['period' => ['nullable', 'in:7,30,90,year,custom'], 'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'], 'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $end = CarbonImmutable::today();
        $period = $request->input('period', '30');
        $start = match ($period) {
            '7' => $end->subDays(6),'90' => $end->subDays(89),'year' => $end->startOfYear(),'custom' => CarbonImmutable::parse($request->input('from')),default => $end->subDays(29)
        };
        if ($period === 'custom') {
            $end = CarbonImmutable::parse($request->input('to'));
        }
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['from' => __('Choose a period of no more than 366 days.')]);
        }

        return [$start, $end];
    }

    public function summary(Builder $query): array
    {
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $completed = (int) ($counts['completed'] ?? 0);
        $pending = (int) ($counts['pending_processing'] ?? 0);

        return ['total' => $total, 'completed' => $completed, 'pending' => $pending, 'unsuccessful' => (int) ($counts['processing_unsuccessful'] ?? 0), 'completion_rate' => $total ? round($completed / $total * 100, 1) : 0, 'statuses' => array_map(fn ($s) => (int) ($counts[$s] ?? 0), CardSwap::STATUSES)];
    }

    private function chart(string $title, string $type, array $labels, array $datasets, bool $horizontal = false): array
    {
        return compact('title', 'type', 'labels', 'datasets', 'horizontal');
    }

    public function dashboard(Request $request): array
    {
        [$start,$end] = $this->range($request);
        $platform = $request->user()->is_system_admin;
        $company = $request->attributes->get('company');
        $charts = [];
        $metrics = [];
        $recent = collect();
        $daily = [];
        $done = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $daily[$date->toDateString()] = 0;
            $done[$date->toDateString()] = 0;
        }
        if ($platform) {
            $metrics = [['Registered companies (all time)', Company::count()], ['Active companies (all time)', Company::where('status', 'active')->count()], ['Suspended companies (all time)', Company::where('status', 'suspended')->count()], ['Registered users (all time)', User::count()], ['Recorded swaps', CardSwap::whereBetween('swap_date', [$start->toDateString(), $end->toDateString()])->count()]];
            $registrations = Company::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');
            foreach ($registrations as $day => $count) {
                if (array_key_exists($day, $daily)) {
                    $daily[$day] = (int) $count;
                }
            }
            $charts[] = $this->chart('Company registrations', 'line', array_keys($daily), [['label' => __('Companies'), 'data' => array_values($daily)]]);
            $months = CardSwap::whereBetween('swap_date', [$start->toDateString(), $end->toDateString()])->selectRaw('SUBSTR(swap_date,1,7) as month, COUNT(*) as total')->groupBy('month')->orderBy('month')->pluck('total', 'month');
            $charts[] = $this->chart('Platform activity by month', 'bar', $months->keys()->all(), [['label' => __('Acknowledgements'), 'data' => $months->values()->map(fn ($n) => (int) $n)->all()]]);
            $charts[] = $this->chart('Company status (all time)', 'doughnut', [__('Active companies'), __('Suspended companies')], [['data' => [Company::where('status', 'active')->count(), Company::where('status', 'suspended')->count()]]]);
        } elseif ($company && ($request->user()->can('swaps.view') || $request->user()->can('reports.view'))) {
            $all = $request->user()->can('reports.view');
            $base = CardSwap::where('company_id', $company->id)->whereBetween('swap_date', [$start->toDateString(), $end->toDateString()])->when(! $all, fn ($q) => $q->where('created_by', $request->user()->id));
            $summary = $this->summary($base);
            $metrics = [['Total acknowledgements', $summary['total']], ['Completed', $summary['completed']], ['Pending', $summary['pending']], ['Unsuccessful', $summary['unsuccessful']]];
            if ($all) {
                $metrics[] = ['Active field staff (all time)', $company->users()->wherePivot('active', true)->where('users.active', true)->whereHas('roles', fn ($q) => $q->where('name', 'Field Staff')->where('roles.company_id', $company->id))->count()];
                $metrics[] = ['Completion rate', $summary['completion_rate'].'%'];
            }
            $rows = (clone $base)->selectRaw('swap_date, status, COUNT(*) as total')->groupBy('swap_date', 'status')->get();
            foreach ($rows as $row) {
                $day = substr($row->getRawOriginal('swap_date'), 0, 10);
                if (isset($daily[$day])) {
                    $daily[$day] += (int) $row->total;
                    if ($row->status === 'completed') {
                        $done[$day] += (int) $row->total;
                    }
                }
            }
            $charts[] = $this->chart($all ? 'Swap activity' : 'My acknowledgement activity', 'line', array_keys($daily), [['label' => __('Total'), 'data' => array_values($daily)], ['label' => __('Completed'), 'data' => array_values($done)]]);
            $charts[] = $this->chart('Status distribution', 'doughnut', array_map(fn ($s) => __($s), CardSwap::STATUSES), [['data' => $summary['statuses'], 'backgroundColor' => ['#94a3b8', '#1689B9', '#d59a20', '#bd5555', '#16B78C']]]);
            if ($all) {
                $byStaff = (clone $base)->selectRaw('created_by, COUNT(*) as total')->groupBy('created_by')->orderByDesc('total')->limit(20)->get();
                $names = User::whereIn('id', $byStaff->pluck('created_by'))->pluck('name', 'id');
                $charts[] = $this->chart('Swaps by staff', 'bar', $byStaff->map(fn ($r) => $names[$r->created_by] ?? __('Staff'))->all(), [['label' => __('Acknowledgements'), 'data' => $byStaff->pluck('total')->map(fn ($n) => (int) $n)->all()]]);
                $reasons = (clone $base)->selectRaw('reason, COUNT(*) as total')->whereNotNull('reason')->groupBy('reason')->pluck('total', 'reason');
                $charts[] = $this->chart('Replacement reasons', 'bar', array_map(fn ($r) => __($r), ['lost', 'damaged', 'faulty', 'other']), [['label' => __('Acknowledgements'), 'data' => array_map(fn ($r) => (int) ($reasons[$r] ?? 0), ['lost', 'damaged', 'faulty', 'other'])]], true);
                $places = (clone $base)->whereNotNull('location_id')->selectRaw('location_id, COUNT(*) as total')->groupBy('location_id')->get();
                $locationNames = Location::where('company_id', $company->id)->pluck('name', 'id');
                if ($places->isNotEmpty()) {
                    $charts[] = $this->chart('Swaps by location', 'bar', $places->map(fn ($r) => $locationNames[$r->location_id] ?? '—')->all(), [['label' => __('Acknowledgements'), 'data' => $places->pluck('total')->map(fn ($n) => (int) $n)->all()]], true);
                }
            }
            $recent = $request->user()->can('swaps.view') ? (clone $base)->when(! $request->user()->can('swaps.view-all'), fn ($q) => $q->where('created_by', $request->user()->id))->with('creator')->latest()->limit(8)->get() : collect();
        }

        $recentCompanies = $platform ? Company::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->latest()->limit(5)->get() : collect();

        return compact('metrics', 'charts', 'recent', 'platform', 'start', 'end', 'recentCompanies');
    }
}
