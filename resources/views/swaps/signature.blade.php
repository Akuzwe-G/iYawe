<div class="signature-box" data-signature>
<div class="section-heading"><div><h3>{{ __($signatureLabel) }}</h3><p class="helper">{{ __('Sign using your finger or stylus. Rotate or expand for more space.') }}</p></div><button class="secondary compact" type="button" data-expand>{{ __('Expand signing area') }}</button></div>
<div class="signature-surface"><canvas aria-label="{{ __($signatureLabel) }}" tabindex="0"></canvas><span class="signing-guide" aria-hidden="true">{{ __('Sign here') }}</span></div>
<input type="hidden" name="signature" value="">
<p class="field-error signature-error" role="alert" hidden>{{ __('Please sign clearly before continuing.') }}</p>
<div class="signature-tools"><button class="secondary compact" type="button" data-clear>{{ __('Clear / Retry signature') }}</button><span class="helper">{{ __('Your signature will be saved securely.') }}</span></div>
</div>
