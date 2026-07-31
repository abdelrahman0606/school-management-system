<?php

namespace Tests\Unit\Support;

use App\Support\LocalizedDate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Native (offline) date localization -- Carbon's own bundled locale data for
 * month/day names (vendor/nesbot/carbon, no network call) plus a hardcoded
 * digit map for locales that use non-ASCII numerals. No third-party
 * translation API involved anywhere in this class.
 */
class LocalizedDateTest extends TestCase
{
    public function test_formats_in_english_with_ordinary_digits_by_default(): void
    {
        $date = Carbon::create(2026, 7, 31);

        $this->assertSame('31 July 2026', LocalizedDate::format($date, 'd F Y', 'en'));
    }

    public function test_formats_in_bengali_with_translated_month_and_native_digits(): void
    {
        $date = Carbon::create(2026, 7, 31);

        // "31 July 2026" -> Bengali month name + native (০-৯) digits.
        $this->assertSame('৩১ জুলাই ২০২৬', LocalizedDate::format($date, 'd F Y', 'bn'));
    }

    public function test_defaults_to_the_current_app_locale_when_none_given(): void
    {
        $date = Carbon::create(2026, 7, 31);
        app()->setLocale('bn');

        $this->assertSame('৩১ জুলাই ২০২৬', LocalizedDate::format($date, 'd F Y'));
    }

    public function test_a_regional_locale_variant_still_matches_the_base_digit_map(): void
    {
        $date = Carbon::create(2026, 7, 31);

        $this->assertSame('৩১ জুলাই ২০২৬', LocalizedDate::format($date, 'd F Y', 'bn_BD'));
    }

    public function test_null_date_returns_null_matching_optional_format_behaviour(): void
    {
        $this->assertNull(LocalizedDate::format(null, 'd F Y', 'bn'));
    }

    public function test_does_not_mutate_the_original_carbon_instances_locale(): void
    {
        $date = Carbon::create(2026, 7, 31)->locale('en');

        LocalizedDate::format($date, 'd F Y', 'bn');

        $this->assertSame('en', $date->locale);
    }

    public function test_digits_swaps_an_arbitrary_string_or_int_to_native_numerals(): void
    {
        $this->assertSame('২০২৬', LocalizedDate::digits(2026, 'bn'));
        $this->assertSame('২০২৬', LocalizedDate::digits('2026', 'bn'));
        $this->assertSame('2026', LocalizedDate::digits('2026', 'en'));
    }
}
