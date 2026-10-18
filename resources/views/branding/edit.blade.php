@extends('layouts.app')
@section('title',__('Document Branding'))
@section('content')
<div class="page-heading"><div><h1>{{ __('Document Branding') }}</h1><p>{{ $company->name }}</p></div></div>
<section class="panel form-panel"><form method="POST" action="{{ route('branding.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
@include('branding.fields')<div class="form-actions"><button class="primary">{{ __('Save document branding') }}</button></div></form></section>
@endsection
