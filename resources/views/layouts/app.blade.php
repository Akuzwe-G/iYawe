<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#1747A6"><title>@yield('title', __('Dashboard')) · iYawe</title><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">@fonts('montserrat')@vite(['resources/css/app.css', 'resources/js/app.js'])@include('pwa.head')@include('pwa.messages')</head>
<body><a class="skip-link" href="#main-content">{{ __('Skip to content') }}</a><div class="app-shell" x-data="{ navigationOpen: false, mobile: window.innerWidth <= 760 }" @resize.window.debounce.150ms="mobile=window.innerWidth <= 760; if(!mobile)navigationOpen=false" @keydown.escape.window="if(navigationOpen){navigationOpen=false;$refs.menu?.focus()}">
@auth
<aside class="sidebar" :class="{ 'is-open': navigationOpen }" :inert="mobile &amp;&amp; !navigationOpen" id="navigation">
<a class="wordmark" href="{{ route('dashboard') }}"><span class="brand-mark">i</span>iYawe</a><p class="sidebar-caption">{{ __('Your workspace') }}</p>
<nav aria-label="{{ __('Main navigation') }}">
<a class="{{ request()->routeIs('dashboard') ? 'selected' : '' }}" href="{{ route('dashboard') }}">▦ &nbsp; {{ __('Dashboard') }}</a>
@if(auth()->user()->is_system_admin)<a class="{{ request()->routeIs('companies.*') ? 'selected' : '' }}" href="{{ route('companies.index') }}">▤ &nbsp; {{ __('Companies') }}</a>@endif
@if($activeCompany ?? null)
@can('swaps.create')<a class="{{ request()->routeIs('swaps.create','swaps.edit') ? 'selected' : '' }}" href="{{ route('swaps.create') }}">↔ &nbsp; {{ __('Card Swap') }}</a>@endcan
@can('swaps.view')<a href="{{ route('drafts.index') }}" class="{{ request()->routeIs('drafts.*')?'selected':' ' }}">{{ __('My Drafts') }}</a><a class="{{ request()->routeIs('swaps.index','swaps.show') ? 'selected' : '' }}" href="{{ route('swaps.index') }}">▤ &nbsp; {{ __('Records') }}</a>@endcan
@can('reports.view')<a class="{{ request()->routeIs('reports.*') ? 'selected' : '' }}" href="{{ route('reports.index') }}">▥ &nbsp; {{ __('Reports') }}</a>@endcan
@can('settings.manage')<a class="{{ request()->routeIs('consents.*') ? 'selected' : '' }}" href="{{ route('consents.index') }}">✓ &nbsp; {{ __('Consent versions') }}</a>@endcan

@can('staff.manage')<a class="{{ request()->routeIs('staff.*') ? 'selected' : '' }}" href="{{ route('staff.index') }}">♙ &nbsp; {{ __('Users') }}</a>@endcan
@can('roles.manage')<a class="{{ request()->routeIs('roles.*') ? 'selected' : '' }}" href="{{ route('roles.index') }}">◇ &nbsp; {{ __('Roles & Permissions') }}</a>@endcan
@can('locations.manage')<a class="{{ request()->routeIs('locations.*') ? 'selected' : '' }}" href="{{ route('locations.index') }}">⌖ &nbsp; {{ __('Locations') }}</a>@endcan
@endif
<a class="{{ request()->routeIs('settings*') ? 'selected' : '' }}" href="{{ route('settings') }}">⚙ &nbsp; {{ __('Settings') }}</a>
</nav><div class="sidebar-bottom">{{ __('Digital acknowledgements, made simple.') }}</div></aside>
@endauth
<div class="@auth workspace @else guest-workspace @endauth">
<header class="topbar">
@auth
<button class="mobile-menu secondary" type="button" x-ref="menu" @click="navigationOpen = !navigationOpen; if(navigationOpen)$nextTick(() => document.querySelector('#navigation a')?.focus())" :aria-expanded="navigationOpen" aria-controls="navigation">{{ __('Menu') }}</button>
<div class="company-context">
@if(($availableCompanies ?? collect())->isNotEmpty())
<form action="{{ route('company.switch') }}" method="POST" class="inline-form">@csrf<label class="sr-only" for="company_id">{{ __('Active company') }}</label><select id="company_id" name="company_id" required><option value="" disabled @selected(!($activeCompany ?? null))>{{ __('Select a company') }}</option>@foreach($availableCompanies as $option)<option value="{{ $option->id }}" @selected($activeCompany?->id === $option->id)>{{ $option->name }}</option>@endforeach</select><button class="secondary compact">{{ __('Switch') }}</button></form>
@else<span class="muted">{{ auth()->user()->is_system_admin ? __('Platform administration') : __('No active company') }}</span>@endif
</div>
@else<a class="wordmark" href="{{ route('login') }}"><span class="brand-mark">i</span>iYawe</a>@endauth
<div class="topbar-actions"><form action="{{ route('locale') }}" method="POST" class="language-switch">@csrf<button name="locale" value="en" class="{{ app()->getLocale() === 'en' ? 'current' : '' }}" aria-label="English">EN</button><span>/</span><button name="locale" value="rw" class="{{ app()->getLocale() === 'rw' ? 'current' : '' }}" aria-label="Kinyarwanda">KINY</button></form>
@auth<a class="profile-link" href="{{ route('settings') }}">{{ __('Profile') }}</a><form action="{{ route('logout') }}" method="POST">@csrf<button class="secondary compact">{{ __('Logout') }}</button></form>@endauth</div></header>
<div class="mobile-nav-shade" x-show="navigationOpen" x-cloak @click="navigationOpen=false;$refs.menu?.focus()" aria-hidden="true"></div><main id="main-content" class="@auth page-content @else guest-content @endauth">
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>{{ __('Please check the form.') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@include('pwa.banners')@yield('content')</main><footer>© {{ date('Y') }} iYawe <span>{{ __('Digital acknowledgements, made simple.') }}</span></footer></div></div><div data-toast role="status" aria-live="polite" hidden></div></body></html>
