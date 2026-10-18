@extends('layouts.app')
@section('title',__('Reports'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $activeCompany->name }}</span><h1>{{ __('Reports') }}</h1><p>{{ __('Company activity for the selected filters.') }}</p></div>@can('reports.export')<a class="primary" href="{{ route('reports.export',request()->query()) }}">{{ __('Export CSV summary') }}</a>@endcan</div>
<section class="panel document-preview">@include('documents.header')@if($branding['report_title'])<h2>{{ $branding['report_title'] }}</h2>@endif @include('documents.footer')</section><div class="quick-actions"><a class="secondary" href="{{ route('reports.print',request()->query()) }}">{{ __('Print / Preview report') }}</a>@can('reports.export')<a class="secondary" href="{{ route('reports.pdf',request()->query()) }}">{{ __('Download report PDF') }}</a>@endcan</div><section class="panel">@include('swaps.filters',['report'=>true])</section>
<div class="metrics analytics-metrics">@foreach(['total'=>'Total acknowledgements','completed'=>'Completed','pending'=>'Pending','unsuccessful'=>'Unsuccessful','completion_rate'=>'Completion rate'] as $key=>$label)<article class="metric"><span>{{ __($label) }}</span><strong>{{ $summary[$key] }}{{ $key==='completion_rate'?'%':'' }}</strong></article>@endforeach</div>
@if($records)<section class="panel">@include('swaps.table'){{ $records->links() }}</section>@endif
@endsection
