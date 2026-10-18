@extends('layouts.app')
@section('title', $staff->exists ? __('Edit staff') : __('Add staff'))
@section('content')
<div class="page-heading"><div><a class="back-link" href="{{ route('staff.index') }}">← {{ __('Users') }}</a><h1>{{ $staff->exists ? __('Edit staff') : __('Add staff') }}</h1></div></div>
<section class="panel form-panel"><form method="POST" action="{{ $staff->exists ? route('staff.update', $staff) : route('staff.store') }}">@csrf @if($staff->exists)@method('PUT')@endif
<label for="name">{{ __('Company display name') }}</label><input id="name" name="name" required value="{{ old('name', $staff->pivot?->display_name ?? $staff->name) }}" maxlength="180">
@if(!$staff->exists)<label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off"><label for="password">{{ __('Initial password') }}</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"><p class="helper">{{ __('At least 12 characters, including letters and numbers. Existing account passwords are never changed.') }}</p>
@else<p class="muted">{{ $staff->email }}</p><p class="helper">{{ __('Account email and password are managed by the account owner in Settings.') }}</p>@endif
<label for="active">{{ __('Membership status') }}</label><select id="active" name="active"><option value="1" @selected(old('active', $staff->exists ? $staff->pivot->active : true))>{{ __('active') }}</option><option value="0" @selected(!old('active', $staff->exists ? $staff->pivot->active : true))>{{ __('inactive') }}</option></select>
<fieldset><legend>{{ __('Company roles') }}</legend>@foreach($roles as $role)<label class="check-row"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $staff->exists ? $staff->roles->modelKeys() : [])))>{{ __($role->name) }}</label>@endforeach</fieldset>
<div class="form-actions"><button class="primary">{{ __('Save staff') }}</button><a href="{{ route('staff.index') }}">{{ __('Cancel') }}</a></div></form></section>
@endsection
