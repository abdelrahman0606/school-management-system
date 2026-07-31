<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Language;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Services\BlockTranslator;
use App\Modules\Website\Services\PageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translation" for a Page (docs/modules/30-multilingual-content-plan.md
 * Phase 5) — translates the default language's LATEST layout (title/meta +
 * every text field inside its blocks, via BlockTranslator) into a brand new
 * DRAFT PageLayout revision for the target locale. Always safe to run
 * regardless of whether the target locale already has content: PageLayout
 * is append-only (PageService::saveLayout() never updates a row in place,
 * see its own docblock), so this only ever ADDS a new unpublished revision
 * to History for the admin to review — it can never clobber an existing
 * hand-translated draft or a published page, unlike menus (a full-tree
 * replace) which is why SuggestMenuTranslationJob has to gate on "target
 * locale has zero items" instead.
 */
class SuggestPageTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    public function __construct(private readonly int $pageId, private readonly string $locale) {}

    public function handle(TranslationGatewayContract $gateway, BlockTranslator $blocks, PageService $pages): void
    {
        $page = Page::find($this->pageId);
        if (! $page) {
            return;
        }

        $source = Language::defaultCode();
        if ($this->locale === $source) {
            return; // nothing to translate into itself
        }

        $sourceLayout = $page->layoutsForLocale($source)->first();
        if (! $sourceLayout) {
            return; // nothing to translate from yet
        }

        $pages->saveLayout(
            $page,
            $blocks->translateLayout(is_array($sourceLayout->layout_json) ? $sourceLayout->layout_json : [], $source, $this->locale),
            null,
            $this->locale,
            [
                'title' => $this->translateField($sourceLayout->title, $source, $gateway),
                'meta_title' => $this->translateField($sourceLayout->meta_title, $source, $gateway),
                'meta_desc' => $this->translateField($sourceLayout->meta_desc, $source, $gateway),
                // og_image is a stored file path, never translatable.
                'og_image' => $sourceLayout->og_image,
            ],
        );
    }

    /**
     * Same swallow-and-continue as BlockTranslator's own translateField() —
     * one field failing (a MyMemory rate limit) must not abort the whole
     * suggestion; it just leaves that one field in the source language,
     * easy for the reviewing admin to spot and finish by hand.
     */
    private function translateField(?string $value, string $source, TranslationGatewayContract $gateway): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        try {
            return $gateway->translate($value, $source, $this->locale);
        } catch (Throwable) {
            return $value;
        }
    }
}
