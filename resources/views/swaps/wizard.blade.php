@extends('layouts.app')
@section('title',__('Card Swap'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $swap->reference }}</span><h1>{{ __('Card Swap') }}</h1><p>{{ __('Step :step of 5',['step'=>$swap->step]) }} · {{ __($swap->status) }}</p></div><a class="secondary" href="{{ route('swaps.show',$swap) }}">{{ __('Save and exit') }}</a></div>
<ol class="wizard-progress" aria-label="{{ __('Acknowledgement progress') }}">@foreach(['Commuter','Cards','Consent','Verification','Receipt'] as $label)<li class="{{ $loop->iteration===$swap->step?'current':($loop->iteration<$swap->step?'done':'') }}" @if($loop->iteration===$swap->step) aria-current="step" @endif><span>{{ $loop->iteration }}</span><small>{{ __($label) }}</small></li>@endforeach</ol>
<section class="panel wizard-panel">
<form method="POST" action="{{ route('swaps.advance',[$swap,$swap->step]) }}" data-once>@csrf<input type="hidden" name="revision" value="{{ $swap->revision }}">
@if($swap->step===1)
<h2>{{ __('Commuter information') }}</h2><div class="form-grid">
<x-swap-input name="swap_date" label="Date" type="date" :value="$swap->swap_date?->format('Y-m-d') ?? today()->format('Y-m-d')" :required="true" :max="today()->format('Y-m-d')"/>
<x-swap-input name="commuter_name" label="Commuter full name" :value="$swap->commuter_name" :required="true" maxlength="180" autocomplete="off"/>
<x-swap-input name="phone" label="Phone number" type="tel" :value="$swap->phone" :required="true" maxlength="25"/>
<x-swap-input name="email" label="Email address (optional)" type="email" :value="$swap->email" maxlength="254"/>
<x-swap-input name="national_id" label="National ID / Approved identification number" :value="$swap->national_id" :required="true" maxlength="80" autocomplete="off"/>
@if($locations->isNotEmpty())<div><label for="location_id">{{ __('Location') }}</label><select id="location_id" name="location_id"><option value="">{{ __('Not assigned') }}</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id',$swap->location_id)==$location->id)>{{ $location->name }}</option>@endforeach</select></div>@endif
</div>
@elseif($swap->step===2)
<h2>{{ __('Card information') }}</h2><p class="helper">{{ __('Enter the balances recorded during the physical card swap process.') }}</p><div class="form-grid">
<x-swap-input name="old_card" label="Existing card number" :value="$swap->old_card" :required="true" maxlength="80" autocomplete="off"/>
<x-swap-input name="new_card" label="Replacement card number" :value="$swap->new_card" :required="true" maxlength="80" autocomplete="off"/>
<x-swap-input name="old_balance" label="Existing card balance (RWF)" type="number" :value="$swap->old_balance" :required="true" min="0" max="999999999999.99" step="0.01" inputmode="decimal"/>
<x-swap-input name="new_balance" label="Replacement card balance (RWF)" type="number" :value="$swap->new_balance" :required="true" min="0" max="999999999999.99" step="0.01" inputmode="decimal"/>
</div><div x-data="{reason: @js(old('reason',$swap->reason ?? 'lost'))}"><label for="reason">{{ __('Reason for replacement') }}</label><select id="reason" name="reason" x-model="reason">@foreach(['lost','damaged','faulty','other'] as $reason)<option value="{{ $reason }}" @selected(old('reason',$swap->reason??'lost')===$reason)>{{ __($reason) }}</option>@endforeach</select><div x-show="reason==='other'"><label for="reason_other">{{ __('Other reason') }}</label><textarea id="reason_other" name="reason_other" :required="reason==='other'" maxlength="1000">{{ old('reason_other',$swap->reason_other) }}</textarea></div></div>
@elseif($swap->step===3)
<h2>{{ __('Consent and authorisation') }}</h2>
@if($consents->isEmpty())
<div class="notice error">{{ __('No approved consent is available for this company. Ask an administrator to publish the applicable declaration before signing.') }}</div>
@else
<div x-data="{selected: @js((string)old('consent_version_id',$consents->first()->id))}">
<label for="consent_version_id">{{ __('Customer consent language') }}</label><select id="consent_version_id" name="consent_version_id" x-model="selected">@foreach($consents as $consent)<option value="{{ $consent->id }}">{{ $consent->language==='en'?'English':'Kinyarwanda' }} · {{ $consent->version }}</option>@endforeach</select>
@foreach($consents as $consent)<div x-show="selected==='{{ $consent->id }}'" class="consent-copy" lang="{{ $consent->language }}"><h3>TAP&GO COMMUTER CARD SWAP CONSENT AND AUTHORIZATION FORM</h3><p>{{ $consent->declaration }}</p></div>@endforeach
<p class="helper">{{ __('The consent language is independent of the staff interface language.') }}</p>
<x-swap-input name="consent_name" label="Commuter full name" :value="$swap->commuter_name" :required="true" maxlength="180"/>
<label class="check-row"><input type="checkbox" name="acknowledged" value="1" required>{{ __('I have read and agree to the declaration displayed above.') }}</label>
@include('swaps.signature',['signatureLabel'=>'Customer consent signature'])
</div>
@endif
@elseif($swap->step===4)
<h2>{{ __('Verification and processing') }}</h2><div class="notice success">{{ __('Consent signed') }} · {{ $swap->consent_signed_at->format('d M Y H:i') }} {{ config('app.timezone') }}</div>
<p>{{ __('Verified by') }}: <strong>{{ auth()->user()->name }}</strong></p>
<div x-data="{outcome: @js(old('outcome','pending'))}">
<label for="outcome">{{ __('Physical swap outcome') }}</label><select id="outcome" name="outcome" x-model="outcome"><option value="pending">{{ __('Not yet completed — pending') }}</option><option value="completed">{{ __('Yes — physical swap completed') }}</option><option value="unsuccessful">{{ __('No — processing unsuccessful') }}</option></select>
<label class="check-row" x-show="outcome==='completed'"><input type="checkbox" name="physical_confirmation" value="1" :required="outcome==='completed'">{{ __('I confirm the physical card swap has actually been completed.') }}</label>
<label for="remarks">{{ __('Remarks') }}</label><textarea id="remarks" name="remarks" :required="outcome==='unsuccessful'" maxlength="2000">{{ old('remarks') }}</textarea>
</div>@include('swaps.signature',['signatureLabel'=>'Staff verification signature'])
@elseif($swap->step===5)
<h2>{{ __('Commuter confirmation after swap') }}</h2><div class="notice success">{{ __('Physical completion recorded. The customer must now confirm receipt.') }}</div>
<div class="consent-copy" lang="{{ $swap->consent_language }}"><p>{{ $swap->consentVersion->receipt }}</p></div>
<label class="check-row"><input type="checkbox" name="receipt_acknowledged" value="1" required>{{ __('I confirm receipt of the replacement card as stated above.') }}</label>
@include('swaps.signature',['signatureLabel'=>'Customer receipt signature'])
@endif
@if($swap->step!==3 || $consents->isNotEmpty())<div class="form-actions"><button class="primary">{{ $swap->step===5?__('Complete acknowledgement'):__('Save and continue') }} →</button></div>@endif
</form>
@if($swap->status==='draft' && $swap->step>1)<form method="POST" action="{{ route('swaps.back',$swap) }}" class="back-form">@csrf<input type="hidden" name="revision" value="{{ $swap->revision }}"><input type="hidden" name="step" value="{{ $swap->step===2?1:2 }}"><button class="secondary">{{ __('Back to previous step') }}</button></form>@endif
</section>
@endsection
