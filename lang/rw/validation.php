<?php

$validation = [
    'required' => ':attribute irakenewe.',
    'email' => ':attribute igomba kuba imeli yemewe.',
    'string' => ':attribute igomba kuba inyandiko.',
    'integer' => ':attribute igomba kuba umubare wuzuye.',
    'boolean' => ':attribute igomba kuba yego cyangwa oya.',
    'array' => ':attribute igomba kuba urutonde.',
    'in' => ':attribute wahisemo ntiyemewe.',
    'not_in' => ':attribute wahisemo ntiyemewe.',
    'exists' => ':attribute wahisemo ntibaho.',
    'unique' => ':attribute isanzwe ikoreshwa.',
    'confirmed' => ":attribute ntihuye n'icyemezo cyayo.",
    'current_password' => 'Ijambobanga si ryo.',
    'prohibited' => ':attribute ntiyemewe hano.',
    'alpha_dash' => ":attribute yemera inyuguti, imibare n'utunyerezo gusa.",
    'min' => ['string' => ':attribute igomba kugira nibura ibimenyetso :min.'],
    'max' => ['string' => ':attribute ntigomba kurenza ibimenyetso :max.'],
    'password' => ['letters' => ':attribute igomba kugira inyuguti.', 'numbers' => ':attribute igomba kugira imibare.'],
    'attributes' => ['email' => 'Imeli', 'name' => 'Izina', 'password' => 'Ijambobanga', 'roles' => 'Inshingano', 'slug' => 'Izina rigufi', 'status' => 'Imimerere', 'current_password' => 'Ijambobanga ukoresha'],
];

$validation['accepted'] = ':attribute igomba kwemerwa.';
$validation['accepted_if'] = ':attribute igomba kwemerwa igihe :other ari :value.';
$validation['required_if'] = ':attribute irakenewe igihe :other ari :value.';
$validation['date'] = ':attribute igomba kuba itariki yemewe.';
$validation['date_format'] = ':attribute igomba gukurikiza imiterere :format.';
$validation['before_or_equal'] = ':attribute igomba kuba mbere cyangwa kuri :date.';
$validation['after_or_equal'] = ':attribute igomba kuba nyuma cyangwa kuri :date.';
$validation['numeric'] = ':attribute igomba kuba umubare.';
$validation['regex'] = 'Imiterere ya :attribute ntiyemewe.';
$validation['different'] = ':attribute na :other bigomba gutandukana.';
$validation['decimal'] = ':attribute igomba kugira imibare :decimal nyuma y\'akadomo.';
$validation['min']['numeric'] = ':attribute ntigomba kuba munsi ya :min.';
$validation['max']['numeric'] = ':attribute ntigomba kurenza :max.';
$validation['attributes'] = array_merge($validation['attributes'], [
    'commuter_name' => "Amazina y'umugenzi", 'consent_name' => "Amazina y'usinya", 'national_id' => 'Irangamuntu', 'phone' => 'Telefoni', 'old_card' => 'Ikarita isanzwe', 'new_card' => 'Ikarita nshya', 'old_balance' => "Amafaranga y'ikarita isanzwe", 'new_balance' => "Amafaranga y'ikarita nshya", 'swap_date' => 'Itariki', 'signature' => 'Umukono', 'reason' => 'Impamvu', 'reason_other' => 'Indi mpamvu', 'remarks' => 'Ibisobanuro', 'from' => 'Itariki itangira', 'to' => 'Itariki irangira', 'receipt_acknowledged' => 'Kwemeza kwakira', 'acknowledged' => 'Kwemera', 'physical_confirmation' => 'Kwemeza gusimbuza', 'consent_version_id' => 'Verisiyo yo kwemera', 'outcome' => 'Ibyavuye mu gutunganya', 'declaration' => 'Inyandiko yo kwemera', 'receipt' => 'Icyemezo cyo kwakira',
]);

$validation['file'] = ':attribute igomba kuba dosiye.';
$validation['mimes'] = ':attribute igomba kuba dosiye ya: :values.';
$validation['mimetypes'] = ':attribute igomba kuba dosiye ya: :values.';
$validation['dimensions'] = "Ingano y'ishusho ya :attribute ntiyemewe.";
$validation['uploaded'] = 'Kohereza :attribute ntibyashobotse.';
$validation['max']['file'] = ':attribute ntigomba kurenza KB :max.';
$validation['attributes'] = array_merge($validation['attributes'], [
    'brand_display_name' => "Izina ry'ikigo rigaragara", 'brand_legal_name' => "Izina ryemewe ry'ikigo", 'brand_address' => "Aderesi y'ikigo", 'brand_email' => 'Imeyili yo ku nyandiko', 'brand_phone' => 'Telefoni yo ku nyandiko', 'brand_report_title' => 'Umutwe wa raporo', 'brand_footer' => 'Amagambo yo hasi ku nyandiko', 'brand_logo' => "Ikirango cy'ikigo", 'brand_remove_logo' => 'Gukuraho ikirango', 'revision' => 'Verisiyo', 'step' => 'Intambwe',
]);

return $validation;
