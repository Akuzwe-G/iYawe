@extends(($customerMode??false)?'layouts.customer':'layouts.app')
@section('title',__('Card Swap'))
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $swap->reference }}</span><h1>{{ __('Card Swap') }}</h1><p>{{ __('Step :step of 5',['step'=>$swap->step]) }} · {{ __($swap->status) }}</p></div>@if(!$customerMode)<a data-save-exit class="secondary" href="{{ route('swaps.show',$swap) }}">{{ __('Exit record') }}</a>@endif</div>
<ol class="wizard-progress" aria-label="{{ __('Acknowledgement progress') }}">@foreach(['Commuter','Cards','Consent','Verification','Receipt'] as $label)<li class="{{ $loop->iteration===$swap->step?'current':($loop->iteration<$swap->step?'done':'') }}" @if($loop->iteration===$swap->step) aria-current="step" @endif><span>{{ $loop->iteration }}</span><small>{{ __($label) }}</small></li>@endforeach</ol>
@if(!$customerMode && in_array($swap->step,[3,5]))<section class="panel customer-entry"><h2>{{ __('Customer signing mode') }}</h2><p>{{ __('Hide staff navigation before handing the phone to the commuter. Your password is required to return.') }}</p><form method="POST" action="{{ route('customer.enter',$swap) }}">@csrf<label for="customer_language">{{ __('Customer language') }}</label><select id="customer_language" name="language">@foreach(['en'=>'English','rw'=>'Kinyarwanda'] as $code=>$label)<option value="{{ $code }}" @selected(($swap->consent_language??app()->getLocale())===$code)>{{ $label }}</option>@endforeach</select><button class="primary">{{ __('Hand phone to customer') }}</button></form></section>@endif
<section class="panel wizard-panel">
<form method="POST" action="{{ route('swaps.advance',[$swap,$swap->step]) }}" autocomplete="off" data-once data-workflow data-draft-url="{{ route('drafts.save',$swap) }}" data-autosave="{{ ($swap->status==='draft' && in_array($swap->step,[1,2]))?'true':'false' }}">@csrf<input type="hidden" name="step" value="{{ $swap->step }}"><input type="hidden" name="revision" value="{{ $swap->revision }}">
<div class="save-state" data-save-state role="status" aria-live="polite">{{ __('Last saved') }}: {{ $swap->updated_at->format('H:i') }}</div><div data-workflow-errors class="notice error" role="alert" hidden></div>
@if($swap->status==='draft' && in_array($swap->step,[1,2]))<div class="draft-tools"><button type="button" class="secondary" data-save-draft>{{ __('Save Draft') }}</button><span class="helper">{{ __('Drafts are saved to the server. Signatures are saved only when explicitly submitted.') }}</span></div>@endif
@if($swap->step===1)
<h2>{{ __('Commuter information') }}</h2><div class="form-grid">
<x-swap-input name="swap_date" label="Date" type="date" :value="$swap->swap_date?->format('Y-m-d') ?? today()->format('Y-m-d')" :required="true" :max="today()->format('Y-m-d')"/>
<x-swap-input name="commuter_name" label="Commuter full name" :value="$swap->commuter_name" :required="true" maxlength="180" autocomplete="off"/>
<x-swap-input name="phone" label="Phone number" type="tel" inputmode="tel" :value="$swap->phone" :required="true" maxlength="25"/>
<x-swap-input name="email" label="Email address (optional)" type="email" :value="$swap->email" maxlength="254"/>
@include('swaps.identification')
<div><label for="location_id">{{ __('Location') }} <span aria-hidden="true">*</span></label><select id="location_id" name="location_id" required data-required-message="{{ __('Choose a location to continue.') }}" aria-describedby="location_id_error location_id_hint"><option value="">{{ __('Choose a location to continue.') }}</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id',$swap->location_id)==$location->id)>{{ $location->name }}</option>@endforeach</select><p id="location_id_error" class="field-error" aria-live="polite" @if(!$errors->has('location_id')) hidden @endif>{{ $errors->first('location_id') }}</p><p id="location_id_hint" class="helper" @if($locations->isNotEmpty()) hidden @endif>{{ __('No active locations are available. Ask your company administrator to add one before continuing.') }}</p></div>
</div>
@elseif($swap->step===2)
<h2>{{ __('Card information') }}</h2><p class="helper">{{ __('The hexadecimal format is provisional. Format checks do not confirm that a card exists in Hamwe.') }}</p><p class="helper">{{ __('Enter the balances recorded during the physical card swap process.') }}</p><div class="form-grid">
<div><x-swap-input name="old_card" label="Existing card number" inputmode="text" autocapitalize="characters" :value="$swap->old_card" :required="true" minlength="8" maxlength="80" pattern="[0-9A-Fa-f]{8}" autocomplete="off" spellcheck="false" data-tapgo-card data-format-message="{{ __(\App\Support\CommuterIdentifiers::CARD_MESSAGE) }}" data-different-message="{{ __('The existing and replacement card numbers must be different.') }}" aria-describedby="old_card_hint old_card_client_error"/><p class="helper" id="old_card_hint">{{ __(\App\Support\CommuterIdentifiers::CARD_MESSAGE) }}</p><p class="field-error" id="old_card_client_error" data-client-error aria-live="polite" hidden></p></div>
<div><x-swap-input name="new_card" label="Replacement card number" inputmode="text" autocapitalize="characters" :value="$swap->new_card" :required="true" minlength="8" maxlength="80" pattern="[0-9A-Fa-f]{8}" autocomplete="off" spellcheck="false" data-tapgo-card data-format-message="{{ __(\App\Support\CommuterIdentifiers::CARD_MESSAGE) }}" data-different-message="{{ __('The existing and replacement card numbers must be different.') }}" aria-describedby="new_card_hint new_card_client_error"/><p class="helper" id="new_card_hint">{{ __(\App\Support\CommuterIdentifiers::CARD_MESSAGE) }}</p><p class="field-error" id="new_card_client_error" data-client-error aria-live="polite" hidden></p></div>
</div>
@include('swaps.balances')<div x-data="{reason: @js(old('reason',$swap->reason ?? 'lost'))}"><label for="reason">{{ __('Reason for replacement') }}</label><select id="reason" name="reason" x-model="reason">@foreach(['lost','damaged','faulty','other'] as $reason)<option value="{{ $reason }}" @selected(old('reason',$swap->reason??'lost')===$reason)>{{ __($reason) }}</option>@endforeach</select>@error('reason')<p class="field-error">{{ $message }}</p>@enderror<div x-show="reason==='other'"><label for="reason_other">{{ __('Other reason') }}</label><textarea id="reason_other" name="reason_other" :required="reason==='other'" maxlength="1000">{{ old('reason_other',$swap->reason_other) }}</textarea>@error('reason_other')<p class="field-error">{{ $message }}</p>@enderror</div></div>
@elseif($swap->step===3)
<h2>{{ __('Consent and authorisation') }}</h2>
@if($consents->isEmpty())
<div class="notice error">{{ __('No approved consent is available for this company. Ask an administrator to publish the applicable declaration before signing.') }}</div>
@else
<div x-data="{selected: @js((string)old('consent_version_id',($consents->firstWhere('language',app()->getLocale())??$consents->first())->id))}">
<label for="consent_version_id">{{ __('Customer consent language') }}</label><select id="consent_version_id" name="consent_version_id" x-model="selected">@foreach($consents as $consent)<option value="{{ $consent->id }}">{{ $consent->language==='en'?'English':'Kinyarwanda' }} · {{ $consent->version }}</option>@endforeach</select>@error('consent_version_id')<p class="field-error">{{ $message }}</p>@enderror
@foreach($consents as $consent)<div x-show="selected==='{{ $consent->id }}'" class="consent-copy" lang="{{ $consent->language }}"><h3>{{ __('TAP&GO COMMUTER CARD SWAP CONSENT AND AUTHORIZATION FORM', [], $consent->language) }}</h3><p>{{ $consent->declaration }}</p></div>@endforeach
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
<label for="outcome">{{ __('Physical swap outcome') }}</label><select id="outcome" name="outcome" x-model="outcome"><option value="pending">{{ __('Not yet completed — pending') }}</option><option value="completed">{{ __('Yes — physical swap completed') }}</option><option value="unsuccessful">{{ __('No — processing unsuccessful') }}</option></select>@error('outcome')<p class="field-error">{{ $message }}</p>@enderror
<label class="check-row" x-show="outcome==='completed'"><input type="checkbox" name="physical_confirmation" value="1" :required="outcome==='completed'">{{ __('I confirm the physical card swap has actually been completed.') }}</label>
<label for="remarks">{{ __('Remarks') }}</label><textarea id="remarks" name="remarks" :required="outcome==='unsuccessful'" maxlength="2000">{{ old('remarks') }}</textarea>@error('remarks')<p class="field-error">{{ $message }}</p>@enderror
</div>@include('swaps.signature',['signatureLabel'=>'Staff verification signature'])
@elseif($swap->step===5)
<h2>{{ __('Commuter confirmation after swap') }}</h2><div class="notice success">{{ __('Physical completion recorded. The customer must now confirm receipt.') }}</div>
<div class="consent-copy" lang="{{ $swap->consent_language }}"><p>{{ $swap->consentVersion->receipt }}</p></div>
<label class="check-row"><input type="checkbox" name="receipt_acknowledged" value="1" required>{{ __('I confirm receipt of the replacement card as stated above.') }}</label>
@include('swaps.signature',['signatureLabel'=>'Customer receipt signature'])
@endif
@if($swap->step!==3 || $consents->isNotEmpty())<div class="form-actions"><button class="primary">{{ $swap->step===5?__('Complete acknowledgement'):__('Save and continue') }} →</button></div>@endif
</form>
@if(!$customerMode && $swap->status==='draft' && $swap->step>1)<form method="POST" action="{{ route('swaps.back',$swap) }}" class="back-form">@csrf<input type="hidden" name="revision" value="{{ $swap->revision }}"><input type="hidden" name="step" value="{{ $swap->step===2?1:2 }}"><button class="secondary">{{ __('Back to previous step') }}</button></form>@endif
</section>
@endsection
