<?php

namespace App\Modules\Language\Gateways;

/**
 * Any other translation provider implements this and gets bound in
 * AppServiceProvider in place of MyMemoryTranslator — nothing else in the
 * multilingual-content feature changes. docs/modules/30-multilingual-content-plan.md
 * Phase 5.
 */
interface TranslationGatewayContract
{
    /**
     * Translate $text from $sourceLocale to $targetLocale. Throws on any
     * failure (network error, non-2xx, unparseable/empty response) — callers
     * are queued jobs that catch everything themselves, the same
     * "gateway throws, the job catches" split AiCheckerContract already uses.
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): string;
}
