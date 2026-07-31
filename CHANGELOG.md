# Changelog

All notable changes to this project are documented here. Format loosely
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Bilingual (en + bn) seed/demo data: `SchoolSeeder`/`DemoDataSeeder`/`WebsitePagesSeeder` now
  seed hand-written Bangla translations for School/SiteSetting identity fields, Department/
  Designation names, all 12 demo Staff names, all 3 demo Announcements, all 11 public pages'
  block content, and a Bangla primary navigation Menu — so a fresh install already shows real
  Bangla content when switching languages, instead of an empty translation until an admin fills
  one in by hand. New `WebsitePagesSeeder::pageTranslation()` seeds a locale-specific
  `PageLayout` against the SAME `Page` row (never a second `Page`); fixed a latent bug where
  `page()`'s `PageLayout` delete was unscoped by locale, which would have wiped the Bangla layout
  on every reseed. None of this content goes through the AI-suggest gateway — seed data is
  hand-written and stable, not a live network call at seed time. See
  `docs/modules/30-multilingual-content-plan.md` Phase 11.
- "Suggest translations (AI)" for the general UI-string catalog (Settings → Languages →
  Translations editor) — previously only the per-model content (School, Pages, Menus, Announcement,
  Staff, ...) had an AI-suggest button; the flat `__()` string catalog (~2,200 keys) was manual-only.
  New `SuggestUiTranslationsJob` translates a given list of `Translation` row ids via the existing
  MyMemory gateway, never overwriting an already-translated row. The editor's "Suggest translations
  (AI)" button is a second submit on the same form as "Save Translations" (via `formaction`) and
  operates on exactly the ids currently on screen — one paginated page at a time (≤50 rows), not the
  whole catalog in one request. Clicking it also saves any values you've already typed in but not
  yet clicked Save for, so it can never discard in-progress edits. Tests
  (`tests/Feature/Admin/UiTranslationSuggestTest.php`).
- `App\Support\LocalizedDate` — native (offline, no third-party translation API) date
  localization: translated month/day names via Carbon's own bundled locale data
  (`vendor/nesbot/carbon`, a one-time composer install, never a runtime network call), plus a
  hardcoded native-digit map (currently Bengali ০-৯) Carbon doesn't apply on its own. `SetLocale`
  middleware now also calls `Carbon::setLocale()` alongside `app()->setLocale()`, so every
  `translatedFormat()` call downstream (this helper, or a raw Carbon call anywhere else) picks up
  the visitor's locale automatically. Wired into the public site's four date-rendering call sites:
  the header's "today" date, the notices block/sidebar/homepage-fallback date lines, and the
  footer's copyright year + established year. Tests: `tests/Unit/Support/LocalizedDateTest.php`,
  plus two new cases in `MultilingualBlockContentTest.php`. Admin/portal/staff areas (~70 more
  `->format()` call sites) are NOT touched by this — public-site-only for now, same scoping
  discipline as the rest of this multilingual work; a good next step if wanted.
- Per-field translation for Announcement (title + body) and Staff/Designation/Department (name),
  extending `docs/modules/30-multilingual-content-plan.md`'s Phase 4/5 pattern beyond the public
  website's original scope to these four models. `HasTranslations` wired onto all four models; each
  gets a "Translations" section (one collapsed panel per active non-default language, same
  convention as School settings) inside its existing modal-per-row edit form, plus a "Suggest
  translations (AI)" button per language. Designation and Department share one
  `SuggestStaffReferenceTranslationJob` (parameterized by model class) the same way
  `StaffReferenceController` already shares one controller for both types.
  `TranslationService::saveMany()`'s type widened to admit the four new hosts. Tests
  (`tests/Feature/Admin/MultilingualAnnouncementAndStaffContentTest.php`).

### Fixed
- Reported: the public footer's "© 2026 &lt;school name&gt;. All rights reserved." only translated
  the school name — "All rights reserved." was a bare literal string, never wrapped in `__()`,
  unlike every other string in `public/layout.blade.php`'s footer. Also fixed the same bug in the
  footer's institution-code fallback label ("Code"). Wrapping in `__()` alone isn't a complete fix
  for an already-deployed school: run `php artisan translations:scan` to register the new key, then
  fill in its Bengali value under Settings → Languages → Translations (bn) — e.g. "সর্বস্বত্ব
  সংরক্ষিত।" for "All rights reserved."
- Reported: clicking "Suggest translation (AI)" on Announcement/Staff/Designation/Department closed
  the edit modal (a plain form POST+redirect), forcing the admin to reopen Edit just to see what
  the AI filled in. Fixed by having the button fetch() the same endpoint with
  `X-Requested-With: XMLHttpRequest` instead of submitting the hidden form normally; the three
  controllers now return the resolved field values as JSON for an ajax request instead of
  redirecting, and a new shared script
  (`admin/partials/translation-suggest-script.blade.php`) fills the matching input/textarea in
  place — only if it's still empty, so it can never overwrite text the admin already typed. A real
  (non-ajax) form submit still redirects exactly as before.
