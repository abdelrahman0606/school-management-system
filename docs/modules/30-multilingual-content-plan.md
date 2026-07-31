# 30 — Multilingual Public Content (Plan)

## Problem

The public header has a language switcher (`/language/{code}`, Language module, module 26) that
sets `session('app_locale')` and feeds the `translations` table into Laravel's translator via
`SetLocale::injectFlatLines()`. That only affects `__()`-wrapped static UI strings — nav
fallback labels, empty-state placeholders, button text (~2,200 keys extracted by
`translations:scan`).

Everything a school admin actually *authors* is a single free-text DB value with no `locale`
axis at all:

| Content | Table(s) | Confirmed by |
|---|---|---|
| Page/block content (headings, richtext, FAQ, hero copy, ...) | `page_layouts.layout_json` | no `locale` column on the table |
| Page SEO meta | `pages.title/meta_title/meta_desc/og_image` | same |
| Menu labels | `menu_items.label` | `menus`/`menu_items` have no `locale` column |
| Site text | `site_settings.meta_title/meta_description` | same |
| School identity | `schools.name/institution_code_label/institution_code/school_code_label/school_code/technical_branch_code_label/technical_branch_code/address` | same |

Switching language currently cannot change any of the above, because there is nothing to
switch between. This plan adds real per-locale storage for all five, in phases.

## Scope (confirmed)

Pages & blocks, menu item labels, page SEO meta, `SiteSetting` text fields, and School identity
fields (name/institution codes/address — footer + header). Fallback when a locale has no
translation yet: **render the default-language content**, same behavior as the existing `__()`
translator falling back to English. AI-assisted draft translations (button in the admin UI,
reviewed/edited before saving) are in scope — see "AI-assisted draft translation" below for the
provider (revised to the free MyMemory API during Phase 5).

## Architecture

Two different storage shapes, chosen per content shape rather than forcing one mechanism
everywhere:

### 1. Structured/versioned content → duplicate row per locale

Applies to **pages/blocks** and **menus**, because both already use a "whole-tree, versioned or
full-replace" pattern (`page_layouts` is append-only versioned rows; `MenuService::replaceItems`
already does a full-tree replace). Adding a `locale` column and letting each locale own its own
full row/tree is the smallest change that fits the existing pattern exactly — no new table, no
new query layer.

- **`page_layouts`**: add `locale` (string, e.g. `en`/`bn`), `title`, `meta_title`, `meta_desc`,
  `og_image` (nullable — per-locale SEO, previously only on `pages`). `pages` keeps its own
  `title/meta_title/meta_desc/og_image` as the default-locale seed and the admin page-list
  label (list view isn't locale-switchable). `pages.slug` stays a single shared value — URLs
  stay unprefixed (`/about`, not `/en/about`), matching the existing session-based switch.
  Index becomes `(page_id, locale, is_published)`.
- **`PageService`**: `saveLayout()` gains a `string $locale` param. New
  `publishedLayoutFor(Page $page, string $locale): ?PageLayout` — looks up
  `(page_id, locale, is_published=true)` latest row, falls back to the default-locale row if
  none exists. `PageController::show()`/render path calls this instead of the current
  unconditional "latest published" query.
- **`menus`**: add `locale`. `Menu::forSchool($id)->firstOrCreate(['school_id'=>$id,
  'locale'=>$locale])` replaces the current implicit-singleton lookup. `MenuController`/
  `MenuService::replaceItems()` signature is unchanged — it already operates on one `Menu`
  instance, now just a locale-scoped one. Public header's view composer resolves the
  current-locale menu, falling back to the default-locale menu if that school hasn't built one
  yet.
- **Admin UX**: a locale tab strip added to `edit.blade.php` (reusing the existing Content/
  Style/Layout `nav-tabs` pattern already in that file) and to the menu editor — switching tabs
  reloads the editor for that locale (`?locale=xx` on the existing GET, hidden field on the
  existing POST; both routes are already plain form round-trips, not AJAX, so this is a small
  change). A "Copy from default language" action seeds a new locale's first draft from the
  default-locale content instead of starting blank.

### 2. Scalar fields on singleton rows → generic polymorphic translation table

Applies to **School identity fields** and **`SiteSetting` text fields** — both are one row per
school with a handful of independently-translatable scalar columns. Adding a full duplicate row
per locale doesn't fit (a school has exactly one `currency`/`timezone`/`country_code`; only a
few of its columns are language-dependent). Duplicating N nullable columns per locale doesn't
scale as languages are added. Instead:

