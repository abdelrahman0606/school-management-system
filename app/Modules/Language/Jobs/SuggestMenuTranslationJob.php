<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Language;
use App\Modules\Website\Models\Menu;
use App\Modules\Website\Models\MenuItem;
use App\Modules\Website\Services\MenuService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translation" for a Menu (docs/modules/30-multilingual-content-plan.md
 * Phase 5) — builds the target locale's menu tree by translating the
 * default language's item labels, keeping type/page_id/url/dynamic_route/
 * icon/target/order identical.
 *
 * Unlike Pages (append-only, always safe to re-run), a Menu save is a full
 * tree REPLACE (see MenuService::replaceItems()'s own docblock). Running
 * this against a locale that already has items would silently destroy
 * whatever the admin already built/translated by hand, so it only ever
 * proceeds when the target locale currently has ZERO items — the same
 * "untranslated" condition the admin editor's own language-tab badge
 * already uses (MenuController::edit()'s localesWithItems).
 */
class SuggestMenuTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    public function __construct(private readonly int $schoolId, private readonly string $locale) {}

    public function handle(TranslationGatewayContract $gateway, MenuService $menus): void
    {
        $source = Language::defaultCode();
        if ($this->locale === $source) {
            return; // nothing to translate into itself
        }

        $sourceMenu = Menu::forSchool($this->schoolId)->where('locale', $source)
            ->with('items.children')->first();
        if (! $sourceMenu || $sourceMenu->items->isEmpty()) {
            return; // nothing to translate from yet
        }

        $targetMenu = Menu::forSchool($this->schoolId)->firstOrCreate(
            ['school_id' => $this->schoolId, 'locale' => $this->locale],
            ['name' => "Main menu ({$this->locale})"],
        );

        // Cheap advisory check first — avoids burning MyMemory API calls
        // (translateItems() below is a real network call per label)
        // translating a whole tree that's just going to be discarded in the
        // common case where the target locale already has items. This
        // alone does NOT close the race (two requests could both pass it
        // before either writes) — the AUTHORITATIVE check is inside
        // replaceItemsIfEmpty() itself, against a locked row, so it stays
        // correct even if this job and the plain "Copy from default
        // language" action both fire for the same just-created locale at
        // once. See that method's own docblock.
        if ($targetMenu->allItems()->exists()) {
            return;
        }

        $menus->replaceItemsIfEmpty($targetMenu, $this->translateItems($sourceMenu->items, $source, $gateway));
    }

    /**
     * $items is deliberately untyped beyond `iterable` rather than a
     * strictly-generic'd Collection<int, MenuItem>: Menu::items()'s and
     * MenuItem::children()'s own HasMany return-type PHPDocs are already a
     * known/baselined generics mismatch in this codebase (see
     * phpstan-baseline.neon's Menu.php/MenuItem.php entries), which widens
     * what callers see back to a bare Model — a strict Collection<..,
     * MenuItem> parameter here could never actually be satisfied by them. A
     * plain foreach + instanceof guard (which phpstan narrows natively)
     * sidesteps that entirely instead of fighting it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function translateItems(iterable $items, string $source, TranslationGatewayContract $gateway): array
    {
        $out = [];

        foreach ($items as $item) {
            if (! $item instanceof MenuItem) {
                continue;
            }

            $row = [
                'label' => $this->translateField($item->label, $source, $gateway),
                'type' => $item->type,
                'page_id' => $item->page_id,
                'url' => $item->url,
                'dynamic_route' => $item->dynamic_route,
                'icon' => $item->icon,
                'target' => $item->target,
            ];

            if ($item->relationLoaded('children') && $item->children->isNotEmpty()) {
                $row['children'] = $this->translateItems($item->children, $source, $gateway);
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Same swallow-and-continue as SuggestPageTranslationJob/BlockTranslator
     * — one item's label failing (a MyMemory rate limit) must not abort the
     * rest of the tree; it just leaves that one label in the source
     * language, easy for the reviewing admin to spot and finish by hand.
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
