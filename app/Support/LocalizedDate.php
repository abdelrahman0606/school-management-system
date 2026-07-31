<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Native (offline) date localization — month/day NAMES via Carbon's own
 * bundled locale data (Carbon ships every Symfony Translation locale file,
 * including bn/bn_BD/bn_IN, under vendor/nesbot/carbon — this is a one-time
 * composer install, never a runtime network call or translation API), plus a
 * hardcoded native-digit swap for the locales that need one.
 *
 * Carbon's translatedFormat() only localizes the letters ('F' => 'জুলাই'),
 * never the digits — 'j F Y' still comes out "31 জুলাই 2026" without the
 * second step below. There is no bundled data for that (digit systems aren't
 * part of Carbon/Symfony's locale files), so it's a plain hardcoded map —
 * exactly the "hardcode each digit" approach requested. Extend NATIVE_DIGITS
 * with another locale's map if a future language needs its own numerals
 * (e.g. Arabic-Indic ٠-٩ for `ar`) — everything else about this class stays
 * the same.
 *
 * Usage:
 *   LocalizedDate::format($announcement->publish_at)              // 'j F Y' in the current app locale
 *   LocalizedDate::format($announcement->publish_at, 'd M Y')
 *   LocalizedDate::format($school->established, 'Y', 'bn')        // explicit locale override
 */
class LocalizedDate
{
    /**
     * Native digit swap per BASE locale code (i.e. 'bn', not 'bn_BD') — see
     * baseLocale(). Only locales that actually use a different digit system
     * need an entry; anything not listed (English and any future
     * Latin-script locale) is returned from format() untouched, with no
     * strtr() call at all.
     *
     * @var array<string, array<string, string>>
     */
    private const NATIVE_DIGITS = [
        'bn' => [
            '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
        ],
    ];

    /**
     * Format a date with the given locale's translated month/day names and
     * native digits. Null-safe (mirrors optional($date)->format(...), which
     * every call site this replaces already used) — returns null for a null
     * $date instead of formatting "now".
     *
     * $format uses Carbon/PHP's ordinary format() letters (j, d, F, M, Y,
     * l, ...) — translatedFormat() supports the exact same letters, it just
     * additionally translates the ones that have a textual name (F, M, l, D).
     */
    public static function format(?DateTimeInterface $date, string $format = 'j F Y', ?string $locale = null): ?string
    {
        if (! $date) {
            return null;
        }

        $locale ??= app()->getLocale();
        $carbon = $date instanceof Carbon ? $date->clone() : Carbon::instance($date);
        $formatted = $carbon->locale($locale)->translatedFormat($format);

        $digitMap = self::NATIVE_DIGITS[self::baseLocale($locale)] ?? null;

        return $digitMap ? strtr($formatted, $digitMap) : $formatted;
    }

    /**
     * Swap ASCII digits 0-9 to $locale's native digits in an arbitrary
     * string (e.g. a plain "2026" year already extracted as a string/int,
     * not a DateTimeInterface). No-op for a locale with no NATIVE_DIGITS
     * entry.
     */
    public static function digits(string|int $value, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $digitMap = self::NATIVE_DIGITS[self::baseLocale($locale)] ?? null;

        return $digitMap ? strtr((string) $value, $digitMap) : (string) $value;
    }

    /** 'bn_BD' / 'bn-BD' / 'BN' -> 'bn', so a regional variant still matches the base map above. */
    private static function baseLocale(string $locale): string
    {
        return strtolower(substr(str_replace('-', '_', $locale), 0, 2));
    }
}