- Reported: the newly-added Announcement/Staff translations weren't showing up on the public
  website — the "notices" and "staff" blocks (and the header's notice ticker) kept showing the
  default-locale English text no matter which language a visitor was browsing in. Root cause: those
  blocks' content is *live-resolved* from Announcement/Staff on every render
  (`PageRenderService::resolveBlockData()` → `PublicPortalService::notices()`/`staffList()`), a
  completely separate path from `BlockTranslator` (which only ever translates a page's stored
  `layout_json` — a block's own static `heading` field, never live-resolved data) — the visitor's
  locale was simply never passed down to `PublicPortalService` at all, anywhere in the chain. Fixed
  by threading a required `string $locale` parameter through the full render pipeline
  (`renderPage()`/`buildView()`/`buildViewFromBlocks()`/`resolveBlockData()`/
  `resolveNestedBlocks()`/`staffFor()`) down to `notices()`/`staffList()`, which now apply
  `transOr()` per item (skipped for the default locale as a fast path). `listVisible()`'s
  Announcement collection is cache-aside; both methods clone each item before overwriting its
  translatable attributes so a locale's translated text can never leak into another locale's cache
  hit. Also wired into the admin page-builder's live-preview endpoints so the iframe reflects
  whichever language tab is actually being edited. Tests
  (`tests/Feature/Public/MultilingualBlockContentTest.php`).
- Reported: saving the Menu editor for a locale after using "Build from default language (AI)"
  left the tree corrupted — some top-level items duplicated, the Gallery dropdown split into two
  separate single-child dropdowns instead of one with both children, and later items (Notices,
  Contact) missing entirely, despite the page showing a clean, correct tree right up until "Save
  Menu" was clicked — and it reproduced identically on every attempt, growing worse each time a
  save was repeated (confirmed via direct DB dumps: the same specific items were left behind every
  single time, never a random subset — the signature of a deterministic bug, not a timing race).
  Root cause: `menu_items.parent_id` is a self-referencing foreign key with `cascadeOnDelete()` (a
  dropdown's children are auto-deleted when their parent row is), and `MenuService::replaceItems()`
  deleted via `$menu->allItems()->delete()`, whose relation carries `orderBy('sort_order')`. A
  child's own `sort_order` is independently 0-based per parent, so it routinely ties with unrelated
  top-level items' `sort_order` values — and when that ordering caused the delete statement to
  remove a dropdown's parent row before its own scan reached that parent's children, the cascade
  silently removed those children out from under the statement, while rows the cascade never raced
  were left behind untouched — then the fresh insert landed on top of whatever survived. Fixed by
  deleting children (no children of their own — one level of nesting only) before parents, with no
  ordering involved either time, so the cascade can never race the delete statement's own scan.
  Also closed a related, smaller gap while investigating: `replaceItems()` had no protection against
  two overlapping saves against the same menu racing each other (unlike `replaceItemsIfEmpty()`,
  used by the AI-suggest flow, which already locks the row) — it now takes the same row lock first.
  The Save Menu button is also disabled (with a spinner) the instant it's clicked, closing off the
  easiest way to fire two overlapping saves at the source.

### Changed
- Removed the Menu editor's "Copy from default language to start translating" button (and its
  `POST /admin/menus/copy-locale` route/controller action) — it duplicated "Build from default
  language (AI)" (Phase 5) for no real benefit once that shipped, and having both on screen was
  more confusing than useful. The Page editor keeps its own Copy button, which still offers
  something AI-suggest there doesn't (labels stay in the source language rather than
  auto-translated, useful when a school wants to translate by hand).

### Added
- Multilingual content foundation (`docs/modules/30-multilingual-content-plan.md` Phase 1):
  `page_layouts` gains `locale` + per-locale SEO meta columns, `menus` gains `locale`, and a new
  `content_translations` table + `HasTranslations` trait (wired onto `School` and `SiteSetting`)
  provide per-field translations for scalar singleton-row models. Purely additive — no rendered
  output changes yet. This is the first phase of fixing a reported gap: the public language
  switcher only ever changed static `__()` UI strings, never a school's own page/menu/site
  content, because none of it had a locale column to switch between.
- Multilingual page/block content (`docs/modules/30-multilingual-content-plan.md` Phase 2): each
  page's block layout, title, and SEO meta can now vary per language — every locale owns its own
  independently draft/published `PageLayout` revision (`pages.*` stays the default-locale seed),
  publishing one language never touches another's, and an untranslated locale silently falls back
  to the default language instead of rendering blank. Admin page builder gains a language tab
  switcher (marks untranslated locales) and a "Copy from default language" starter action; History
  now tracks "Latest"/"Current" per locale, not globally. Demo site seed fixed to set an explicit
  locale on its seeded page revisions (an oversight that would otherwise have made every seeded
  page invisible under the new locale-scoped public render query).
- Multilingual navigation menus (`docs/modules/30-multilingual-content-plan.md` Phase 3): each
  language now owns its own full menu tree (`menus.locale`), so the public nav actually changes
  when a visitor switches language instead of always showing whatever was built first. The admin
  menu editor gains the same language-tab switcher pattern as the Phase 2 page builder (marks
  untranslated languages, plain-GET reload), and the public header composer resolves the
  visitor's locale via a new `Menu::published()` (falls back to the default language's menu when
  the visitor's language has no nav built yet, same fallback used everywhere else in this
  feature). Extracted the locale-resolution logic that Phase 2's `PageController` had local to
  itself into a shared `Language::resolve()`, now used by both controllers. Demo site seeder
  fixed to seed its "Main menu" with an explicit locale (the same class of bug Phase 2 hit with
  `PageLayout`, caught here before it shipped).
