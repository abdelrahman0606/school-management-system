# 29 — Public Frontend Modernization Proposal

Status: **proposal, awaiting sign-off** (no code changed by this document). Once approved, implementation
follows in phased branches per the plan in §7, each merged with the usual commit workflow.

## 1. Why this is being proposed

The public site (`resources/views/public/`) sits on genuinely solid infrastructure — a recursive
Elementor-style block editor (`docs/modules/28-elementor-block-editor-plan.md`), per-block Style/Layout
tabs, drag-and-drop, live preview, reveal animations, `color-mix()`-based theming. The *code* isn't dated.
What reads as dated is the **visual output**: a dense three-row header, a single flat button-and-card
visual language everywhere, and a block catalog that covers content/media but not the trust-building and
conversion patterns modern school sites lean on (FAQ, testimonials, urgency banners, comparison/why-us).

Two concrete findings shaped this proposal:

- **`site_settings` already has ~20 theming columns that go nowhere.** The migration
  (`2026_07_04_994005_create_site_settings_table.php`) defines `secondary_color`, `background_color`,
  `surface_color`, `text_color`, `link_color`, `link_hover_color`, `border_color`, `font_heading`,
  `font_body`, `base_font_size`, `container_width`, `btn_radius`, `btn_font_weight`, `btn_transition_ms`,
  `btn_filled_json`, `btn_outline_json`, and a global background (`global_bg_type/color/image/overlay`).
  None of these are read by `public/layout.blade.php` (only `primary_color`/`accent_color`/`heading_color`
  are), and **none are editable in the admin** — `SchoolController::update()`'s Appearance section only
  whitelists `primary_color, accent_color, heading_color, topbar_text_color, ticker_position, meta_title,
  meta_description`. This is half-built theming infrastructure sitting dormant on both ends. Wiring it up is
  the single highest-leverage, lowest-risk item in this proposal — no migration, no new block, just
  connecting an existing column to an existing render path and an existing admin form.
- **The header is doing four jobs stacked vertically** (utility bar → logo/identity row → nav) before any
  actual content appears — a layout pattern common on 2015-era BD school sites, not what a visitor expects
  from a "modern" site today (sticky slim nav, content immediately below the fold). This is the biggest
  single driver of the "aging" impression and the first thing addressed below.

## 2. Design direction

**Principles**, applied consistently across the rework rather than as one-off tweaks:

- **One header row, not three.** Utility info (phone, date, language switcher) either collapses into the
  slim top row or moves into the footer; logo + nav share one row. Sticky on scroll, shrinks slightly once
  scrolled (logo/nav padding reduces) rather than staying full-height forever.
- **A real type scale**, not ad-hoc `h1`/`h3`/`.lead` sizes. `font_heading`/`font_body`/`base_font_size`
  (already in the schema) drive a `clamp()`-based fluid scale (e.g. hero `clamp(2.25rem, 4vw+1rem, 3.5rem)`)
  so headings scale smoothly between mobile and desktop instead of jumping at breakpoints.
- **More breathing room, fewer competing accents.** Current section rhythm is already reasonable
  (`py-lg-6`); the gap is card density (`.card` used identically for notices/staff/stats) and no visual
  hierarchy between a "hero-adjacent" section and a "deep in the page" section. Introduce 2–3 section
  background treatments (plain, tinted, dark) blocks can opt into via the existing Style tab's `bg_color`,
  rather than every section defaulting to white.
- **Motion stays restrained** — the existing `.reveal` scroll-in fade and `prefers-reduced-motion` handling
  is good and is kept as-is, just applied to more of the new patterns below.
- **Everything ships through the existing Style/Layout tab system**, not new one-off CSS hooks — a new
  block type gets the same padding/margin/background/border/radius/shadow/animation controls every
  existing block already has, for free, via `BlockPresentation`.

### Header/nav rework

- Row 1 (topbar) shrinks to a thin strip with just phone + language switcher, or is dropped entirely on
  mobile (already partially true via Bootstrap responsive classes — make it deliberate).
  now on nights/weekends).
- Row 2 becomes logo + site name + nav all in one bar, with institution codes/established year moving to
  the footer (they're reference info, not navigation-critical — currently the *first* thing after the
  logo, ahead of the actual nav).
- Nav bar adds a visually distinct **CTA button** ("Apply Now" / "Admissions" — configurable, links to the
  Online Admission module) instead of nav items and Login competing for the same visual weight.
- New **announcement/sticky bar** (see §3, one of the two blocks the user prioritized) can sit above row 1 —
  dismissible, session-scoped via a cookie so it doesn't reappear every page load once closed.

### Theming wiring (no new schema)

1. Expose the ~17 dormant `SiteSetting` columns in the admin Appearance form (`SchoolController::update()`'s
   whitelist + a corresponding form section) — grouped as Colors / Typography / Buttons / Global Background,
   matching the migration's own section comments.
2. `public/layout.blade.php`'s `<style>` block reads all of them with the same `?? $default` fallback
   pattern already used for `primary`/`accent`/`heading`, so a school with nothing set renders identically
   to today (zero visual regression for schools that never touch Appearance settings).
3. `font_heading`/`font_body` load from Google Fonts (or self-hosted, TBD in implementation) only when set —
   otherwise the current system-font stack is kept, so no external request is added for schools that don't
   opt in.

## 3. New block catalog

**Building first** (the two the user flagged as priority):

