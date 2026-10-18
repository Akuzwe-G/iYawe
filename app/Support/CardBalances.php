<?php

namespace App\Support;

use App\Models\CardSwap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;

class CardBalances
{
    public const MESSAGE = 'Enter a whole-number balance from 100 to 49,999 RWF.';

    public const EQUAL_MESSAGE = 'The existing and replacement card balances must be equal.';

    public static function forRequest(Request $request): bool
    {
        return (int) CardSwap::where('company_id', $request->attributes->get('company')?->id)
            ->whereKey($request->route('swap'))->value('balance_validation_version') === 1;
    }

    public static function rules(bool $draft = false): array
    {
        return ['bail', $draft ? 'nullable' : 'required', function (string $attribute, mixed $value, Closure $fail): void {
            if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A[0-9]+\z/D', (string) $value) || (int) $value < 100 || (int) $value > 49999) {
                $fail(__(self::MESSAGE));
            }
        }];
    }

    public static function messages(): array
    {
        return ['old_balance.required' => __(self::MESSAGE), 'new_balance.required' => __(self::MESSAGE)];
    }

    public static function validate(array $values): void
    {
        $validator = Validator::make($values, ['old_balance' => self::rules(), 'new_balance' => self::rules()], self::messages());
        $validator->after(function (LaravelValidator $validator) use ($values): void {
            if (! $validator->errors()->has('old_balance') && ! $validator->errors()->has('new_balance') && (int) $values['old_balance'] !== (int) $values['new_balance']) {
                $validator->errors()->add('new_balance', __(self::EQUAL_MESSAGE));
            }
        });
        $validator->validate();
    }

    public static function validateStored(CardSwap $swap): void
    {
        if (! $swap->usesWholeRwfBalances()) {
            return;
        }
        $values = $swap->only(['old_balance', 'new_balance']);
        foreach ($values as &$value) {
            // Existing decimal storage represents whole RWF amounts with a .00 suffix.
            if (is_string($value) && preg_match('/\A[0-9]+\.00\z/D', $value)) {
                $value = substr($value, 0, -3);
            }
        }
        unset($value);
        self::validate($values);
    }
}
