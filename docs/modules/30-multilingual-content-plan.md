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
  default-locale content instead of starting blank. *(Removed for Menus specifically after Phase
  5 shipped "Suggest translation (AI)" — see Phase 5 follow-ups below — since it duplicated that
  button's purpose and having both on screen was more confusing than useful; Pages keeps its own
  Copy button, which offers something AI-suggest there doesn't.)*

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
   - Menu save could delete items non-deterministically: `menu_items.parent_id`'s
     `cascadeOnDelete()` racing `MenuService::replaceItems()`'s `ORDER BY sort_order` delete —
     see CLAUDE.md's own Gotchas Learned entry for the full mechanism. Fixed by deleting children
     before parents explicitly, with no ordering on either step. Also removed the Menu editor's
     "Copy from default language to start translating" button — it duplicated "Build from default
     language (AI)" (Phase 5) for no real benefit once that shipped.
7. ✅ **Announcement + Staff/Designation/Department** (extends this plan beyond the public
   website — see Non-goals below, updated): `HasTranslations` wired onto `Announcement`
   (title/body), `Staff` (name), `Designation` (name), `Department` (name) — all four reuse
   Phase 4/5's exact mechanism (`content_translations`, `TranslationService::saveMany()`, a
   per-model `Suggest*TranslationJob`) unchanged, for admin editing (staff directory,
   announcements board). The one real difference: these are all per-row list-with-modal-per-row
   admin screens (DataTable + Bootstrap modal), not a singleton settings page or a dedicated edit
   route — so the language-panel-per-`<details>` and "hidden sibling AI-suggest form" pattern
   from School's editor had to go *inside* each row's own edit modal, with per-row-unique
   DOM/route ids (`ai-suggest-{model}-{id}-{locale}`) instead of one set per page. Designation and
   Department share one `SuggestStaffReferenceTranslationJob` (parameterized by model class
   string) the same way `StaffReferenceController` already shares one controller for both.
   `TranslationService::saveMany()`'s union type widened to admit all four new hosts (see its own
   docblock). Tests (`tests/Feature/Admin/MultilingualAnnouncementAndStaffContentTest.php`).
   Merged to `dev`.
