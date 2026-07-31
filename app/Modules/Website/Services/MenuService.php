<?php

namespace App\Modules\Website\Services;

use App\Modules\Website\Models\Menu;
use App\Modules\Website\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Menu items are always saved as a full tree replace (matches the DevPlan's
 * own `PUT /menus/{id}/items` spec) rather than individual item CRUD — a
 * drag-to-reorder / nest-under-dropdown edit is naturally "here's the whole
 * new tree", not a sequence of single-row operations.
 */
class MenuService
{
    /**
     * @param  array<int, array<string, mixed>>  $items  One level of nesting via
     *                                                   an optional 'children' key on dropdown-type items.
     */
    public function replaceItems(Menu $menu, array $items): Menu
    {
        return DB::transaction(function () use ($menu, $items): Menu {
            $menu->allItems()->delete();

            foreach ($items as $index => $item) {
                $this->createItem($menu, $item, null, $index);
            }

            return $menu->fresh('items.children');
        });
    }

    /**
     * "Copy from default language to start translating" — plain, non-AI
     * copy of one locale's menu tree into another, labels left untranslated
     * (same idea as PageService::copyLayoutToLocale(), just for a
     * full-tree-replace Menu instead of an append-only PageLayout).
     * Structure (type/page_id/url/dynamic_route/icon/target/order) carries
     * over unchanged. $source must already have items.children
     * eager-loaded. Race-safety against an already-non-empty target is
     * replaceItemsIfEmpty()'s job — see its own docblock.
     */
    public function copyLocale(Menu $source, Menu $target): ?Menu
    {
        return $this->replaceItemsIfEmpty($target, $this->treeToArray($source->items));
    }

    /**
     * Shared by copyLocale() (above) and SuggestMenuTranslationJob — both
     * "seed an untranslated locale" entry points that must never clobber a
     * locale the admin already built/translated by hand, since a Menu save
     * is a full-tree REPLACE. Returns null (and writes nothing) if the
     * target already had items by the time the lock below was acquired.
     *
     * The zero-items check happens INSIDE this transaction, against a
     * LOCKED row — not as a check the caller does first and this method
     * writes second. Two requests targeting the same empty locale close
     * together (a double-click, or Copy and Suggest both fired) would
     * otherwise both pass a "currently zero items" check taken before
     * either had written anything, and both proceed to insert —
     * replaceItems() deletes-then-inserts, so this doesn't even need both
     * to run truly concurrently: transaction A inserts and commits,
     * transaction B (whose SELECT ran before A started, or without a lock)
     * still sees its own pre-A snapshot and inserts a second copy on top of
     * A's. Same class of read-check-then-write race CLAUDE.md's own
     * "Gotchas Learned" section documents for Library's own
     * borrow()/available_copies. lockForUpdate() serializes the two
     * attempts so the second transaction's own zero-items check runs AFTER
     * the first has committed, and correctly sees non-zero items and aborts.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function replaceItemsIfEmpty(Menu $target, array $items): ?Menu
    {
        return DB::transaction(function () use ($target, $items): ?Menu {
            $locked = Menu::whereKey($target->id)->lockForUpdate()->first();
            if (! $locked || $locked->allItems()->exists()) {
                return null;
            }

            return $this->replaceItems($target, $items);
        });
    }

    /**
     * Walks a loaded MenuItem tree into the array shape replaceItems()
     * expects. $items is deliberately untyped beyond `iterable` rather than
     * a strictly-generic'd Collection<int, MenuItem> — see
     * SuggestMenuTranslationJob::translateItems()'s own docblock for why
     * (Menu::items()/MenuItem::children()'s HasMany PHPDoc is a known
     * generics quirk in this codebase); same foreach + instanceof guard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function treeToArray(iterable $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if (! $item instanceof MenuItem) {
                continue;
            }

            $row = [
                'label' => $item->label,
                'type' => $item->type,
                'page_id' => $item->page_id,
                'url' => $item->url,
                'dynamic_route' => $item->dynamic_route,
                'icon' => $item->icon,
                'target' => $item->target,
            ];

            if ($item->relationLoaded('children') && $item->children->isNotEmpty()) {
                $row['children'] = $this->treeToArray($item->children);
            }

            $out[] = $row;
        }

        return $out;
    }

    /** @param array<string, mixed> $item */
    private function createItem(Menu $menu, array $item, ?int $parentId, int $index): void
    {
        $children = $item['children'] ?? [];
        unset($item['children']);

        $created = MenuItem::create(array_merge($item, [
            'school_id' => $menu->school_id,
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'sort_order' => $item['sort_order'] ?? $index,
        ]));

        foreach ($children as $childIndex => $child) {
            $this->createItem($menu, $child, $created->id, $childIndex);
        }
    }
}
