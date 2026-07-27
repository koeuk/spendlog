<?php

namespace App\Support;

use App\Enums\Locale;

/**
 * Translatable fields are edited as one plain value rather than one box per
 * language: the forms lost their locale tabs, but the columns are still spatie
 * JSON, so something has to sit between "a string arrived" and "a per-locale map
 * is stored". That is this.
 *
 * Everything written through a form now lands under the fallback locale, which
 * is what spatie resolves to when the reader's own language has no translation.
 * Existing translations in other locales are left alone by the writes here —
 * they are simply no longer authored through the app.
 */
final class TranslatableInput
{
    /**
     * The plain string a form (or an older API client, which still sends
     * `field[en]`) meant to submit.
     *
     * An array is reduced to its fallback-locale value so a client posting the
     * old per-locale shape keeps working instead of failing the string rule.
     */
    public static function toString(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $fallback = self::fallback();

        // Fall back to whatever single value is there: a client that only ever
        // sent Khmer should not silently submit an empty name.
        $resolved = $value[$fallback] ?? null;

        if (blank($resolved)) {
            $resolved = collect($value)->first(fn ($item) => filled($item) && is_string($item));
        }

        return $resolved ?? '';
    }

    /**
     * The per-locale map to store. Blank clears the field to {} rather than
     * persisting "" — spatie only falls back when the key is absent.
     *
     * @return array<string, string>
     */
    public static function toTranslations(mixed $value): array
    {
        $value = trim((string) self::toString($value));

        return $value === '' ? [] : [self::fallback() => $value];
    }

    private static function fallback(): string
    {
        $fallback = (string) config('app.fallback_locale');

        return Locale::tryFrom($fallback)?->value ?? Locale::English->value;
    }
}
