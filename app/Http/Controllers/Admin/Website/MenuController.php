<?php

namespace App\Http\Controllers\Admin\Website;

use App\Modules\Language\Models\Language;
use App\Modules\Website\Models\Menu;
use App\Modules\Website\Models\MenuItem;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Services\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Website navigation menu editor (Blade). WordPress-style: drag to reorder and
 * drag under a dropdown to nest (one level, matching the schema). The client
 * serialises the whole tree to JSON and this saves it via MenuService's
 * full-tree replace.
 *
 * docs/modules/30-multilingual-content-plan.md Phase 3 — a school builds one
 * full tree PER LOCALE now, mirroring how Phase 2 made a page's layout
 * per-locale: ?locale= on the GET, a hidden field on the PUT, both resolved
 * via Language::resolve() (falls back to the default language for a
 * missing/invalid/deactivated code, same as SetLocale itself).
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function edit(Request $request): View
    {
        $schoolId = app('current_school_id');
        $locale = Language::resolve($request->query('locale'));
        $menu = $this->menuFor($schoolId, $locale);

        return view('admin.website.menus.edit', [
            'menu' => $menu->load(['items.children.page', 'items.page']),
            'pages' => Page::forSchool($schoolId)->orderBy('title')->get(['id', 'title', 'slug', 'is_homepage']),
            'locale' => $locale,
            'defaultLocale' => Language::defaultCode(),
            'languages' => Language::activeCached(),
            // Which locales already have at least one menu item — drives the
            // language-switcher's "(untranslated)" marker. A locale's menu
            // row can exist (auto-created by menuFor()) yet still be empty,
            // so "has a row" alone isn't a meaningful signal here.
            'localesWithItems' => Menu::forSchool($schoolId)->withCount('items')
                ->get()->filter(fn (Menu $m) => $m->items_count > 0)->pluck('locale')->all(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $schoolId = app('current_school_id');
        $locale = Language::resolve($request->input('locale'));
        $menu = $this->menuFor($schoolId, $locale);

        $raw = json_decode((string) $request->input('items', '[]'), true);
        $items = is_array($raw) ? $this->sanitize($raw, $schoolId) : [];

        $this->menus->replaceItems($menu, $items);

        return redirect()->route('admin.menus.index', ['locale' => $locale])->with('status', __('Menu Saved.'));
    }

    /** Get (or create) this school's menu for one locale — each language owns its own full tree. */
    private function menuFor(int $schoolId, string $locale): Menu
    {
        return Menu::forSchool($schoolId)->firstOrCreate(
            ['school_id' => $schoolId, 'locale' => $locale],
            ['name' => $locale === Language::defaultCode() ? 'Main menu' : "Main menu ({$locale})"],
        );
    }

    /**
     * Whitelist the client tree into what MenuItem accepts. One level of nesting:
     * only a top-level dropdown keeps children.
     *
     * @param  array<int, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function sanitize(array $raw, int $schoolId, int $depth = 0): array
    {
        $validPageIds = Page::forSchool($schoolId)->pluck('id')->all();
        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = in_array($row['type'] ?? '', MenuItem::TYPES, true) ? $row['type'] : 'external';

            $pageId = null;
            if ($type === 'page' && in_array((int) ($row['page_id'] ?? 0), $validPageIds, true)) {
                $pageId = (int) $row['page_id'];
            }

            $item = [
                'label' => mb_substr($label, 0, 150),
                'type' => $type,
                'target' => in_array($row['target'] ?? '', MenuItem::TARGETS, true) ? $row['target'] : '_self',
                'page_id' => $pageId,
                'url' => $type === 'external' ? (trim((string) ($row['url'] ?? '')) ?: null) : null,
                'dynamic_route' => $type === 'dynamic' ? (trim((string) ($row['dynamic_route'] ?? '')) ?: null) : null,
                'icon' => trim((string) ($row['icon'] ?? '')) ?: null,
            ];

            if ($type === 'dropdown' && $depth === 0 && ! empty($row['children']) && is_array($row['children'])) {
                $children = $this->sanitize($row['children'], $schoolId, 1);
                if ($children !== []) {
                    $item['children'] = $children;
                }
            }

            $out[] = $item;
        }

        return $out;
    }
}