| Block | Notes |
|---|---|
| **Announcement / sticky CTA bar** | Slim bar above the header. Text + optional link/button, dismissible (cookie-remembered per browser), optional color override via the block's existing Style tab `bg_color`. Distinct from the existing notice **ticker** (which scrolls a list of `Announcement` module records) — this is a single, admin-authored, high-intent message ("Admissions open for 2026-27", "Result published"), not a feed. |
| **FAQ accordion** | Repeatable Q/A list, Bootstrap 5 `.accordion` (no new JS dependency — accordion JS ships with `bootstrap.bundle.min.js`, already loaded). Optional grouping/heading, single-open-at-a-time. High-value on Admissions-type pages. |

**Proposed for a follow-up phase** (not built without further confirmation, listed here because the user
asked what I'd suggest — ranked by how directly each supports the "usability + modern feel" goal):

| Block | Why |
|---|---|
| Testimonials / reviews | Social proof; parents deciding on a school lean on this heavily. Single or carousel, optional photo + role ("Parent, Class 5"). |
| Events / calendar strip | Distinct from Notices — date-forward cards ("15 Aug — Annual Sports Day"), sorts by upcoming date. Currently there's no Academic-calendar-facing block at all. |
| Why-us / comparison tiles | Icon + short text tiles ("Small class sizes", "STEM lab", …) — currently the only way to convey this is `image_text` or `richtext`, neither is built for a scannable grid of value props. |
| Timeline / school history | Founding → milestones, common on About pages, currently no purpose-built layout for it. |
| Downloads / documents list | Prospectus, fee structure, syllabus PDFs — a titled list with file-type icons and size, currently would need raw `richtext` links. |
| CTA banner (mid-page) | Distinct from the sticky bar — a full-width section-level "Ready to enroll?" panel usable anywhere in a page, not just at the top. `home.blade.php`'s hand-coded `.cta-panel` section is exactly this pattern, just not available as a reusable block yet. |
| Map + directions card | `google_maps` exists but is a bare embed; this pairs it with an address/hours/"Get Directions" card, useful standalone on a Contact page without needing the full `contact` block. |

All of the above are purely additive: new entries in `PageRenderService::BLOCKS`/`LEAF_BLOCKS`/
`CATEGORIES`, a `@case` in `public/blocks/render.blade.php`, and a fields partial for the admin editor —
same pattern as every existing block. No migration, no change to `layout_json`'s shape, and they show up
immediately in the Add Block panel for every school without needing any existing page re-saved.

## 4. Homepage

`home.blade.php` (the hard-coded fallback used when a school has no published homepage layout yet) gets the
same visual-system updates as everything else (header, type scale, button styling) but keeps its current
section order (hero → notices → staff → results CTA) — that flow tests well for a school homepage and isn't
what's "aging" about it. The real, block-built homepage (`is_homepage` page, per CLAUDE.md module 20) is
unaffected structurally; schools already using it get the visual refresh automatically since it renders
through the same shared blocks/layout.

## 5. Rollout

Per your answer: **automatic for every school**, no opt-in flag. This is safe because:

- `layout_json` (stored block data) is never touched — only how the shared layout/CSS/block partials render
  it. A school's actual content (text, images, block order) is byte-for-byte the same before and after.
- Every themable value already degrades to today's current hardcoded default when unset — a school that
  never opens Website → Settings sees no change beyond the header/type-scale/spacing refresh that applies
  globally.
- No new `theme_version` flag to maintain forever, matching your stated preference for the simpler option.

The one deliberately-global change (not opt-in, not gradual) is the header/nav restructure and the base
type scale — those are structural, not per-school-configurable today, and re-implementing them as two
parallel header partials would roughly double the maintenance surface for a look nobody would choose to
keep once they'd seen the new one.

## 6. Non-goals (explicitly out of scope for this pass)

- No new database tables/columns beyond what already exists in `site_settings`.
- No change to the block editor's own UI mechanics (drag-and-drop, copy/paste style, nesting) — those are
  already solid per `docs/modules/28-elementor-block-editor-plan.md`.
- No dark mode toggle, no multi-theme picker (beyond the existing per-school color/font settings) — flagged
  as a possible future idea, not part of this rework.
- No changes to `old/` (doesn't exist in this checkout) or to any authenticated portal UI — public
  marketing site only.

## 7. Phased implementation plan

Each phase is its own branch/commit(s), tested and merged before the next starts, per the project's usual
workflow.

1. **Theming wiring** — expose the dormant `SiteSetting` columns end-to-end (admin form → service → public
   layout `<style>`). Lowest risk, highest leverage, no visual change until a school actually sets a value.
2. **Header/nav + type scale rework** — the one genuinely global visual change. Ships behind a quick manual
   pass on a couple of representative schools' data before merging (logo aspect ratios, long site names,
   RTL locale per module 26, since the header renders differently for `bn`/RTL).
3. **Announcement/sticky CTA bar block** + **FAQ accordion block** — the two priority blocks, following the
   existing 10-step-minus-migration pattern (data shape → sanitizer entry → render partial → admin fields
   partial → category placement → tests).
4. **CHANGELOG + docs** — update `CLAUDE.md`'s Website module row if the block catalog materially changes,
   and the module's own test suite (`tests/Feature/Website/` or wherever the block-render tests live) gets
   coverage for the two new block types, matching existing per-block test patterns.

Phase 2 is the one worth a screenshot/preview check before merging, given it's the only phase that changes
something every visitor sees on every page regardless of what a school has configured.

---

**Open question for you before I start Phase 1:** anything in the "proposed for a follow-up phase" table
in §3 you want pulled forward into this same pass, or is the priority-two (announcement bar + FAQ) plus the
header/theming rework the right scope for now?
