# Changelog

All notable changes to this project are documented here. Format loosely
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.3.3] — 2026-07-28

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
