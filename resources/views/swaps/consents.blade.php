@extends('layouts.app')
@section('title',__('Consent versions'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $activeCompany->name }}</span><h1>{{ __('Consent versions') }}</h1><p>{{ __('Publish approved wording for this company. Existing signed declarations are never changed.') }}</p></div></div>
<section class="panel form-panel"><h2>{{ __('Publish approved declaration') }}</h2><p>{{ __('The supplied English Tap&Go wording names AC Mobility / Tap&Go. Enable it only where it applies. Kinyarwanda legal text must be supplied and approved before use.') }}</p>
<form method="POST" action="{{ route('consents.publish') }}" x-data="{language:'en',templates:@js(config('consent')),version:@js(config('consent.en.version')),declaration:@js(config('consent.en.declaration')),receipt:@js(config('consent.en.receipt'))}" data-once>@csrf
<label for="language">{{ __('Consent language') }}</label><select id="language" name="language" x-model="language" @change="version=templates[language].version;declaration=templates[language].declaration;receipt=templates[language].receipt"><option value="en">English</option><option value="rw">Kinyarwanda</option></select>
<label for="version">{{ __('Version') }}</label><input id="version" name="version" x-model="version" required maxlength="80">
<label for="declaration">{{ __('Complete approved declaration') }}</label><textarea id="declaration" name="declaration" x-model="declaration" required rows="18" maxlength="20000"></textarea>
<label for="receipt">{{ __('Approved receipt confirmation') }}</label><textarea id="receipt" name="receipt" x-model="receipt" required rows="4" maxlength="4000"></textarea>
<label class="check-row"><input type="checkbox" name="approved" value="1" required>{{ __('I confirm this exact wording is approved and applicable to this company.') }}</label><div class="form-actions"><button class="primary">{{ __('Publish approved version') }}</button></div></form></section>
<section class="panel"><h2>{{ __('Version history') }}</h2><div class="table-scroll"><table><thead><tr><th>{{ __('Language') }}</th><th>{{ __('Version') }}</th><th>{{ __('Approved at') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>@forelse($versions as $version)<tr><td>{{ strtoupper($version->language) }}</td><td>{{ $version->version }}</td><td>{{ $version->approved_at->format('d M Y H:i') }}</td><td>{{ $version->active?__('Active'):__('Historical') }}</td></tr>@empty<tr><td colspan="4">{{ __('No approved versions published.') }}</td></tr>@endforelse</tbody></table></div></section>
@endsection
