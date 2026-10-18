@extends('layouts.app')
@section('title', $role->exists ? __('Edit role') : __('Create role'))
@section('content')
<div class="page-heading"><div><a class="back-link" href="{{ route('roles.index') }}">← {{ __('Roles & Permissions') }}</a><h1>{{ $role->exists ? __('Edit role') : __('Create role') }}</h1></div></div>
<section class="panel form-panel"><form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">@csrf @if($role->exists)@method('PUT')@endif
<label for="name">{{ __('Role name') }}</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $role->name) }}">
<fieldset><legend>{{ __('Approved company permissions') }}</legend><p class="helper">{{ __('Acknowledgement, record and PDF permissions prepare access for Milestone 2.') }}</p>@foreach($catalogue as $permission)<label class="check-row"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $role->exists ? $role->permissions->pluck('name')->all() : [])))>{{ __($permission) }}</label>@endforeach</fieldset>
<div class="form-actions"><button class="primary">{{ __('Save role') }}</button><a href="{{ route('roles.index') }}">{{ __('Cancel') }}</a></div></form></section>
@endsection
