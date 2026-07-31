<?php

namespace App\Modules\Language\Gateways;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls the free MyMemory Translation API (api.mymemory.translated.net) —
 * no API key, no billing account, works from any hosting. Any failure here
 * (timeout, non-2xx, unparseable output, an API-level quota/error message
 * riding inside an otherwise-200 response) is allowed to propagate, exactly
 * like AnthropicAiChecker — the queued Suggest*TranslationJob callers are
 * the layer responsible for catching everything and never rethrowing.
 */
class MyMemoryTranslator implements TranslationGatewayContract
{
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        if ($sourceLocale === $targetLocale) {
            return $trimmed;
        }

        // MyMemory's own per-request query length limit (~500 bytes) —
        // truncated defensively before the request is sent. Every result
        // this gateway produces is a draft a human reviews before it's ever
        // saved for real, so a truncated-but-present suggestion is still
        // useful rather than needing to be rejected outright.
        $maxChars = (int) config('language.mymemory_max_chars');
        $truncated = mb_substr($trimmed, 0, $maxChars);

        $response = Http::timeout((int) config('language.mymemory_timeout_seconds'))
            ->get(config('language.mymemory_api_base'), array_filter([
                'q' => $truncated,
                'langpair' => "{$sourceLocale}|{$targetLocale}",
                'de' => config('language.mymemory_contact_email'),
            ]));

        if (! $response->successful()) {
            throw new RuntimeException("MyMemory API error ({$response->status()}): {$response->body()}");
        }

        $translated = $response->json('responseData.translatedText');
        $status = (int) $response->json('responseStatus');

        if (! is_string($translated) || $translated === '') {
            throw new RuntimeException('MyMemory API returned an unparseable response: '.$response->body());
        }

        // MyMemory bakes quota/rate-limit errors into a 200 response as
        // English prose inside translatedText rather than a non-2xx status
        // — responseStatus (and this literal marker it uses) is the real
        // signal, not HTTP success.
        if ($status !== 200 || str_contains($translated, 'MYMEMORY WARNING')) {
            throw new RuntimeException("MyMemory translation failed (status {$status}): {$translated}");
        }

        return $translated;
    }
}
