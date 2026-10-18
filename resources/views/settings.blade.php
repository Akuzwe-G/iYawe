@extends('layouts.app')
@section('title', __('Settings'))
@section('content')
<div class="page-heading"><div><h1>{{ __('Settings') }}</h1><p>{{ __('Manage your profile and workspace preferences.') }}</p></div></div>
<section class="panel form-panel"><h2>{{ __('Your account') }}</h2><form method="POST" action="{{ route('settings.update') }}">@csrf @method('PUT')
<label for="name">{{ __('Full name') }}</label><input id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required>
<label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" required>
<label for="locale">{{ __('Language') }}</label><select id="locale" name="locale"><option value="en" @selected(auth()->user()->locale === 'en')>English</option><option value="rw" @selected(auth()->user()->locale === 'rw')>Kinyarwanda</option></select>
<label for="current_password">{{ __('Current password') }}</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
<label for="password">{{ __('New password (optional)') }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12">
<label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
<button class="primary">{{ __('Save settings') }}</button></form></section>
@if($activeCompany)@can('settings.manage')
<section class="panel form-panel"><h2>{{ __('Company settings') }}</h2><form method="POST" action="{{ route('settings.company') }}">@csrf @method('PUT')
<label for="company_name">{{ __('Company name') }}</label><input id="company_name" name="name" value="{{ $activeCompany->name }}" required>
<label for="company_email">{{ __('Email address') }}</label><input id="company_email" name="email" type="email" value="{{ $activeCompany->email }}">
<label for="company_phone">{{ __('Phone') }}</label><input id="company_phone" name="phone" value="{{ $activeCompany->phone }}"><button class="primary">{{ __('Save company settings') }}</button></form></section>
@endcan @endif

@if(auth()->user()->is_system_admin)
<section class="panel form-panel"><h2>{{ __('Platform settings') }}</h2>
<form method="POST" action="{{ route('settings.platform') }}">@csrf @method('PUT')
<label for="default_locale">{{ __('Default guest language') }}</label><select id="default_locale" name="default_locale"><option value="en" @selected($platformPreferences['default_locale'] === 'en')>English</option><option value="rw" @selected($platformPreferences['default_locale'] === 'rw')>Kinyarwanda</option></select>
<label for="support_email">{{ __('Support email') }}</label><input id="support_email" name="support_email" type="email" value="{{ $platformPreferences['support_email'] }}">
<button class="primary">{{ __('Save platform settings') }}</button></form></section>
@endif
@endsection
