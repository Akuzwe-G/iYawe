@php
    $selectedType = old('identification_type', $swap->identification_type ?? ($swap->national_id ? '' : 'rwandan_national_id'));
    $selectedType = is_string($selectedType) ? $selectedType : '';
    $specifications = \App\Support\CommuterIdentifiers::specifications();
    foreach ($specifications as &$specification) { $specification['message'] = __($specification['message']); }
    unset($specification);
    $selectedSpecification = $specifications[$selectedType] ?? null;
@endphp
<div class="field" data-identification>
<label for="identification_type">{{ __('Identification type') }} <span aria-hidden="true">*</span></label>
<select id="identification_type" name="identification_type" required data-required-message="{{ __('Choose an identification type.') }}" aria-describedby="identification_type_error">
<option value="">{{ __('Choose an identification type.') }}</option>
@foreach(\App\Support\CommuterIdentifiers::TYPES as $type=>$label)<option value="{{ $type }}" @selected($selectedType===$type)>{{ __($label) }}</option>@endforeach
</select><p id="identification_type_error" class="field-error" data-client-error aria-live="polite" @if(!$errors->has('identification_type')) hidden @endif>{{ $errors->first('identification_type') }}</p>
<label for="national_id">{{ __('Identification number') }} <span aria-hidden="true">*</span></label>
<input id="national_id" name="national_id" type="text" value="{{ old('national_id',$swap->national_id) }}" required inputmode="{{ $selectedType==='rwandan_national_id'?'numeric':'text' }}" maxlength="80" @if($selectedSpecification) minlength="{{ $selectedSpecification['min'] }}" pattern="{{ $selectedSpecification['pattern'] }}" @endif autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="national_id_hint national_id_error" data-required-message="{{ __('Enter the identification number.') }}">
<p id="national_id_hint" class="helper">{{ $selectedSpecification['message']??__('Choose the identification type before continuing.') }}</p>
<p id="national_id_error" class="field-error" data-client-error aria-live="polite" @if(!$errors->has('national_id')) hidden @endif>{{ $errors->first('national_id') }}</p>
<p class="helper">{{ __('Format checks only; identity is not verified against NIDA.') }}</p>
<script type="application/json" data-identifier-specifications>@json($specifications)</script>
</div>