```
content_translations
  id, school_id, translatable_type, translatable_id, locale, field, value, timestamps
  unique (translatable_type, translatable_id, locale, field)
```

A `HasTranslations` trait (new, `App\Support\Translation\HasTranslations`) on `School` and
`SiteSetting`:

```php
$school->trans('name', 'bn');              // translated value, or null if not set
$school->transOr('name', 'bn');            // translated value, falls back to $school->name
$school->setTranslation('name', 'bn', '...'); // upsert
```

This deliberately mirrors the existing `Translation` model's shape (`locale` + key + `value`)
but keyed by `(record, field)` instead of by English source string, since two schools both have
a `name` field with different values — the English string itself can't be the dictionary key
here the way it is for static UI strings.

Fields in scope: `School::name`, `institution_code_label`, `institution_code`,
`school_code_label`, `school_code`, `technical_branch_code_label`, `technical_branch_code`,
`address`; `SiteSetting::meta_title`, `meta_description`.

- **Admin UX**: a "Translations" section on the School settings page (same page that already
  saves School + SiteSetting fields together per `SchoolController::update()`) — one collapsed
  panel per active language, each with the ~10 fields above. Saved via the same `update()`
  action; `$this->schools->updateSettings()`/`$this->siteSettings->update()` calls stay as they
  are, translations save through a new small `TranslationService::saveMany()` call alongside
  them.
- **Public rendering**: every call site identified — `layout.blade.php` (`$school->name`,
  `$school->address`, the three institution-code pairs, `$s->meta_description`/`meta_title`),
  `header.blade.php` (`$school->name`), `home.blade.php`/`page.blade.php` (meta fields) —
  switches from `$school->name` to `$school->transOr('name')` (locale read from
  `app()->getLocale()` inside the trait, same as `__()` already does implicitly).

## Fallback behavior

Both mechanisms fall back to the default-locale value silently (no "untranslated" badge) —
consistent with how `SetLocale` already falls back to English UI strings when a `translations`
row is missing. `HasTranslations::transOr()` encodes this; `PageService::publishedLayoutFor()`
and the menu lookup encode the layout/menu equivalent.

## AI-assisted draft translation

**Revised during Phase 5** — originally planned to reuse the LMS module's paid Anthropic key
(`schools.lms_ai_api_key`), gated on `module.enabled:lms`. Switched to the free, keyless
MyMemory Translation API instead: translation isn't an LMS-specific feature to begin with, so
tying the "Suggest" button to a paid module a school might never enable was the wrong shape —
every school gets the button with zero setup instead.

`TranslationGatewayContract` (`app/Modules/Language/Gateways/`) + `MyMemoryTranslator` follow
the exact same gateway-throws/job-catches split `AiCheckerContract`/`AnthropicAiChecker`
established for LMS: a synchronous "Suggest" button feels wrong for a network call inside an
admin form, so a **queued** `Suggest{School,Page,Menu}TranslationJob` (`ShouldQueue`, catches
everything, never rethrows) backs each button, with the admin refreshing the page to see the
result once ready — a suggestion is always a draft, never auto-published or auto-saved over
existing content.

