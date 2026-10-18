@extends('layouts.app')
@section('title', __('Locations'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $activeCompany->name }}</span><h1>{{ __('Locations') }}</h1><p>{{ __('Organise your service locations.') }}</p></div></div>
<section class="panel"><h2>{{ __('Add location') }}</h2><form class="location-form" method="POST" action="{{ route('locations.store') }}">@csrf
<div><label for="new_name">{{ __('Name') }}</label><input id="new_name" name="name" value="{{ old('name') }}" required maxlength="180"></div><div><label for="new_address">{{ __('Address') }}</label><input id="new_address" name="address" value="{{ old('address') }}" maxlength="255"></div><input type="hidden" name="active" value="1"><button class="primary">{{ __('Add location') }}</button></form></section>
<section class="panel"><h2>{{ __('Service locations') }}</h2>@forelse($locations as $location)<form class="location-form location-row" method="POST" action="{{ route('locations.update', $location) }}">@csrf @method('PUT')
<div><label for="name_{{ $location->id }}">{{ __('Name') }}</label><input id="name_{{ $location->id }}" name="name" value="{{ $location->name }}" required></div>
<div><label for="address_{{ $location->id }}">{{ __('Address') }}</label><input id="address_{{ $location->id }}" name="address" value="{{ $location->address }}"></div>
<div><label for="active_{{ $location->id }}">{{ __('Status') }}</label><select id="active_{{ $location->id }}" name="active"><option value="1" @selected($location->active)>{{ __('active') }}</option><option value="0" @selected(!$location->active)>{{ __('inactive') }}</option></select></div><button class="secondary">{{ __('Save') }}</button></form>@empty<p class="empty-state">{{ __('No locations yet.') }}</p>@endforelse{{ $locations->links() }}</section>
@endsection
