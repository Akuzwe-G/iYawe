@extends('layouts.customer')
@section('content')
<section class="panel completion-card"><span class="badge">{{ $swap->reference }}</span><h1>{{ $captured?__('Signature recorded'):__('Customer signing mode') }}</h1><p>{{ $captured?__('Thank you. Please return the phone to the staff member.'):__('Please complete the signature or return the phone to staff.') }}</p>@if(!$captured)<a class="primary" href="{{ route('swaps.edit',$swap) }}">{{ __('Continue signing') }}</a>@endif</section>
@endsection
