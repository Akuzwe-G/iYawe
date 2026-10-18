@extends('layouts.app')
@section('title',__('New Card Swap'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $activeCompany->name }}</span><h1>{{ __('New Card Swap') }}</h1><p>{{ __('A guided acknowledgement in five steps.') }}</p></div></div>
<section class="panel form-panel"><h2>{{ __('Before you begin') }}</h2><p>{{ __('Have the commuter identification, both card details and recorded balances ready. The customer signs twice and the verifying staff member signs once.') }}</p><p>{{ __('iYawe records acknowledgements only. It does not transfer balances or deactivate cards.') }}</p>
<ol class="workflow-intro">@foreach(['Commuter information','Card information','Consent and signature','Verification and processing','Receipt confirmation'] as $title)<li>{{ __($title) }}</li>@endforeach</ol>
<form method="POST" action="{{ route('swaps.store') }}" data-once>@csrf<input type="hidden" name="submission_key" value="{{ $submissionKey }}"><button class="primary">{{ __('Start acknowledgement') }} →</button></form></section>
@endsection
