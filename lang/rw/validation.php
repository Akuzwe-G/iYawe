<?php

return [
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
