@extends('layouts.app')
@section('title',__('Records'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $activeCompany->name }}</span><h1>{{ __('Records') }}</h1><p>{{ __('Exact searches protect sensitive customer information. Card numbers are masked in the list.') }}</p></div>@can('create',\App\Models\CardSwap::class)<a class="primary" href="{{ route('swaps.create') }}">{{ __('New Card Swap') }}</a>@endcan</div>
<section class="panel">@include('swaps.filters')</section><section class="panel">@include('swaps.table'){{ $records->links() }}</section>
@endsection
