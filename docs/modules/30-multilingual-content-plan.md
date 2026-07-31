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
reviewed/edited before saving) are in scope, modeled on the LMS module's existing Anthropic
integration.

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

Models the LMS module's existing `AnthropicAiChecker` (`app/Modules/Lms/Gateways/`) exactly:
`Http::withHeaders([...])->timeout(...)->post($apiBase, [...])`, `claude-3-5-haiku-latest`,
strict-JSON prompt, response parsed via `$response->json('content.0.text')`. Given the LMS
gotcha already on file (checker throws, a queued job is the layer that catches), the same split
applies here: a synchronous "Suggest" button feels wrong for a network call inside an admin
form, so a **queued** `SuggestTranslationJob` (`ShouldQueue`, catches everything, writes a
draft into the relevant locale row/`content_translations` value) backs the button, with the
admin UI polling or the page just refreshing to show the draft once ready — draft is never
auto-published, admin always reviews/edits before the locale row is marked non-empty.

API key: reuses `schools.lms_ai_api_key` if the LMS module is enabled for that school (same key,
same provider, no reason to force a second key); if LMS isn't enabled, the "Suggest" button is
simply hidden (matches the existing `module.enabled:{name}` gating pattern already used for
Payroll/LMS).

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
4. **School identity + SiteSetting text**: admin Translations panel, public call-site swap to
   `transOr()`. Tests.
5. **AI-assist**: `SuggestTranslationJob`, "Suggest translation" buttons wired into the Phase
   2–4 admin UIs, gated on `module.enabled:lms` + a set `lms_ai_api_key`. Tests (queued-job
   catches-everything per the sync-queue gotcha, never asserts a real network call).

Each phase is its own branch off `dev`, its own commit(s), tests green before merge — same
workflow as modules 20/29.

## Non-goals

- Locale-prefixed URLs (`/en/about`) — out of scope, stays session-based.
- Machine-translating existing untouched content in bulk on migration — new content only,
  admin/AI-assist opts in per field.
- Translating `Announcement`/`Notice` module content, staff/student-facing portals, or anything
  outside the public website — this plan is scoped to the public-facing site only.
