@extends('layouts.app')
@section('title', __('Roles & Permissions'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $activeCompany->name }}</span><h1>{{ __('Roles & Permissions') }}</h1><p>{{ __('Control what your team can do.') }}</p></div><a class="primary" href="{{ route('roles.create') }}">{{ __('Create role') }}</a></div>
<div class="role-grid">@foreach($roles as $role)<section class="panel"><div class="section-heading"><h2>{{ __($role->name) }}</h2>@if(in_array($role->name, ['Company Administrator', 'Field Staff']))<span class="badge">{{ __('Reserved') }}</span>@else<a href="{{ route('roles.edit', $role) }}">{{ __('Edit') }}</a>@endif</div><ul class="permission-list">@foreach($role->permissions as $permission)<li>✓ {{ __($permission->name) }}</li>@endforeach</ul>@if($role->permissions->isEmpty())<p class="muted">{{ __('No permissions assigned.') }}</p>@endif</section>@endforeach</div>
@endsection
