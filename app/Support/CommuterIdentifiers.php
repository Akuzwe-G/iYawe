<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class CommuterIdentifiers
{
    public const TYPES = ['rwandan_national_id' => 'Rwandan National ID', 'passport' => 'Passport', 'foreign_id' => 'Other Foreign Identification'];

    public const CARD_MESSAGE = 'Enter exactly 8 characters using 0–9 or A–F. Lowercase letters are accepted.';

    public static function specifications(): array
    {
        return [
            'rwandan_national_id' => ['min' => 16, 'max' => 16, 'pattern' => '[0-9]{16}', 'prefix' => '[0-9]{1,16}', 'message' => 'Enter exactly 16 digits for the Rwandan National ID.'],
            'passport' => ['min' => 5, 'max' => 20, 'pattern' => '[A-Za-z0-9][A-Za-z0-9\\x2D]{3,18}[A-Za-z0-9]', 'prefix' => '[A-Za-z0-9][A-Za-z0-9\\x2D]{0,19}', 'message' => 'Enter 5–20 letters or digits for the passport, with hyphens only inside the number.'],
            'foreign_id' => ['min' => 5, 'max' => 30, 'pattern' => '[A-Za-z0-9][A-Za-z0-9 \\x2D]{3,28}[A-Za-z0-9]', 'prefix' => '[A-Za-z0-9][A-Za-z0-9 \\x2D]{0,29}', 'message' => 'Enter 5–30 letters or digits for the foreign identification, with spaces or hyphens only inside the number.'],
        ];
    }

    public static function typeRules(bool $draft = false): array
    {
        return [$draft ? 'nullable' : 'required', 'string', Rule::in(array_keys(self::TYPES))];
    }

    public static function numberRules(?string $type, bool $draft = false): array
    {
        $spec = self::specifications()[$type] ?? null;

        return $spec ? ['bail', $draft ? 'nullable' : 'required', 'string', 'max:'.$spec['max'], 'regex:~\\A'.$spec[$draft ? 'prefix' : 'pattern'].'\\z~D'] : [$draft ? 'nullable' : 'required', 'string', 'max:80'];
    }

    public static function cardRules(bool $draft = false): array
    {
        return ['bail', $draft ? 'nullable' : 'required', 'string', 'regex:~\\A[0-9A-F]{'.($draft ? '1,8' : '8').'}\\z~D'];
    }

    public static function normaliseCards(array $data): array
    {
        foreach (['old_card', 'new_card'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = strtoupper($data[$key]);
            }
        }

        return $data;
    }

    public static function messages(?string $type): array
    {
        $number = self::specifications()[$type]['message'] ?? 'Enter the identification number and choose its type.';
        $messages = ['identification_type.required' => __('Choose an identification type.'), 'identification_type.in' => __('Choose an identification type.'), 'identification_type.string' => __('Choose an identification type.'), 'national_id.required' => __('Enter the identification number.'), 'national_id.string' => __($number), 'national_id.max' => __($number), 'national_id.regex' => __($number)];
        foreach (['old_card', 'new_card'] as $field) {
            foreach (['required', 'string', 'regex', 'max'] as $rule) {
                $messages[$field.'.'.$rule] = __(self::CARD_MESSAGE);
            }$messages[$field.'.different'] = __('The existing and replacement card numbers must be different.');
        }

        return $messages;
    }

    public static function label(?string $type): string
    {
        return __(self::TYPES[$type] ?? 'Legacy identification (type unspecified)');
    }
}