- Multilingual school identity & SEO text (`docs/modules/30-multilingual-content-plan.md` Phase
  4): School name/address/institution codes and SiteSetting meta title/description can now vary
  per language. Unlike Pages/Menus (a full duplicate row per locale), these are singleton-per-
  school rows, so they reuse Phase 1's generic `content_translations` table directly — new
  `TranslationService::saveMany()` bulk-saves every active language's overrides from one admin
  form submit. School settings gains a collapsed "Translations" panel per active language; a
  blank submitted field clears back to the fallback rather than saving a literal blank override
  (guards against a freshly-added language's untouched panel silently blanking the whole public
  site). Public site (header, footer, homepage, page `<title>`) now reads these via `transOr()`
  instead of the raw column, with the same silent default-language fallback used throughout this
  feature.
- AI-assisted draft translation (`docs/modules/30-multilingual-content-plan.md` Phase 5): a
  "Suggest translation (AI)" button on School settings, the page builder, and the menu editor,
  backed by the free MyMemory Translation API — no API key, no billing, available to every
  school (revised from the original plan to reuse the LMS module's paid Anthropic key, since
  translation drafts aren't an LMS-specific feature). Each of the three content shapes gets its
  own safety rule: School/SiteSetting fills only currently-empty fields, never overwriting a
  hand-translated one; Pages always create a brand-new draft revision (never destructive, since
  `PageLayout` is append-only) and translate every text field inside the block tree via a new
  schema-driven `BlockTranslator` (structural values — urls, colors, icons — are never sent
  through translation); Menus only build a translated tree when the target language currently has
  zero items, since a menu save is a full-tree replace that could otherwise destroy a hand-built
  nav. One field failing (a rate limit mid-batch) never discards the rest of a suggestion — it's
  just left in the source language for the admin to finish by hand. Tests exercise the real
  end-to-end flow via `Http::fake()`, never a real network call.

### Fixed
- Phase 5 follow-ups reported after the above shipped:
  - The page editor's Update/Publish button starts disabled and only re-enables once the form
    differs from what was just loaded — but Copy from default language / Suggest translation (AI)
    / Restore all create a brand-new unpublished `PageLayout` revision and reload straight into it,
    so nothing "differs" and the button stayed stuck disabled even on an already-published page,
    with no way to publish the new draft short of a throwaway edit. `PageController::edit()` now
    computes a `needsPublish` flag (page published overall, but the locale's loaded revision isn't
    itself the published one) that keeps Update usable in that case.
  - `SuggestSchoolTranslationJob`/`SuggestPageTranslationJob`/`SuggestMenuTranslationJob` now run
    via `dispatchSync()` instead of a queued `dispatch()` — under this app's normal
    `QUEUE_CONNECTION=redis`, a queued dispatch returns before Horizon ever runs the job, so the
    "Suggest translation" actions could redirect back showing stale content with no real signal
    translation was still in flight ("sometimes delayed"). Running inline keeps them exactly as
    best-effort/`tries=1` as before, just synchronous, and drops the Horizon dependency for these
    specific actions entirely.
  - The page editor's Copy/Suggest actions are now fetch()-driven: they splice the resulting
    blocks/fields/History/language-switcher state straight into the live editor instead of doing a
    full form-post navigation, with a progress bar covering the real (multi-second, sequential
    per-field MyMemory calls) wait and a toast surfacing the result — addresses "get the
    translation without refreshing the page."
  - Fixed a misaligned dismiss icon on the page editor's own "Page Saved" toast: `.alert-dismissible`
    reserves padding and absolutely-positions `.btn-close` for the *default* `.alert` box size, which
    didn't match this toast's smaller `py-2`/`px-3` override (and `.btn-close-sm`, also used there,
    isn't a real Bootstrap class — it did nothing to compensate). Switched to a plain flex row, which
    sizes and centers the close button correctly regardless of the alert's own padding.
- Public site and backend (admin/staff/portal) language were unintentionally coupled: both areas
  shared a single `app_locale` session key and one `/language/{code}` switcher (Language module,
  #26), so an admin switching their own backend working language also changed what a public
  visitor saw, and vice versa. `SetLocale` now reads/writes one of two independent session keys
  depending on the request path — `app_locale` for the public site, `backend_locale` for
  `/admin`, `/staff`, `/portal` — with a separate, auth-gated `/backend/language/{code}` route for
  the latter (the only thing `partials/language-switcher.blade.php`, shared by the admin/staff/
  portal headers, links to). An admin can now run the backend in one language while the public
  site serves visitors in a completely different one.
- Follow-up to the above: that path match was originally `admin*`/`staff*`/`portal*`
  (`Request::is()` wildcards match on raw string prefix, not path segment boundaries), so a
  **public** page whose slug merely started with those letters — e.g. a school's own
  `/administration` page, same slug `WebsitePagesSeeder`'s demo content uses — was wrongly
  classified as backend and silently read the unset `backend_locale` instead of the public
  `app_locale` a visitor had actually chosen (reported: the language switcher "reverts back to
  English" on that page specifically). Now matches `admin`/`admin/*` (an exact segment boundary)
  instead.
- The above backend-locale split exposed a real, previously-latent crash: any admin page fatals
  (`htmlspecialchars(): Argument #1 ($string) must be of type string, array given`) rendering
  `components/sidebar.blade.php` under `backend_locale=bn`, because nothing had ever rendered the
  admin panel in a non-English locale independently of the public site before. Root cause: a no-dot
  `__()` key not found in the flat English-as-key JSON cache falls through to Laravel's group-based
  translation lookup, treating the *entire key* as a group name — `__('SMS')` (used as the nav
  label, command palette entry, and page title/breadcrumbs across the SMS module) collided with
  the real `resources/lang/{locale}/sms.php` group file, and since the key has no item segment,
  `Arr::get()` returned the file's *whole array* instead of a string. Renamed that lang file to
  `sms_templates.php` (no visible UI text changed — only the internal `sms.due_reminder` key,
  updated at its one call site in `SendSmsBatchJob`) to remove the collision, and added a defensive
  string coercion in `sidebar.blade.php`'s label output so a future label/lang-group name clash
  degrades to a blank label instead of a 500.

## [1.3.4] — 2026-07-31

### Added
- Public frontend modernization, Phase 1 (`docs/modules/29-frontend-modernization-proposal.md`): wired up
  ~19 `site_settings` theming columns that existed in the schema since the Website module shipped but were
  never exposed in the admin UI nor read by `public/layout.blade.php` — secondary/background/surface/text/
  link/link-hover/border colors, heading/body Google Fonts (curated allow-list, only loaded when a school
  picks one — no extra request otherwise), base font size, page container width, button radius/font-weight/
  hover-transition-speed/filled+outline colors, and a global background (flat color or image+tint overlay).
  New "Advanced Theme" section in Website settings (School settings > Branding & Appearance), collapsed by
  default. Every value falls back to this file's original hardcoded default, so a school that never opens
  the new section renders byte-for-byte identically to before.
- Font names are validated against a fixed allow-list (`SiteSetting::FONTS`) both at save time and again at
  render time, rather than accepted as free text — these values get interpolated directly into a `<style>`
  block and a Google Fonts URL, so an unvalidated value would be a CSS/HTML injection vector once actually
  wired into a real render path (previously moot, since nothing read these columns at all).
- Public frontend modernization, Phase 2 (`docs/modules/29-frontend-modernization-proposal.md`): collapsed
  the public site header from three stacked rows (utility bar, logo/institution-data row, nav row) into a
  slim utility strip plus a single sticky logo+nav+CTA bar — institution codes and the established year
  moved to the footer, where they're still fully visible, just not competing with the nav for space above
  the fold. The merged bar shrinks slightly once the page scrolls (a passive scroll listener toggling one
  CSS class, no per-frame layout work). Added an "Apply Now" admissions CTA in the nav, distinct from the
  Login button (previously the only button in the header). Added a `clamp()`-based fluid type scale for the
  hero heading and section titles so they scale smoothly with viewport width instead of jumping at
  breakpoints. `PublicHeaderTest` updated to match the new structure (institution codes/established year
  assertions moved into a new footer-focused test, same underlying data, same coverage).
- Public frontend modernization, Phase 3 (`docs/modules/29-frontend-modernization-proposal.md`): two new
  page-builder block types. **Announcement bar** — a slim, dismissible, brand-colored bar with an optional
  link, distinct from the existing notice ticker (a scrolling feed of `Announcement` records) — this is a
  single, admin-authored, high-intent message ("Admissions open for 2026-27"). Dismissal is remembered per
  browser via `localStorage`, keyed off the message text itself, so editing the message re-shows it to
  someone who dismissed the old wording. **FAQ accordion** — a Bootstrap accordion fed by a
  `Question|Answer per line` textarea, matching the existing `quick_links`/`office_hours`
  `Label|Value per line` convention exactly (same `pairs()` parsing helper, same multiline-textarea editing
  UX). Both ship with the same Style/Layout tab controls (padding, background, animation, visibility) every
  other block already has, and both are purely additive to `layout_json`'s shape — no migration, and every
  existing page keeps rendering unchanged.

### Changed
- Demo site seed (`database/seeders/SchoolSeeder.php`, `WebsitePagesSeeder.php`) now exercises every piece of
  Phases 1–3 above in real use, not just their empty/default states: the demo school's `SiteSetting` sets
  Advanced Theme values (Poppins/Inter fonts, a forest-green footer via `secondary_color`, a tinted card
  `surface_color`, `10px` button radius/`600` weight) alongside its existing brand color; the homepage leads
  with a dismissible announcement bar linking to Online Admission; Online Admission ends with a 4-question
  FAQ accordion. `WebsitePagesSeedTest` extended to cover all of it.

### Fixed
- Public nav's accent-color hover underline (Phase 2 above) and Bootstrap's own dropdown caret both targeted
  `::after` on the same `<a class="nav-link dropdown-toggle">` — a shared pseudo-element doesn't let one
  ruleset win, every non-conflicting property from both declarations renders at once, so a parent nav item
  with a submenu ("Gallery", "About") showed Bootstrap's border-drawn caret triangle (rendered as a stray dark
  line once the underline rule's `position: absolute` also applied to it) stacked on top of the accent-color
  underline bar. Moved the underline to `::before`, which nothing else in Bootstrap's dropdown CSS touches, so
  the two can no longer collide.

## [1.3.3] — 2026-07-28

### Changed
- Routine dependency bumps via Dependabot: `laravel/framework` 13.21.1 → 13.23.0, `laravel/pint` 1.29.3 →
  1.30.0 (both `dev-dependencies`/`laravel` grouped PRs), and `concurrently` 10.0.3 → 10.0.4. The
  `concurrently` bump is notable on its own: 10.0.4 now pins `shell-quote@1.9.0` directly upstream, which
  independently confirms the `shell-quote` DoS fix earlier in this release was correct — the `overrides` entry
  in `package.json` is harmless-but-now-redundant rather than removed, since it's still satisfied either way.
  Ran `npm install` after merging to sync `node_modules`/`package-lock.json`; `npm audit` still reports 0
  vulnerabilities.

### Fixed
- Removed 3 leftover `console.log()` debug statements from the admin page-builder's Admission Form custom
  field editor (`resources/views/admin/website/pages/_admission_form_fields.blade.php`) — every admin using
  the "add custom field" button had their browser console cluttered with development breadcrumbs
  ("addCustomField called with prefix:", etc.). Kept the two `console.error()` calls (template/container not
  found) — those are genuine defensive diagnostics for a real failure case, not debug noise.
- `scripts/deploy.sh` was committed non-executable (`100644`) because this repo has `core.filemode=false` set
  (sensible for the Windows-mounted dev drive, but it means a local `chmod +x` never gets picked up by `git
  add`) — anyone cloning fresh on a VPS and following the docs' `./scripts/deploy.sh` instructions literally
  would have hit "Permission denied." Fixed via `git update-index --chmod=+x`.
- `CLAUDE.md`'s own "Key Patterns" reference examples (Repository/Observer cache pattern, the Mark module's
  tabulation cache) still showed the raw `Cache::tags([...])->flush()`/`->remember()` facade instead of
  `App\Support\CacheTags` — the exact pattern that was already wrong once (see `PageRenderService`'s fix a
  few releases back) and would silently break again on shared cPanel hosting (`CACHE_STORE=database`/`file`
  don't support native tagging) if a future module's Repository/Observer copied the stale example verbatim.
  Added a corresponding "Gotchas Learned" entry so this doesn't quietly regress a third time. Also fixed an
  unrelated literal duplicated sentence in the same section.

### Security
- Bumped the transitive `shell-quote` dependency (pulled in only by `concurrently`, a devDependency used by
  `composer run dev` to run `php artisan serve`/queue listener/`pail`/`npm run dev` together) from `1.8.4` to
  `1.10.0`, fixing [GHSA-395f-4hp3-45gv](https://github.com/advisories/GHSA-395f-4hp3-45gv) (CVE-2026-13311):
  a quadratic-complexity DoS in `shell-quote`'s `parse()` that could hang the event loop for seconds on a
  small, plain-text (no shell metacharacters needed) input. `concurrently@10.0.3` pins `shell-quote` to the
  exact vulnerable version, which is what blocked Dependabot's automatic update — added an `overrides` entry
  in `package.json` to force the patched version tree-wide instead, per Dependabot's own suggested fix.
  Real-world exposure here was low regardless (dev-only tooling, never shipped to production, not on any
  network-facing path), but `npm audit` now reports 0 vulnerabilities.

### Added
- Shared cPanel hosting support, without any code changes to how the app is normally run elsewhere.
  `config/filesystems.php`'s `minio` disk (every module calls `Storage::disk('minio')` by name) now falls
  back to a plain local disk automatically whenever `AWS_ENDPOINT` isn't set, instead of requiring a real
  MinIO/S3 endpoint. Added `.env.cpanel.example` (database-backed cache/queue/sessions, SMTP mail, no Redis)
  and `docs/cpanel-deployment.md` — a full walkthrough covering the document-root gotcha on shared hosting,
  Composer with/without SSH, cron-based scheduler + queue worker (no Horizon/Supervisor available), storage
  symlink, file permissions, PHP limits worth raising, HTTPS, and troubleshooting.

### Fixed
- The one remaining raw `Cache::tags(['pageview'])` call (in `PageRenderService::renderPage()`, the public
  page-render cache) didn't go through `App\Support\CacheTags` like every other tagged cache read/write in
  the app. Native Laravel cache tagging only exists on the Redis/Memcached/array drivers — not database or
  file, which is what `CACHE_STORE` needs to be on shared hosting without Redis — so this one call would
  have thrown "This cache store does not support tagging" the moment `CACHE_STORE=database`/`file` was set.
  Switched it to `CacheTags::remember()`. Behavior is unchanged: nothing ever flushes the `pageview` tag by
  name, since a fresh `PageLayout` id (and therefore a fresh cache key) is minted on every publish anyway.

### Fixed
- `/api/v2/health` hardcoded `Cache::store('redis')`, forcing a Redis connection attempt on every health
  check regardless of the app's actual `CACHE_STORE` — meaning this exact endpoint threw a 500 on any
  deployment (shared cPanel hosting, see above) that doesn't configure Redis at all, which is precisely the
  moment you'd most want a working smoke-test endpoint (right after a fresh deploy). Now resolves whatever
  cache store is actually configured; the response key changed from `redis` to `cache` to match. Added a
  regression test.
- The app's version number (shown in the admin panel footer) was read from `APP_VERSION` in `.env`, but
  `.env` is per-deployment and not git-tracked — an already-deployed server's `.env` only ever has whatever
  `APP_VERSION` it was first set up with, so bumping `.env.example` on every release never actually updated
  a running instance's footer, only fresh installs. Moved the version to a git-tracked `VERSION` file at the
  repo root instead (`config('app.version')` now reads it directly), so a plain `git pull` always picks up
  the current release. Removed `APP_VERSION` from `.env.example` and `.env.cpanel.example`.

### Added
- Version integrity checking, so a hand-edited `VERSION` file (accidental or not) is both caught as
  malformed and, where `.git` is present, checkable against whether that version number actually
  corresponds to real deployed code. `config('app.version')` now validates the `VERSION` file's format
  (`App\Support\VersionIntegrity::isValidFormat()`) and falls back to `'unknown'` instead of displaying
  garbage. New `App\Support\VersionIntegrity::verifyAgainstGit()` — on demand only, never on the request-boot
  path — checks whether that version has a matching git tag reachable from `HEAD`: `true` (tagged and at or
  before `HEAD`), `false` (no such tag exists, or `HEAD` is behind it — the version claims a release that
  isn't actually on disk), or `null` (unverifiable — no `.git` directory, e.g. a zip-uploaded shared-hosting
  install; treated as "can't check", never as a tamper signal). Surfaced via two new `version`/
  `version_verified` fields on `GET /api/v2/health` and a new `php artisan version:verify` command.
  `scripts/deploy.sh`'s health-check step now hard-fails the deploy on an explicit `version_verified:false`.
- `scripts/deploy.sh` and `docs/vps-deployment.md` — a safe update sequence for an already-installed
  instance (VPS/SSH, or shared cPanel hosting with a Terminal that has git): maintenance mode, database
  backup, fast-forward-only code update, `composer install`, migrate, cache rebuild, Horizon restart (only
  if actually running it), and an `/api/v2/health` smoke test before taking the site back out of maintenance
  mode. Stops on the first failure and leaves the site in maintenance mode rather than guessing its way back
  to a working state — prints the previous commit and backup file path instead. `docs/cpanel-deployment.md`
  gained an "Updating an existing installation" section covering the no-git/no-SSH shared-hosting case
  (temporary-folder + directory-swap approach instead of overwriting the live app in place), and explains
  what actually goes wrong from a naive "paste the new version over the old one" update: lost uploads
  (`storage/app/*` was never in git), an overwritten `.env`, a torn old/new file mix served mid-update, and
  stale config/route/view caches.

## [1.3.2] — 2026-07-27

### Added
- Gallery Photo and Gallery Video blocks now open a lightbox instead of leaving the page: clicking a photo
  opens it full-size in a modal with prev/next (and arrow-key) navigation through the rest of that gallery;
  clicking a video thumbnail opens the same style of modal and only then loads the embed, instead of every
  video on the page loading an iframe up front. Closing (or navigating away from) a video stops its
  playback. Each gallery block gets its own lightbox, so a page with more than one Gallery block doesn't
  have them interfere with each other.

### Fixed
- The Admission Form block rendered edge-to-edge full-width instead of staying inside the page's normal
  content column, unlike every other block. It was grouped with the Hero block as "self-contained"
  (meaning: skip the standard `<div class="container">` wrapper because the block manages its own width
  itself), but unlike Hero — whose markup has its own inner `<div class="container">` around its
  text/button content, with only the background bleeding full-width — the Admission Form's markup never
  had an equivalent inner container. It just lost its width constraint outright. Fixed by no longer
  treating `admission_form` as self-contained, so it gets the same container + default section padding as
  every other block.
- `/online-admission` (and any other page using the Admission Form block) threw a 500 — "Serialization of
  'Closure' is not allowed" — on every real (non-preview) page load. `PageRenderService` embedded 3 PHP
  Closures directly inside the block data it returns, and that data gets cached in Redis; Redis can't
  serialize a Closure. Invisible to the test suite because `phpunit.xml` runs tests against the `array`
  cache store, which never actually serializes anything. Fixed by keeping the cached data plain and
  reconstructing the helper closures locally inside the Blade view at render time instead. Added a
  regression test that calls `serialize()` directly on the rendered block data, reproducing the real
  failure without needing a Redis connection.

### Added
- Subtle motion on the public school site: buttons lift slightly on hover, cards get a soft shadow bump,
  nav links get an animated underline, and the hero fades in on load. All of it respects
  `prefers-reduced-motion` and skips straight to the end state when disabled.

### Changed
- Refreshed the public site's shared design tokens (`resources/views/public/layout.blade.php`) for a more
  minimal look: a small neutral/spacing/shadow/radius scale, softer card shadows with rounded corners,
  tighter heading letter-spacing, and branded form-focus rings (form fields previously fell back to
  Bootstrap's default blue focus ring regardless of the school's own brand color).
- The school's configured accent color (Website > Settings) is now actually used somewhere — it was declared
  as a CSS variable but never rendered anywhere on the live site. It now colors the nav underline.

### Fixed
- `.text-muted` on the public site now resolves to a token instead of Bootstrap's default gray, so muted
  text stays consistent with the rest of the refreshed palette.
- Several public-site strings that were hardcoded English literals ("Welcome to …", "Check results",
  "Teachers & staff", the results-CTA paragraph, the notices/staff "no data" fallback labels) are now
  translation keys like everything else on the site.

### Changed
- Pilot pass on the homepage's visual design (`public/home.blade.php`) and the hero/notices/staff
  page-builder blocks (`public/blocks/render.blade.php`) — restyled only, every field the block editor
  exposes (title, subtitle, button, image, heading, members, notices, limit) still works exactly as before:
  - Hero: eyebrow label, bigger heading, frosted glass ("stat-glass") stat tiles instead of plain white
    boxes, pill-shaped buttons.
  - Notices: a circular icon badge per card instead of an inline icon+text row, more generous card padding.
  - Staff: avatars now have a soft ring instead of a bordered box, un-boxed (no more card wrapper per
    person), bigger photos, a subtle scale on hover.
  - Results CTA: a tinted gradient panel instead of a plain white card.
  - Added a `py-lg-6`/`p-lg-6` utility pair (Bootstrap 5.3's shipped spacer scale stops at `5`) for extra
    section breathing room on large screens.
  - Scope: deliberately CSS/markup restyling only, inside `home.blade.php` and the 3 block cases' own
    content — the shared block wrapper (`data-block-path`/drag-and-drop/click-to-select attributes) that
    the admin page-builder's live-preview iframe depends on was not touched, and no other block type was
    changed in this pass.
- Rolled the same treatment out to the remaining page-builder block types, same restyle-only scope and same
  editor-wrapper guarantee as the pilot above:
  - Richtext and Image+Text blocks: their WYSIWYG HTML now renders through a `.prose` typography scale
    (heading/paragraph/list spacing, styled links) instead of completely unstyled browser defaults.
  - Image, Image+Text, and Gallery Photo blocks: images get a soft shadow, rounded corners, and a gentle
    zoom on hover, clipped tightly to the image's own rendered size (not a full-width invisible box) so it
    looks right whether the image fills its container or is a smaller centered image.
  - Video, Gallery Video, and Google Maps blocks: consistent rounded corners + soft shadow around the
    embed/player.
  - Icon block: a linked icon now scales slightly on hover.
  - Stats block: each tile lifts on hover, matching the small-tile treatment used elsewhere (distinct from
    the hero's frosted glass tiles, which only make sense on the hero's own gradient background).
  - Contact block (and the sidebar's Contact Info block): address/phone/email rows now use the same
    circular icon badge as the Notices block, instead of a plain inline icon.
  - Admission Form block: its five section labels ("Student Information", "Parent Information", …) get a
    thin rule under them to break up what's otherwise a very long single-column form.
  - Sidebar Quick Links block: links now use the same arrow-nudge hover as the homepage's "View all" link.
  - Divider block (and any `<hr>` a Richtext block's HTML happens to contain): resolves through the
    neutral border token instead of Bootstrap's default.
  - Container, Grid, Heading, Button, and Spacer blocks: left as-is — purely structural (Container/Grid) or
    already minimal enough that the token refresh already covers them (Heading via `.section-title`, Button
    via the shared `.btn` hover, Spacer has no visual surface at all).
  - Also fixed while touching this code: a hardcoded `'Teachers & staff'` string in the Stats block and
    several sidebar block headings ("Quick links", "Office hours", "Contact", "Recent notices") that were
    English literals, not translation keys.

## [1.3.1] — 2026-07-25

### Added
- Submit buttons now show a spinner and disable themselves while a form is submitting, panel-wide. Skips
  forms already cancelled by their own `confirm()` handler and forms opted out via `data-no-loading-state`.

### Fixed
- `public/css/admin-design-tokens.css` had ~250 lines of corrupted CSS (Form Wizard styles and the print
  stylesheet) — every line had a stray line-number token glued onto it, plus an orphaned extra closing brace.
  Restored valid CSS; also removed a redundant duplicate `.inline-edit-error` rule found in the same area.
- Removed a dark-mode CSS block in `layouts/admin.blade.php` that only checked the OS `prefers-color-scheme`
  (no app-level dark mode toggle exists) — it would have silently repainted just the sidebar dark for users
  with OS dark mode on, while the rest of the page stayed light.

### Changed
- Consolidated the admin panel's two competing color systems into one: `admin-design-tokens.css`'s
  `--color-primary-*` scale is now the actual indigo brand color (it was a blue scale that
  `layouts/admin.blade.php` silently overrode with hardcoded indigo hex values). The layout's Bootstrap
  variable bridge (`--bs-primary`, `.btn-primary`, `.text-primary`, focus rings, pagination, etc.) now
  references those tokens instead of repeating the hex literals a second time.
- `admin.partials.page-header` now accepts an `actions` array (multiple buttons), not just a single `action`
  — existing single-`action` usages are unaffected.
- All admin views now render status badges through the `<x-badge>` component instead of raw
  `<span class="badge text-bg-*">` markup — one shared source of badge styling across all 25 modules
  (People, Academics, Finance, HR, Comms, Certificates, Transport, Payroll, LMS, Library, Website, Setup).

### Removed
- Dead `resources/views/admin/students/` directory (a superseded tabbed student-detail page, never routed
  to — the live route renders `admin/people/students/show.blade.php` instead). Confirmed via `git log
  --follow` and a full reference search before deleting.
- Dead `@media (prefers-color-scheme: dark)` block in `admin-design-tokens.css`, gated behind a
  `data-theme="dark"` attribute that nothing in the codebase ever sets.

### Dependencies
- `laravel/framework` 13.18.0 → 13.21.1, `laravel/horizon` 5.47.2 → 5.48.1, `laravel/sanctum` 4.3.2 → 4.3.3
- `spatie/laravel-permission` 8.1.0 → 8.3.0
- `league/flysystem-aws-s3-v3` 3.35.1 → 3.35.2
- `phpoffice/phpspreadsheet` 1.30.5 → 1.30.6
- `nunomaduro/collision` 8.9.4 → 8.9.5
- `concurrently` (npm, dev) ^9.0.1 → ^10.0.3
- `actions/checkout` v6 → v7 in all CI workflows
- Docker base image `php:8.3-fpm` → `php:8.5-fpm`

## [1.3.0] — 2026-07-24

### Added
- Per-page SEO fields (meta title, meta description, Open Graph image), with matching Twitter Card tags on
  the public site. A page's own value overrides the site-wide default.
- Duplicate Page and Save as Template actions in the page editor, plus a screen for renaming/deleting saved
  templates.
- Media library: upload and picker wired into image/poster block fields, drag-and-drop upload, and alt-text
  editing.
- Autosave: local crash-recovery snapshots while editing, and a warning if the page was published elsewhere
  since you started.
- Public page rendering is cached and invalidated automatically on publish.
- The block editor's Layout tab is now "Advanced," split into four collapsible sections: Layout (grid
  columns, margin/padding, width mode — Default/Full Width/Inline/Custom), Border (type, width, color,
  radius, shadow), Background (color, image, overlay), and Responsive (per-breakpoint visibility). The Style
  tab is now just text color and entrance animation.
- Accessibility: announcements for block add/remove/reorder/move actions, focus restored when the
  right-click context menu closes, and aria-labels across the canvas.
- Test coverage for the Advanced tab and Container/Grid nesting.

### Changed
- Padding and margin moved from the Style tab to the Advanced tab as four-box (top/bottom/left/right)
  controls.
- Removed the Move Up/Down buttons from block rows — reordering is drag-only now.
- Media fields show a live thumbnail preview; empty image/video blocks show a placeholder instead of a
  broken-image icon.
- Page editor sidebar has a fixed 250px minimum width (was a viewport-relative percentage that could shrink
  below 200px on laptop screens).

### Fixed
- Registered the missing `minio` filesystem disk, so the media library can actually store uploads.
- `Page::layouts()` now tie-breaks on `id` as well as `created_at`.
- Resolved 14 PHPStan (level 5) errors and baselined one false positive.
- Status badges no longer clip tall-script glyphs.
- Media Library modal is now visible when opened from inside the fullscreen page editor.
- Public page title, description, and Open Graph/Twitter meta tags were double-HTML-escaped (`&amp;amp;`
  instead of `&amp;`) — fixed.
- Fixed a parse error in `PageSeoMetaTagsTest.php` that had been silently skipping that entire test file.
- Fixed two incorrect assertions in the width tests.

## [1.2.0] — 2026-07-24

### Added
- Elementor-style live page builder for the Website module: a fullscreen canvas with a resizable
  block-layers sidebar, a live preview that renders through the same Blade views as the public site, and a
  responsive desktop/laptop/tablet/mobile viewport toolbar.
- Click-to-select, drag-to-reorder, and right-click Copy Style / Paste Style / Remove on the live canvas.
- Drag a block type from the Add Block panel straight onto the canvas, including into a Container/Grid.
- 8 new block types: Video (YouTube/Vimeo/Dailymotion/VideoPress/self-hosted), Button, Divider, Spacer,
  Icon, Google Maps, and two layout blocks — Container and Grid — that hold nested children.
- Nested blocks go arbitrarily deep (up to 6 levels) and are fully canvas-interactive at every depth.
- Per-block Style (padding/margin/background/color/radius/shadow/animation) and Layout (columns/visibility)
  tabs, applied consistently across every block type.
- Session undo/redo, page revision history with one-click restore, and copy/paste block style.
- The editor's Update/Publish button stays disabled until something actually changes.
- `.github/dependabot.yml` for scheduled dependency updates.

### Changed
- Page editor sidebar is resizable (12.5%–25% of viewport width) and remembers its width.
- Block tabs are smaller and fully bordered, with the active tab filled in the site's brand color.

### Fixed
- A saved page with a populated Container/Grid block could throw `Undefined array key "d"` on the public
  site and in the live preview.
- Undo/redo could drop a Container/Grid block's children when restoring a history snapshot.
- The responsive viewport toolbar wasn't actually resizing the live preview.
- The sidebar-resize divider was effectively unclickable — a CSS `overflow` rule was clipping its hit area.
- Removed dead TinyMCE code; rich text editing has always run on Quill.
- Fixed a `postMessage` console error from the live preview iframe.

### Security
- Rate-limited login and the two-factor challenge (5 attempts/minute) — neither had any throttling before.
- Changing your password or disabling two-factor authentication now signs out every other active session.
- Requesting an email change now also notifies the current address, with a link to cancel the change.

## [1.0.1] — 2026-07-23

### Added
- Self-service Account & Security page for every user (admin, staff, family): change name and password,
  change email (held pending until confirmed), enable two-factor authentication (TOTP with recovery codes),
  and manage active sessions.
- Placeholder favicon across every layout.
- Release version shown in the admin panel footer, read from `APP_VERSION`.

### Fixed
- Selected language no longer reverts to English after a page refresh.
- Completed Bangla translation coverage across the admin panel.
- Fixed a translation-engine bug where a source string containing a literal period could corrupt a shorter,
  unrelated key sharing its prefix.
- Fixed the session/device list always reporting "No other active sessions" — the session ID was never being
  persisted.

## [1.0.0] — 2026-07-22

First tagged release.

### Added
- 26 modules: School, Academic, User/Auth, Student, Staff, Announcement, FeeItem, Payment (bKash,
  SSLCommerz, Stripe, PayPal), Examination, Attendance, Mark, Leave, Loan, Certificate, IdCard, Report, Sms,
  DataImport, OnlineAdmission, Website, Payroll, LMS, Library, Transport, Messaging, and Language.
- Server-rendered Laravel Blade + Bootstrap 5 admin panel with session auth, reusing module Services
  directly.
- 578 automated tests; CI runs the suite, Pint, and Larastan/PHPStan on every push and pull request.
- AGPL-3.0 license.

[1.2.0]: https://github.com/tanzibhossain/school-management-system/compare/v1.0.1...v1.2.0
[1.0.1]: https://github.com/tanzibhossain/school-management-system/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/tanzibhossain/school-management-system/releases/tag/v1.0.0
