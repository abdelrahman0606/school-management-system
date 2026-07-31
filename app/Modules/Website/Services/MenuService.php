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
     * A menu save is a full-tree REPLACE (delete everything, re-insert the
     * submitted tree). Two things make the delete side of that dangerous if
     * done naively:
     *
     * 1. Concurrency: two of these running against the SAME menu (e.g. a
     *    slow request tempting a double-click on "Save Menu", nothing in
     *    the client stops that on its own) can interleave. Locking the menu
     *    row first serializes any concurrent replaceItems() calls against
     *    it — the second one simply waits for the first to fully commit
     *    before starting its own delete+insert.
     *
     * 2. `parent_id` is a SELF-referencing foreign key with
     *    `cascadeOnDelete()` (menu_items migration) — a dropdown's children
     *    are auto-deleted when their parent row is. Deleting via
     *    `$menu->allItems()->delete()` (allItems() carries
     *    `orderBy('sort_order')`) runs `DELETE ... WHERE menu_id=? ORDER BY
     *    sort_order` — and a child's own sort_order is independent, 0-based
     *    per parent, so it routinely TIES with unrelated top-level items'
     *    sort_order values. When that ordering causes MySQL to delete a
     *    parent row before its own statement's cursor has reached that
     *    parent's children, the cascade removes those children out from
     *    under the delete statement's own in-flight row scan — while
     *    whichever rows the cascade never raced end up statement-skipped by
     *    the same mechanism, deterministically, the same way every time
     *    (this is exactly what happened: a menu save consistently deleting
     *    some items, silently leaving others behind untouched, then the
     *    fresh insert landing on top — a genuine tree corruption bug, not a
     *    one-off). Deleting children (no children of their own — one level
     *    of nesting only) before parents, with NO ordering involved either
     *    time, means the cascade is always a no-op by the time a parent row
     *    is deleted, and there's no tie to race in the first place.
     *
     * @param  array<int, array<string, mixed>>  $items  One level of nesting via
     *                                                   an optional 'children' key on dropdown-type items.
     */
    public function replaceItems(Menu $menu, array $items): Menu
    {
        return DB::transaction(function () use ($menu, $items): Menu {
            Menu::whereKey($menu->id)->lockForUpdate()->first();

            MenuItem::where('menu_id', $menu->id)->whereNotNull('parent_id')->delete();
            MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->delete();

            foreach ($items as $index => $item) {
                $this->createItem($menu, $item, null, $index);
            }

            return $menu->fresh('items.children');
        });
    }

    /**
     * Used by SuggestMenuTranslationJob — a "seed an untranslated locale"
     * entry point that must never clobber a locale the admin already
     * built/translated by hand, since a Menu save is a full-tree REPLACE.
     * Returns null (and writes nothing) if the target already had items by
     * the time the lock below was acquired.
     *
     * The zero-items check happens INSIDE this transaction, against a
     * LOCKED row — not as a check the caller does first and this method
     * writes second. Two requests targeting the same empty locale close
     * together would otherwise both pass a "currently zero items" check
     * taken before either had written anything, and both proceed to insert
     * — replaceItems() deletes-then-inserts, so this doesn't even need both
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
