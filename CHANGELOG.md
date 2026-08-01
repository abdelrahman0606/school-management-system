# Changelog

All notable changes to this project are documented here. Format loosely
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
follows [Semantic Versioning](https://semver.org/).

## [1.4.0] — 2026-07-31

### Added
- Multi-language support for pages, menus, school info, staff, departments, and announcements.
- AI-assisted translation button, for both website content and admin UI text.
- Automatic Bengali date and digit formatting across the public site.
- Bilingual (English + Bengali) sample data for the whole app, so it's ready to demo out of the box.
- Translation status indicators on the Staff, Department, Page, and Announcement lists.
- Sample data for features that had none before: certificates, ID cards, SMS, payroll, loans,
  refunds, holidays, and contact messages.
- Refreshed and cleaned up Bengali translations using real usage data.

### Fixed
- Your own admin language setting no longer changes what visitors see on the public site.
- Fixed a crash when browsing the admin panel in Bengali.
- Fixed a bug where AI-translating a menu could scramble its order.
- Several public-site details (dates, phone numbers, addresses, the admission form) were ignoring
  the language switcher — now translated properly.
- Staff and announcement translations weren't showing up on the public site.
- The AI-translate button no longer closes your edit form unexpectedly.
- General code-quality cleanup: fixed test failures and static-analysis warnings.
- Cleaned up roughly 30 incorrect or awkward Bengali translations.
- Some admission form labels stayed in English under Bengali — now translate correctly.
- Cleaned up duplicate entries in the translation system.

### Changed
- Removed a redundant "copy" button now that AI-translate does the same job.

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
