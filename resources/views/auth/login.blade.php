@extends('layouts.app')
@section('title', __('Sign In'))
@section('content')
<div class="login-layout"><section class="login-story"><span class="eyebrow">iYawe</span><h1>{{ __('Every acknowledgement. A clearer connection.') }}</h1><p>{{ __('Digital acknowledgements, made simple.') }}</p><div class="story-art" aria-hidden="true"><div class="digital-card"><span>iYawe</span><div class="card-lines"></div><strong>✓</strong></div></div><p class="story-note">{{ __('One secure workspace for your organisation.') }}</p></section>
<section class="login-panel"><div class="login-card"><span class="eyebrow">{{ __('WELCOME BACK') }}</span><h2>{{ __('Sign In') }}</h2><p class="muted">{{ __('Enter your details to access your workspace.') }}</p><form method="POST" action="{{ route('login.store') }}">@csrf
<label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old('email') }}" required>
<label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required>
@if(config('iyawe.password_reset_enabled'))<div class="form-link"><a href="{{ route('password.request') }}">{{ __('Forgot Password?') }}</a></div>@endif<button class="primary full">{{ __('Sign In') }} →</button></form><p class="helper">{{ __('Accounts are provided by your administrator.') }}</p>@if($platformPreferences['support_email'] ?? null)<p class="helper"><a href="mailto:{{ $platformPreferences['support_email'] }}">{{ __('Contact support') }}</a></p>@endif</div></section></div>
@endsection
