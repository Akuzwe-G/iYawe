@if($swap->usesWholeRwfBalances())
<div class="form-grid" data-card-balances data-format-message="{{ __(\App\Support\CardBalances::MESSAGE) }}" data-equal-message="{{ __(\App\Support\CardBalances::EQUAL_MESSAGE) }}">
@foreach(['old_balance'=>'Existing card balance (RWF)','new_balance'=>'Replacement card balance (RWF)'] as $name=>$label)
<div><x-swap-input :name="$name" :label="$label" :value="preg_replace('/\\.00$/', '', $swap->$name ?? '')" :required="true" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="80" autocomplete="off" spellcheck="false" data-card-balance :aria-describedby="$name.'_hint '.$name.'_client_error'"/>
<p class="helper" id="{{ $name }}_hint">{{ __(\App\Support\CardBalances::MESSAGE) }}</p><p class="field-error" id="{{ $name }}_client_error" aria-live="polite" hidden></p></div>
@endforeach
</div>
@else
<div class="form-grid">
<x-swap-input name="old_balance" label="Existing card balance (RWF)" type="number" :value="$swap->old_balance" :required="true" min="0" max="999999999999.99" step="0.01" inputmode="decimal"/>
<x-swap-input name="new_balance" label="Replacement card balance (RWF)" type="number" :value="$swap->new_balance" :required="true" min="0" max="999999999999.99" step="0.01" inputmode="decimal"/>
</div>
@endif