Content-shape-specific safety rules, since "suggest" means something different for each of the
three storage shapes this plan uses:
- **School/SiteSetting** (Phase 4's `content_translations`): fills only currently-EMPTY fields
  for the target locale, via `HasTranslations::trans()`'s own "null means untranslated" contract
  — never overwrites a field an admin already translated by hand.
- **Pages** (Phase 2's append-only `PageLayout`): always safe to run, regardless of whether the
  locale already has content — it only ever creates a brand new draft revision
  (`PageService::saveLayout()` never updates in place), so a suggestion can never destroy an
  existing hand-translated draft or the published page. A new `BlockTranslator`
  (`app/Modules/Website/Services/`) walks the block tree with a schema-driven per-block-type text
  field map (not a "translate every string" heuristic), so structural values — urls, colors,
  icon names, alignment — are never sent through translation.
- **Menus** (Phase 3's full-tree-replace `Menu`): the opposite of Pages — `MenuService::replaceItems()`
  destroys and rebuilds the whole tree, so this only ever proceeds when the target locale
  currently has ZERO items (the same "untranslated" signal the admin editor's own language
  switcher already shows), refusing outright otherwise rather than risk clobbering a hand-built
  menu.

Every gateway call (School's per-field loop, a page's dozens of block text fields, a menu's
item labels) is individually try/caught — one field hitting MyMemory's rate limit must not
discard everything translated before it; that one field is just left in the source language,
easy for the reviewing admin to spot and finish by hand.

## Phased implementation

1. ✅ **Foundation**: migrations (`page_layouts.locale`+meta columns, `menus.locale`,
   `content_translations` table), `HasTranslations` trait (`App\Support\Concerns\HasTranslations`,
   backed by `App\Modules\Language\Models\ContentTranslation`), wired onto `School`/`SiteSetting`.
   No UI changes — everything defaulted to the school's default language, byte-for-byte identical
   rendering to before. Merged to `dev`.
2. ✅ **Pages & blocks**: `Page::layoutsForLocale()`, `PageService::saveLayout()`/`publish()`/
   `duplicate()`/`restore()`/`copyLayoutToLocale()` all locale-aware,
   `PageRenderService::publishedLayoutFor()` (default-locale fallback) + `renderPage()` now also
   returns each locale's own meta, admin editor gains a language-tab switcher (marks untranslated
   locales) + "Copy from default language" banner, Title/SEO fields read/write the correct
   locale's own copy while Slug/Status stay shared, History tracks "Latest"/"Current" per locale.
   Public `page.blade.php`/`full.blade.php`/`sidebar.blade.php` prefer the resolved layout's own
   title/meta over the shared `Page` columns. Fixed a real regression this phase would otherwise
   have caused: `WebsitePagesSeeder` created `PageLayout` rows with no `locale`, which the new
   locale-scoped queries would never have matched — every demo page would have silently rendered
   empty. Tests (`tests/Feature/Admin/MultilingualPageContentTest.php`). Merged to `dev`.
3. ✅ **Menus**: `menus.locale` — each language owns its own full tree (`MenuItem`s stay tied to
   `menu_id`, so locale-scoping `Menu` scopes all its items for free). New `Menu::published()`
   (default-locale fallback, same pattern as `PageRenderService::publishedLayoutFor()`) feeds the
   public header composer in `AppServiceProvider`. Admin editor (`MenuController`) gains the same
   language-tab switcher as Phase 2's page builder, marking untranslated languages via a
   `withCount('items')` lookup rather than comparing against the single currently-loaded `Menu`.
   Extracted `Language::resolve()` (requested code → validated against active languages, falling
   back to default) out of `PageController`'s locally-duplicated version so both controllers share
   one implementation. Fixed the same class of regression Phase 2 hit: `WebsitePagesSeeder`'s
   `Menu::firstOrCreate()` had no `locale`, which the new locale-scoped `Menu::published()` query
   would never have matched — the seeded demo nav would have silently disappeared. Tests
   (`tests/Feature/Admin/MultilingualMenuTest.php`). Merged to `dev`.
4. ✅ **School identity + SiteSetting text**: new `TranslationService::saveMany()` bulk-upserts
   one `HasTranslations` host's fields for every active language from a single admin form
   submit (School/SiteSetting are singleton-per-school rows, unlike Pages/Menus' per-locale
   duplicate row, so this reuses Phase 1's `content_translations` mechanism directly rather than
   adding a locale-switcher tab). School settings gains a "Translations" section — one collapsed
   panel per active non-default language (`<details>`, matching the page's own Advanced Theme
   convention), covering `name`/`address`/the three institution code label+value pairs plus
   `SiteSetting::meta_title`/`meta_description`. A blank submitted field clears the override back
   to the fallback rather than storing a literal empty-string translation (the naive form-submit
   failure mode this had to guard against: every untouched language panel starts blank, so saving
   the form once must not silently blank out the whole public site for every active language).
   Public call sites swapped to `transOr()`: `layout.blade.php` (site name fallback, meta
   description/title, footer address + institution codes), `header.blade.php` (site name
   fallback), `home.blade.php`/`page.blade.php` (title/meta fallbacks). Tests
   (`tests/Feature/Admin/MultilingualSchoolContentTest.php`). Merged to `dev`.
5. ✅ **AI-assist**: `TranslationGatewayContract`/`MyMemoryTranslator` (free, keyless MyMemory
   API — revised from the original Anthropic/LMS-gated plan, see "AI-assisted draft translation"
   above), three jobs (`SuggestSchoolTranslationJob`, `SuggestPageTranslationJob`,
   `SuggestMenuTranslationJob`, `dispatchSync()` — see follow-up below) each honoring their
   storage shape's own safety rule (fill-empty-only for School/SiteSetting, always-safe-new-draft
   for Pages, zero-items-only for Menus), a new `BlockTranslator` walking a page's block tree via
   a schema-driven per-block-type text field map. "Suggest translation (AI)" buttons wired into
   School settings, the page builder, and the menu editor — every school gets it, no module
   gating. Tests (`tests/Unit/Language/MyMemoryTranslatorTest.php`,
   `tests/Unit/Website/BlockTranslatorTest.php`, `tests/Feature/Admin/AiTranslationSuggestTest.php`
   — exercised end-to-end via `Http::fake()`, never a real network call). Merged to `dev`.
6. ✅ **Phase 5 follow-ups**, reported after the above shipped:
   - `needsPublish` fix — Copy/Suggest/Restore all create a new unpublished revision and reload
     straight into it, which used to leave the editor's Update button stuck disabled (nothing
     differs from what was just loaded) even on an already-published page.
   - The three Suggest jobs switched from a queued `dispatch()` to `dispatchSync()` — under this
     app's normal `QUEUE_CONNECTION=redis` a queued dispatch returned before Horizon ever ran the
     job, so "Suggest translation" could redirect back to stale content ("sometimes delayed").
     Running inline drops the Horizon dependency for these specific actions and guarantees the
     result is ready the moment the request resolves.
   - The page editor's Copy/Suggest actions became fetch()-driven, splicing the result straight
     into the live DOM instead of a full navigation, with a progress bar for the real (multi-
     second, sequential per-field MyMemory calls) wait.
   - **Public vs. backend locale split** (module 26's own `SetLocale`): the public site and the
     admin/staff/portal areas used to share ONE session key (`app_locale`) and ONE switcher route,
     so an admin's own backend working-language choice and whatever a public visitor was browsing
     in could silently overwrite each other. Split into `app_locale` (public, `/language/{code}`)
     vs. `backend_locale` (`/admin`, `/staff`, `/portal`, new auth-gated
     `/backend/language/{code}` — the only thing `partials/language-switcher.blade.php` links to)
     — see module 26's own docs for the full detail. Tests
     (`tests/Feature/Language/LanguageModuleTest.php`).

Each phase is its own branch off `dev`, its own commit(s), tests green before merge — same
workflow as modules 20/29. Small follow-up fixes after a phase's initial merge (like #6 above) go
directly onto `dev` instead, same convention CLAUDE.md documents for every other module.

## Non-goals

- Locale-prefixed URLs (`/en/about`) — out of scope, stays session-based.
- Machine-translating existing untouched content in bulk on migration — new content only,
  admin/AI-assist opts in per field.
- Translating `Announcement`/`Notice` module content, staff/student-facing portals, or anything
  outside the public website — this plan is scoped to the public-facing site only.
