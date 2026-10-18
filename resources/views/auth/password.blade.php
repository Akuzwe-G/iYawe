@extends('layouts.app')
@section('title', __('Reset password'))
@section('content')
<section class="panel auth-panel"><h1>{{ __('Reset password') }}</h1><p class="muted">{{ __('Use your account email address.') }}</p>
<form method="POST" action="{{ route($reset ? 'password.update' : 'password.email') }}">@csrf
@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
<label for="email">{{ __('Email address') }}</label><input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" autocomplete="email" required>
@if($reset)<label for="password">{{ __('New password') }}</label><input id="password" type="password" name="password" autocomplete="new-password" minlength="12" required><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>@endif
<button class="primary full">{{ $reset ? __('Reset password') : __('Send reset link') }}</button></form><a class="back-link" href="{{ route('login') }}">{{ __('Back to sign in') }}</a></section>
@endsection
