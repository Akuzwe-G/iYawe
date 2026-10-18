@php($brand=$company->document_branding??[])
<fieldset class="branding-fields"><legend>{{ __('Document Branding') }}</legend><p class="helper">{{ __('These settings apply to documents and reports. The iYawe interface stays unchanged.') }}</p>
<div class="form-grid">
@foreach(['display_name'=>'Company display name','legal_name'=>'Official / legal company name','email'=>'Document contact email','phone'=>'Document contact phone'] as $key=>$label)<div><label for="brand_{{ $key }}">{{ __($label) }}</label><input id="brand_{{ $key }}" name="brand_{{ $key }}" type="{{ $key==='email'?'email':'text' }}" value="{{ old('brand_'.$key,$brand[$key]??($key==='display_name'?$company->name:null)) }}" maxlength="{{ $key==='email'?254:($key==='phone'?40:240) }}"></div>@endforeach
</div>
<label for="brand_address">{{ __('Company address') }}</label><textarea id="brand_address" name="brand_address" maxlength="1000">{{ old('brand_address',$brand['address']??'') }}</textarea>
<label for="brand_report_title">{{ __('Default report heading / title') }}</label><input id="brand_report_title" name="brand_report_title" value="{{ old('brand_report_title',$brand['report_title']??'') }}" maxlength="240">
<label for="brand_footer">{{ __('Document footer text') }}</label><textarea id="brand_footer" name="brand_footer" maxlength="1500">{{ old('brand_footer',$brand['footer']??'') }}</textarea>
<label for="brand_logo">{{ __('Company logo (optional)') }}</label><input id="brand_logo" name="brand_logo" type="file" accept="image/png,image/jpeg,image/webp"><p class="helper">{{ __('PNG, JPEG or WebP, up to 2 MB. SVG is not accepted.') }}</p>
@if($brand['logo_data_uri']??null)<img class="branding-logo" src="{{ $brand['logo_data_uri'] }}" alt="{{ __('Current company logo') }}"><label class="check-row"><input type="checkbox" name="brand_remove_logo" value="1">{{ __('Remove logo') }}</label>@endif
<p class="helper">{{ __('Formal documents use the legal name, falling back to the display name. Completed acknowledgements retain their original branding.') }}</p>
</fieldset>
