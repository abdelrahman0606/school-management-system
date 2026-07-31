<?php

namespace Tests\Unit\Language;

use App\Modules\Language\Gateways\MyMemoryTranslator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Mirrors tests/Unit/LMS/AnthropicAiCheckerTest.php's pattern exactly — a
 * fake HTTP response, never a real network call. docs/modules/
 * 30-multilingual-content-plan.md Phase 5.
 */
class MyMemoryTranslatorTest extends TestCase
{
    public function test_parses_a_successful_response(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'গ্রীন ভ্যালি মডেল স্কুল'],
                'responseStatus' => 200,
            ], 200),
        ]);

        $result = (new MyMemoryTranslator)->translate('Green Valley Model School', 'en', 'bn');

        $this->assertSame('গ্রীন ভ্যালি মডেল স্কুল', $result);
    }

    public function test_blank_text_returns_blank_without_a_network_call(): void
    {
        Http::fake();

        $this->assertSame('', (new MyMemoryTranslator)->translate('   ', 'en', 'bn'));
        Http::assertNothingSent();
    }

    public function test_same_source_and_target_locale_returns_the_text_unchanged_without_a_network_call(): void
    {
        Http::fake();

        $this->assertSame('Hello', (new MyMemoryTranslator)->translate('Hello', 'en', 'en'));
        Http::assertNothingSent();
    }

    public function test_throws_on_a_non_successful_response(): void
    {
        Http::fake(['api.mymemory.translated.net/*' => Http::response('rate limited', 429)]);

        $this->expectException(RuntimeException::class);

        (new MyMemoryTranslator)->translate('Hello', 'en', 'bn');
    }

    public function test_throws_on_an_unparseable_response(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response(['responseData' => []], 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new MyMemoryTranslator)->translate('Hello', 'en', 'bn');
    }

    public function test_throws_when_a_quota_warning_rides_inside_an_otherwise_200_response(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY'],
                'responseStatus' => 200,
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new MyMemoryTranslator)->translate('Hello', 'en', 'bn');
    }
}