8. ✅ **Public block rendering follow-up** — Phase 7 above wired up admin editing/AI-suggest for
   Announcement/Staff but missed that both already feed the *public* site through the "notices"
   and "staff" website blocks (`PageRenderService::resolveBlockData()` →
   `PublicPortalService::notices()`/`staffList()` → `public/blocks/render.blade.php`) and the
   header notice ticker (`AppServiceProvider`'s `public.partials.header` composer). That live-data
   path is completely separate from `BlockTranslator` (which only ever walks a page's stored
   `layout_json` — the block's own static `heading` field, never the live-resolved
   notices/members list), so admin-saved translations never reached a real visitor: `$locale` was
   never threaded down to `PublicPortalService` at all. Fixed by adding a required `string $locale`
   parameter through the whole chain — `PageRenderService::renderPage()`/`buildView()`/
   `buildViewFromBlocks()`/`resolveBlockData()`/`resolveNestedBlocks()`/`staffFor()` down to
   `PublicPortalService::notices()`/`staffList()`, which now call `transOr()` per item (skipped
   entirely for the default locale — no-op fast path, same convention as
   `publishedLayoutFor()`'s own default-locale check). `listVisible()`'s Announcement collection is
   cache-aside (`BaseRepository::remember()`); mutating a cached model's `title`/`body` in place
   would leak one locale's translated text into every other locale's cache hit under any cache
   store that doesn't round-trip through serialization (the `array` driver the test suite runs
   on) — both `notices()` and `staffList()` clone each item before overwriting its translatable
   attributes. Also threaded through the admin page-builder's live-preview endpoints
   (`preview()`/`previewBlock()`) so the iframe reflects the language tab actually being edited,
   not always the default locale. Tests
   (`tests/Feature/Public/MultilingualBlockContentTest.php`). Landed directly on `dev` (small
   follow-up, same convention as #6 above).
9. ✅ **"Suggest translation (AI)" no longer closes the modal** — Announcement/Staff/Designation/
   Department (Phase 7) are edited in a modal-per-row, not a dedicated page like School's settings
   editor. Their "Suggest translation (AI)" button originally just `.submit()`ed the same hidden
   form School's editor uses, which POSTs and redirects — on a dedicated page that's a harmless
   full reload of the same editor, but here it closed the modal entirely, forcing the admin to
   reopen Edit just to see what the AI filled in. Fixed by having the button fetch() the same
   endpoint with `X-Requested-With: XMLHttpRequest` instead of submitting the form normally; the
   three controllers now branch on `$request->ajax()` and return the resolved field values as JSON
   instead of redirecting, and a new shared partial
   (`admin/partials/translation-suggest-script.blade.php`, one delegated click listener per index
   page — not one per row) fills the matching input/textarea in place, but only if it's still
   empty, so it can never clobber text the admin already typed. A real (non-ajax) form submit still
   redirects exactly as before — kept as a plain fallback. Tests added to
   `MultilingualAnnouncementAndStaffContentTest.php` covering the JSON response path.
10. ✅ **"Suggest translation (AI)" for the flat UI-string catalog** (Settings → Languages →
    Translations) — everywhere else Phase 5's AI-suggest covers a fixed field list on ONE model
    (School's `name`/`address`/..., a Page's blocks, ...); this catalog has no field list at all —
    every row IS its own translatable unit, keyed by the row's own `key` column (always the literal
    English source text, never `Language::defaultCode()` — this table is English-as-key by
    construction). New `SuggestUiTranslationsJob(string $locale, array $ids)` operates over an
    explicit list of `Translation` row ids rather than "every untranslated row for this locale":
    `LanguageController::suggestTranslations()` passes exactly the ids the editor's own "Save
    Translations" form already submits (`t[id]=value`) — i.e. one paginated page's worth (≤50),
    never the whole ~2,200-row catalog in one request, which would be long enough a sequence of
    sequential MyMemory calls to risk a real HTTP timeout on shared hosting. The new button is a
    second `type="submit"` on the SAME form (via `formaction`, reusing every `t[id]` field already
    rendered — no separate hidden-id-list form needed) and is registered as a `PUT` route to match
    that form's existing `@method('PUT')` spoofing. `suggestTranslations()` first persists any
    manually-typed-but-unsaved values from the same submission (identical to `saveTranslations()`'s
    own loop) before dispatching the job — otherwise an in-progress edit sitting in the form would
    be silently discarded by the `back()` redirect re-fetching from the DB; this makes "Suggest" a
    strict superset of "Save," never a data-loss risk. Tests
    (`tests/Feature/Admin/UiTranslationSuggestTest.php`).

Each phase is its own branch off `dev`, its own commit(s), tests green before merge — same
workflow as modules 20/29. Small follow-up fixes after a phase's initial merge (like #6 above) go
directly onto `dev` instead, same convention CLAUDE.md documents for every other module.

## Non-goals

- Locale-prefixed URLs (`/en/about`) — out of scope, stays session-based.
- Machine-translating existing untouched content in bulk on migration — new content only,
  admin/AI-assist opts in per field.
- Translating staff/student-facing portal UI strings, or any further admin-editable content
  beyond what Phase 7 above already covers (Announcement title/body, Staff/Designation/Department
  name) — those four were the specific, explicitly-requested exceptions to this plan's original
  public-website-only scope. (Phase 8 corrected an initial assumption here: Announcement/Staff
  content isn't admin-only — it already reaches the public site via the notices/staff blocks and
  header ticker, so Phase 8 wired the locale into that rendering path too. The scope boundary is
  still "these four models, nothing else," just not "admin-only" as first described.)
