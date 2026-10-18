@extends('layouts.app')
@section('title',__('Dashboard'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $platform?__('PLATFORM OVERVIEW'):($activeCompany?->name??__('YOUR OVERVIEW')) }}</span><h1>{{ __('Welcome, :name',['name'=>auth()->user()->name]) }}</h1><p>{{ __('Activity from :from to :to',['from'=>$start->format('d M Y'),'to'=>$end->format('d M Y')]) }}</p></div><span class="badge">{{ __('Secure workspace') }}</span></div>
@foreach($invitations as $invitation)<section class="panel invitation"><div><h2>{{ __('Company invitation') }}</h2><p>{{ $invitation->name }}</p></div><form method="POST" action="{{ route('invitations.accept',$invitation) }}">@csrf<button class="primary">{{ __('Accept membership') }}</button></form></section>@endforeach
<form method="GET" class="panel date-filter" x-data="{period:@js(request('period','30'))}">
<div><label for="period">{{ __('Reporting period') }}</label><select id="period" name="period" x-model="period">@foreach(['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','year'=>'This year','custom'=>'Custom range'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('period','30')===(string)$value)>{{ __($label) }}</option>@endforeach</select></div>
<div x-show="period==='custom'"><label for="from">{{ __('From') }}</label><input id="from" name="from" type="date" value="{{ request('from',$start->format('Y-m-d')) }}" :required="period==='custom'"></div><div x-show="period==='custom'"><label for="to">{{ __('To') }}</label><input id="to" name="to" type="date" value="{{ request('to',$end->format('Y-m-d')) }}" :required="period==='custom'"></div><button class="primary">{{ __('Apply period') }}</button>
</form>
@if($metrics)<div class="metrics analytics-metrics">@foreach($metrics as [$label,$value])<article class="metric"><span>{{ __($label) }}</span><strong>{{ $value }}</strong></article>@endforeach</div>@endif
<div class="quick-actions">
@if($platform)<a class="primary" href="{{ route('companies.create') }}">{{ __('Create company') }}</a><a class="secondary" href="{{ route('companies.index') }}">{{ __('Companies') }}</a>
@else
@can('create',\App\Models\CardSwap::class)<a class="primary" href="{{ route('swaps.create') }}">{{ __('New Card Swap') }} →</a>@endcan
@if($activeCompany)@can('swaps.view')<a class="secondary" href="{{ route('swaps.index') }}">{{ __('Search Records') }}</a>@can('swaps.update-pending')<a class="secondary" href="{{ route('swaps.index',['status'=>'pending_processing']) }}">{{ __('Continue Pending Swap') }}</a>@endcan @endcan
@can('staff.manage')<a class="secondary" href="{{ route('staff.index') }}">{{ __('Manage users') }}</a>@endcan
@can('roles.manage')<a class="secondary" href="{{ route('roles.index') }}">{{ __('Roles & Permissions') }}</a>@endcan
@endif
@endif
</div>
<div class="chart-grid">
@foreach($charts as $chart)
<section class="panel chart-card"><h2>{{ __($chart['title']) }}</h2>
@if(array_sum(array_map(fn($set)=>array_sum($set['data']),$chart['datasets']))>0)
<div class="chart-container"><p data-chart-loading class="helper" role="status">{{ __('Loading chart...') }}</p><canvas data-chart="chart_{{ $loop->index }}" role="img" aria-label="{{ __($chart['title']) }}"></canvas></div><script type="application/json" id="chart_{{ $loop->index }}">@json($chart)</script>
<details class="chart-data"><summary>{{ __('View chart data') }}</summary><div class="table-scroll"><table><thead><tr><th>{{ __('Category') }}</th>@foreach($chart['datasets'] as $dataset)<th>{{ $dataset['label']??__('Count') }}</th>@endforeach</tr></thead><tbody>@foreach($chart['labels'] as $index=>$label)<tr><td>{{ $label }}</td>@foreach($chart['datasets'] as $dataset)<td>{{ $dataset['data'][$index]??0 }}</td>@endforeach</tr>@endforeach</tbody></table></div></details>
@else<div class="chart-empty"><span aria-hidden="true">▥</span><h3>{{ __('No activity in this period') }}</h3><p>{{ __('Charts will appear when actual records are available.') }}</p></div>@endif
</section>@endforeach
</div>
@if($platform)
<section class="panel"><div class="section-heading"><h2>{{ __('Recent company registrations') }}</h2><a href="{{ route('companies.index') }}">{{ __('View all') }}</a></div><div class="table-scroll"><table><thead><tr><th>{{ __('Company') }}</th><th>{{ __('Status') }}</th><th>{{ __('Registered') }}</th></tr></thead><tbody>@forelse($recentCompanies->filter(fn($c)=>$c->created_at->between($start->startOfDay(),$end->endOfDay())) as $company)<tr><td><a href="{{ route('companies.edit',$company) }}">{{ $company->name }}</a></td><td>{{ __($company->status) }}</td><td>{{ $company->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="3">{{ __('No registrations in this period.') }}</td></tr>@endforelse</tbody></table></div></section>
@elseif($activeCompany)@can('swaps.view')
<section class="panel"><h2>{{ __('Recent acknowledgements') }}</h2>@include('swaps.table',['records'=>$recent])</section>
@endcan @else<section class="panel"><h2>{{ __('Select your workspace') }}</h2><p>{{ __('Choose an active company above. If none is available, contact your administrator.') }}</p></section>@endif
@endsection
