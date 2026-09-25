<?php

namespace App\Http\Requests;

use App\Enums\BodyColor;
use App\Enums\Currency;
use App\Support\Color;
use Illuminate\Validation\Rule;

/**
 * One account's own currency and colours.
 *
 * The colour rules are the admin form's (see ColorRequest) — a button that
 * cannot carry a readable label is as broken for one person as for everyone —
 * but every field is optional, and null clears the choice so the account
 * follows the app again.
 */
class PreferencesRequest extends ColorRequest
{
    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'nullable', Rule::enum(Currency::class)],
            'button_color' => ['sometimes', 'nullable', 'string', 'regex:'.Color::HEX_PATTERN, $this->labelIsReadable()],
            'body_color' => ['sometimes', 'nullable', 'string', Rule::enum(BodyColor::class)],
        ];
    }
}
