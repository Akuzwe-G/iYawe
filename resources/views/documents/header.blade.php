<div class="document-header">
@if($branding['logo_data_uri']??null)<img class="document-logo" src="{{ $branding['logo_data_uri'] }}" alt="{{ $branding['display_name'] }}">@endif
<div><h1 class="document-company">{{ $branding['legal_name'] }}</h1>
@if($branding['address']??null)<p class="document-address">{{ $branding['address'] }}</p>@endif
@if(($branding['email']??null)||($branding['phone']??null))<p>{{ implode(' · ',array_filter([$branding['email']??null,$branding['phone']??null])) }}</p>@endif</div></div>
