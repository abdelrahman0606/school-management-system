<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ ($appIsRtl ?? false) ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $s = $settings ?? null;
        $primary = $s->primary_color ?? '#1d4ed8';
        $accent = $s->accent_color ?? '#f59e0b';
        $heading = $s->heading_color ?? '#0f172a';
        $siteName = $s->site_name ?? ($school->name ?? 'Our School');
        $faviconUrl = \App\Support\Media::url($s->favicon ?? null);
        // Per-page SEO overrides (page.blade.php's 'meta_description'/'og_image'
        // sections, only defined when the page has its own value set) win over
        // the site-wide defaults from Website > Settings — never both at once.
        $metaDesc = trim((string) $__env->yieldContent('meta_description', $s->meta_description ?? '')) ?: null;
        $ogUrl = \App\Support\Media::url(trim((string) $__env->yieldContent('og_image', '')) ?: ($s->og_image ?? null));
        // Computed once and reused for <title>/og:title/twitter:title so all three
        // can never drift from each other (yieldContent is idempotent, but a single
        // source of truth is clearer than three identical @yield calls).
        $pageTitle = trim((string) $__env->yieldContent('title', ($s->meta_title ?? null) ?: $siteName)) ?: $siteName;
      @endphp
    <title>{{ $pageTitle }}</title>
    @if ($metaDesc)
    <meta name="description" content="{{ $metaDesc }}">@endif
    {{-- Falls back to the generic placeholder favicon until a school uploads its own. --}}
    <link rel="icon" href="{{ $faviconUrl ?: asset('favicon.ico') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($metaDesc)
    <meta property="og:description" content="{{ $metaDesc }}">@endif
    @if ($ogUrl)
    <meta property="og:image" content="{{ $ogUrl }}">@endif
    <meta property="og:type" content="website">
    {{-- Twitter Card — same per-page/site-wide precedence as the Open Graph tags
         above, reusing the same computed values so both platforms always agree.
         summary_large_image only makes sense once there's an actual image; a
         plain "summary" card degrades gracefully when a page has no og image. --}}
    <meta name="twitter:card" content="{{ $ogUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    @if ($metaDesc)
    <meta name="twitter:description" content="{{ $metaDesc }}">@endif
    @if ($ogUrl)
    <meta name="twitter:image" content="{{ $ogUrl }}">@endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --brand:
                {{ $primary }}
            ;
            --brand-accent:
                {{ $accent }}
            ;
            --brand-heading:
                {{ $heading }}
            ;
            /* Neutral + motion tokens — a small, deliberately minimal scale
               shared by every element below instead of one-off values, so a
               future tweak (e.g. a rounder or flatter look) is a handful of
               edits here rather than a hunt through every rule. */
            --ink: #1f2937;
            --ink-muted: #64748b;
            --border: #e5e7eb;
            --radius-sm: .5rem;
            --radius-md: .75rem;
            --shadow-sm: 0 1px 2px rgba(16, 24, 40, .06), 0 1px 3px rgba(16, 24, 40, .05);
            --shadow-md: 0 8px 24px rgba(16, 24, 40, .09), 0 2px 6px rgba(16, 24, 40, .05);
            --ease: cubic-bezier(.4, 0, .2, 1);
            --transition-fast: .15s;
            --transition: .25s;
        }

        /* Bootstrap 5.3's shipped spacer scale stops at 5 (3rem) — these
           templates want one more, roomier step at the lg breakpoint for
           section padding, without pulling in a custom Sass build just for
           one extra utility class. 992px matches Bootstrap's own lg
           breakpoint exactly. */
        @media (min-width: 992px) {

            .py-lg-6 {
                padding-top: 5rem !important;
                padding-bottom: 5rem !important;
            }

            .p-lg-6 {
                padding: 5rem !important;
            }
        }

        body {
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        .text-muted {
            color: var(--ink-muted) !important;
        }

        a {
            color: var(--brand);
            transition: color var(--transition-fast) var(--ease);
        }

        a:hover {
            color: color-mix(in srgb, var(--brand) 80%, black);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--brand) !important;
        }

        /* Every Bootstrap button gets the same restrained hover treatment —
           a 1px lift + soft shadow, not a color/scale change, so it reads as
           "responsive to touch" without calling attention to itself. */
        .btn {
            transition: filter var(--transition-fast) var(--ease),
                        transform var(--transition-fast) var(--ease),
                        box-shadow var(--transition-fast) var(--ease),
                        background-color var(--transition-fast) var(--ease),
                        border-color var(--transition-fast) var(--ease);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .btn-brand:hover {
            filter: brightness(.92);
            color: #fff;
            box-shadow: var(--shadow-sm);
        }

        .text-brand {
            color: var(--brand);
        }

        /* Sticky nav: a permanent, soft separation from the content below —
           avoids a scroll listener just to add a shadow once the page moves. */
        .navbar.sticky-top {
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }

        .navbar .nav-link {
            position: relative;
            transition: opacity var(--transition-fast) var(--ease);
        }

        /* Uses the school's configured accent color (Website > Settings) —
           previously declared as --brand-accent but never actually rendered
           anywhere on the public site. */
        .navbar .nav-link::after {
            content: '';
            position: absolute;
            left: .5rem;
            right: .5rem;
            bottom: 2px;
            height: 2px;
            background: var(--brand-accent);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform var(--transition) var(--ease);
        }

        .navbar .nav-link:hover::after {
            transform: scaleX(1);
        }

        .dropdown-menu {
            border-radius: var(--radius-sm);
            border-color: var(--border);
            box-shadow: var(--shadow-md);
        }

        /* Branded focus states — form controls otherwise fall back to
           Bootstrap's default blue ring, which clashes on any school whose
           brand color isn't blue. */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--brand) 20%, transparent);
        }

        .form-check-input:checked {
            background-color: var(--brand);
            border-color: var(--brand);
        }

        .hero {
            background: linear-gradient(135deg, var(--brand), color-mix(in srgb, var(--brand) 70%, #000));
            color: #fff;
            animation: pub-hero-in .5s var(--ease) both;
        }

        .hero h1 {
            color: #fff;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        @keyframes pub-hero-in {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Pill-shaped CTAs read as more contemporary than Bootstrap's default
           squared-off large buttons — scoped to the hero only, everywhere
           else keeps the standard button radius. */
        .hero .btn-lg {
            border-radius: 50rem;
            padding-inline: 1.75rem;
        }

        /* Small uppercase label above a heading — "Welcome", "What's New",
           etc. Purely decorative, no new editable field: the text is a
           literal in the template, not admin-configurable. */
        .eyebrow {
            display: inline-block;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--brand);
        }

        .hero .eyebrow {
            color: rgba(255, 255, 255, .85);
            background: rgba(255, 255, 255, .14);
            border-radius: 50rem;
            padding: .3rem .9rem;
        }

        /* Frosted stat tiles inside the hero — replaces the plain white
           boxes that fought visually with the gradient background. */
        .stat-glass {
            background: rgba(255, 255, 255, .12);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: var(--radius-md);
            color: #fff;
        }

        .stat-glass .stat-num {
            color: #fff;
        }

        /* Staff avatars: a soft ring instead of a plain border, with a
           gentle scale on hover — respects prefers-reduced-motion via the
           existing .btn/.card/.hero rule below (grouped in with the rest
           of this file's transform-based hovers). */
        .avatar-ring {
            box-shadow: 0 0 0 3px #fff, 0 0 0 5px color-mix(in srgb, var(--brand) 25%, transparent);
            transition: transform var(--transition) var(--ease);
        }

        .avatar-ring:hover {
            transform: scale(1.06);
        }

        .notice-icon {
            width: 2.25rem;
            height: 2.25rem;
            background: color-mix(in srgb, var(--brand) 10%, transparent);
            color: var(--brand);
            border-radius: 50%;
        }

        /* "View all" style links with an arrow that nudges forward on
           hover — used wherever a section links out to its full listing. */
        .link-arrow {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-weight: 600;
            text-decoration: none;
        }

        .link-arrow i {
            transition: transform var(--transition) var(--ease);
        }

        /* Baseline typography for arbitrary rich-text HTML (the Richtext and
           Image+Text blocks' WYSIWYG output) — previously completely
           unstyled: a pasted <h2>/<p>/<ul> rendered with bare browser
           defaults, no relationship to the rest of the page's type scale. */
        .prose :where(h1, h2, h3, h4, h5, h6) {
            color: var(--brand-heading);
            font-weight: 700;
            margin-top: 1.5em;
            margin-bottom: .5em;
        }

        .prose :where(h1, h2, h3, h4, h5, h6):first-child {
            margin-top: 0;
        }

        .prose p {
            margin-bottom: 1em;
        }

        .prose ul,
        .prose ol {
            margin-bottom: 1em;
            padding-left: 1.25em;
        }

        .prose img {
            max-width: 100%;
            border-radius: var(--radius-md);
        }

        .prose a {
            text-decoration-color: color-mix(in srgb, var(--brand) 40%, transparent);
            text-underline-offset: .15em;
        }

        .link-arrow:hover i {
            transform: translateX(3px);
        }

        .cta-panel {
            background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 8%, #fff), color-mix(in srgb, var(--brand-accent) 10%, #fff));
            border-radius: 1.5rem;
        }

        /* Video/map embeds get the same soft shadow as everything else, but
           deliberately no hover-zoom like .img-zoom — scaling a video
           player or map on hover looks broken while its own controls are
           visible. */
        .media-shadow {
            box-shadow: var(--shadow-sm);
        }

        /* Same visual formula as .notice-icon, kept as a separate class
           rather than renaming that one — used for the contact block's
           address/phone/email icons instead of the plain inline icon they
           had before. */
        .icon-badge {
            width: 2.25rem;
            height: 2.25rem;
            background: color-mix(in srgb, var(--brand) 10%, transparent);
            color: var(--brand);
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Uppercase section labels inside the Admission Form block ("Student
           Information", "Parent Information", …) — a thin rule instead of
           just floating text gives a long multi-section form some visual
           rhythm to break it up. */
        .form-section-label {
            padding-bottom: .5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            letter-spacing: .04em;
        }

        /* Small stat tiles (the Stats block — distinct from the hero's
           .stat-glass, which only makes sense on the hero's own gradient
           background) get a lift on hover like any other small tile. */
        .stat-tile {
            border-radius: var(--radius-md);
            transition: transform var(--transition) var(--ease), box-shadow var(--transition) var(--ease);
        }

        .stat-tile:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Gentle zoom-on-hover for content images (Image, Image+Text, and
           Gallery blocks) — the wrapper clips the zoom, the image itself is
           what scales. inline-block (not block) deliberately: the Image
           block centers a naturally-sized image via its <figure>'s
           text-align:center, and a full-width block wrapper here would put
           the shadow/radius around that whole invisible full-width box
           instead of hugging the actual (often narrower) image. */
        .img-zoom {
            display: inline-block;
            overflow: hidden;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            max-width: 100%;
        }

        .img-zoom img {
            display: block;
            max-width: 100%;
            transition: transform .4s var(--ease);
        }

        .img-zoom:hover img {
            transform: scale(1.04);
        }

        /* Linked Icon blocks get the same restrained hover feedback as
           everything else clickable on this page. */
        .icon-link {
            display: inline-block;
            transition: transform var(--transition) var(--ease);
        }

        .icon-link:hover {
            transform: scale(1.08);
        }

        /* Section dividers (the Divider block, and any plain <hr> a
           richtext block's HTML might contain) read the same neutral
           border token as everything else instead of Bootstrap's default. */
        hr {
            border-color: var(--border);
            opacity: 1;
        }

        @media (prefers-reduced-motion: reduce) {

            .avatar-ring,
            .link-arrow i,
            .stat-tile,
            .img-zoom img,
            .icon-link {
                transition: none;
            }
        }

        .section-title {
            color: var(--brand-heading);
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .stat-num {
            color: var(--brand);
            font-weight: 700;
            font-size: 2.25rem;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .card {
            border: 0;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition) var(--ease);
        }

        /* Shadow-only elevation, not a translateY lift — the same .card class
           wraps small tiles (notice/staff cards) and the large admission-form
           card, and a lift reads as a "clickable tile" cue that only makes
           sense for the former. */
        .card:hover {
            box-shadow: var(--shadow-md);
        }

        footer {
            background: #0f172a;
            color: #cbd5e1;
        }

        footer a {
            color: #e2e8f0;
            text-decoration: none;
            transition: color var(--transition-fast) var(--ease);
        }

        footer a:hover {
            color: #fff;
            text-decoration: underline;
        }

        .pub-ticker {
            overflow: hidden;
            white-space: nowrap;
        }

        .pub-ticker-track {
            display: inline-block;
            padding-left: 100%;
            animation: pub-ticker 28s linear infinite;
        }

        .pub-ticker:hover .pub-ticker-track {
            animation-play-state: paused;
        }

        @keyframes pub-ticker {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        /* Block "entrance animation" presets (Style tab) — deliberately minimal:
           a short opacity/translate fade, once, the first time a block scrolls
           into view. Respects prefers-reduced-motion for accessibility. */
        .reveal {
            opacity: 0;
            transition: opacity .5s var(--ease), transform .5s var(--ease);
        }

        .reveal-up {
            transform: translateY(20px);
        }

        .reveal.is-visible {
            opacity: 1;
            transform: none;
        }

        @media (prefers-reduced-motion: reduce) {

            .hero,
            .btn,
            .card,
            .navbar .nav-link::after {
                transition: none;
                animation: none;
            }

            /* .reveal needs its own rule, not just transition:none — without
               forcing opacity/transform back to their visible end-state, an
               element that hasn't been caught by the JS observer yet (or
               whose IntersectionObserver never fires, e.g. JS disabled)
               would stay permanently invisible at opacity:0. */
            .reveal {
                opacity: 1;
                transform: none;
                transition: none;
                animation: none;
            }
        }

        @if ($s?->custom_css ?? false)
            {!! $s->custom_css !!}
        @endif

        /* Admin live-preview click-to-select — only active when this page is
           rendered inside the editor's iframe (see the gated script below and
           docs/modules/28-elementor-block-editor-plan.md Milestone 4). Inert
           otherwise: no visual effect on the real public site. [data-block-path]
           marks every editor-addressable block, top-level AND nested (see §7g). */
        body.is-editor-preview [data-block-path] { cursor: pointer; }
        body.is-editor-preview .is-block-hover { outline: 2px dashed #6c8fff; outline-offset: -2px; }
        body.is-editor-preview .is-block-selected { outline: 2px solid var(--brand); outline-offset: -2px; }
        /* In-canvas drag-and-drop reordering/nesting + right-click context
           menu — see the gated script below. */
        body.is-editor-preview [data-block-path] { cursor: grab; }
        body.is-editor-preview [data-block-path].is-dragging { opacity: .35; cursor: grabbing; }
        body.is-editor-preview [data-block-path].drop-before { box-shadow: inset 0 3px 0 0 var(--brand); }
        body.is-editor-preview [data-block-path].drop-after { box-shadow: inset 0 -3px 0 0 var(--brand); }
        /* Hovering a Container/Grid's own body (not near a sibling's top/
           bottom edge) while dragging — "drop INSIDE this" instead of
           "insert next to this". */
        body.is-editor-preview [data-block-path].drop-into { outline: 2px dashed var(--brand); outline-offset: -4px; background: color-mix(in srgb, var(--brand) 8%, transparent); }
        #editor-context-menu button:hover { background: #f1f3f5; }
        /* Dragging a new block in from the editor's Add Block panel (see the
           gated script below) — a subtle tint over the whole canvas so it's
           clear this is a valid drop target even before hovering a block. */
        body.is-editor-preview.is-external-drag-over { background: color-mix(in srgb, var(--brand) 5%, #fff); }
    </style>
</head>

<body>
    @include('public.partials.header')

    @yield('content')

    <footer class="py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-5">
                    <h5 class="text-white mb-2">{{ $siteName }}</h5>
                    @if ($school?->address ?? false)
                    <p class="mb-1 small"><i class="bi bi-geo-alt"></i> {{ $school->address }}</p>@endif
                    @if ($school?->email ?? false)
                    <p class="mb-0 small"><i class="bi bi-envelope"></i> {{ $school->email }}</p>@endif
                </div>
                <div class="col-md-4">
                    <h6 class="text-white-50 text-uppercase small mb-2">{{ __('Quick Links') }}</h6>
                    <div class="d-flex flex-column gap-1 small">
                        <a href="{{ route('home') }}#notices">{{ __('Notices') }}</a>
                        <a href="{{ route('home') }}#results">{{ __('Check Results') }}</a>
                        <a href="{{ route('login') }}">{{ __('Portal Login') }}</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white-50 text-uppercase small mb-2">{{ __('Portal') }}</h6>
                    <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">{{ __('Sign In') }}</a>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <p class="small mb-0 text-center text-white-50">© {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reveal blocks with a Style-tab "entrance animation" once, the first
        // time they scroll into view. No-op (blocks just render fully visible)
        // if IntersectionObserver isn't available or the user prefers reduced motion.
        (function () {
            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var els = document.querySelectorAll('.reveal');
            if (reduced || !('IntersectionObserver' in window)) {
                els.forEach(function (el) { el.classList.add('is-visible'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: .15, rootMargin: '0px 0px -10% 0px' });
            els.forEach(function (el) { io.observe(el); });
        })();

        // Editor click-to-select bridge — no-op unless this document is
        // actually sitting inside the admin page-builder's preview iframe.
        // See resources/views/admin/website/pages/edit.blade.php for the
        // parent-side listener.
        (function () {
            if (window.self === window.top) return;
            document.body.classList.add('is-editor-preview');

            // ── Path helpers ──────────────────────────────────────────────────
            // A block's address is its data-block-path ("2" for the 3rd
            // top-level block, "2,0" for its 1st child, "2,0,1" for that
            // child's 2nd child, …) — see §7g in
            // docs/modules/28-elementor-block-editor-plan.md. The LAST segment
            // of a block's own path is always its index among its own
            // siblings (the server renders children in array order), which is
            // what makes reorder/insert math below simple: no separate
            // sibling-index lookup needed, it's already encoded in the path.
            function parsePath(str) { return str.split(',').map(function (s) { return parseInt(s, 10); }); }
            function pathsEqual(a, b) { return a.length === b.length && a.every(function (v, i) { return v === b[i]; }); }
            // True if `path` IS `ancestorPath` or nested under it — used to
            // stop a container from being dropped into its own descendant.
            function isWithin(path, ancestorPath) {
                if (path.length < ancestorPath.length) return false;
                for (var i = 0; i < ancestorPath.length; i++) if (path[i] !== ancestorPath[i]) return false;
                return true;
            }

            // ── Accessibility: every selectable block gets a real role/label ──
            // Editor-only (this whole bridge already returns early on the
            // real public site — see the guard at the top of this IIFE) — a
            // screen-reader or keyboard-only admin could otherwise only tell
            // blocks apart by the (also editor-only, purely visual) hover
            // outline, which isn't announced at all, and couldn't reach a
            // block without a mouse. A MutationObserver rather than a
            // one-time pass at load: blocks appear/move via a full iframe
            // reload, the per-block fast-preview patch (runBlockPreview(),
            // edit.blade.php), AND canvas drag-and-drop — one self-maintaining
            // observer covers all three instead of re-running labeling by
            // hand after every one of them.
            function labelBlock(el) {
                if (el.hasAttribute('tabindex')) return; // already labeled
                el.setAttribute('tabindex', '0');
                el.setAttribute('role', 'button');
                el.setAttribute('aria-label', @json(__('Select block')) + ': ' + (el.dataset.blockType || 'block'));
            }
            function labelAllBlocks(root) {
                if (root.nodeType !== 1) return;
                if (root.matches('[data-block-path]')) labelBlock(root);
                root.querySelectorAll('[data-block-path]').forEach(labelBlock);
            }
            labelAllBlocks(document.body);
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(labelAllBlocks);
                });
            }).observe(document.body, { childList: true, subtree: true });
            // Enter/Space activates a focused block exactly like a click —
            // required once a role="button" is added above (an interactive
            // role with no keyboard handler is worse than not labeling it at
            // all). Reuses the click handler's own selection logic via a
            // synthetic .click() rather than duplicating it.
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var el = e.target.closest && e.target.closest('[data-block-path]');
                if (!el || el !== document.activeElement) return;
                e.preventDefault();
                el.click();
            });

            var selected = null;
            document.addEventListener('mouseover', function (e) {
                var el = e.target.closest('[data-block-path]');
                document.querySelectorAll('.is-block-hover').forEach(function (n) {
                    if (n !== el) n.classList.remove('is-block-hover');
                });
                if (el) el.classList.add('is-block-hover');
            });
            document.addEventListener('mouseout', function (e) {
                var el = e.target.closest('[data-block-path]');
                if (el) el.classList.remove('is-block-hover');
            });
            // Capture phase: intercept before a link/form inside the block
            // gets to act — this is a preview, clicks should select, not
            // navigate. closest() naturally resolves to the DEEPEST matching
            // element under the cursor, so clicking a nested child selects
            // THAT child, not its container — nested blocks are just as
            // click-selectable as top-level ones, no extra logic needed.
            document.addEventListener('click', function (e) {
                var el = e.target.closest('[data-block-path]');
                if (!el) {
                    // Clicked the canvas background, not a block — tell the
                    // parent so it can collapse the sidebar back to its
                    // default panel (see edit.blade.php's click-outside handling).
                    if (selected) { selected.classList.remove('is-block-selected'); selected = null; }
                    window.parent.postMessage({ source: 'page-preview', type: 'deselect' }, '*');
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (selected) selected.classList.remove('is-block-selected');
                selected = el;
                el.classList.add('is-block-selected');
                // Posted with '*': this document is loaded via iframe.srcdoc, so
                // window.location.origin here serializes as the literal string
                // "null" (a known browser quirk for srcdoc documents) — passing
                // that as a targetOrigin throws "Invalid target origin 'null'".
                // The parent verifies the sender by e.source (the iframe's
                // contentWindow), not by origin, so this stays safe without it.
                window.parent.postMessage({
                    source: 'page-preview',
                    type: 'select-block',
                    group: el.dataset.blockGroup,
                    path: parsePath(el.dataset.blockPath),
                }, '*');
            }, true);

            // ── In-canvas drag-and-drop: reorder, or drop INTO a container ───
            // Native HTML5 DnD (draggable="true" is set server-side in this
            // editor-preview context only — see public/blocks/render.blade.php
            // and public/sidebar/render.blade.php). Dragging is confined to a
            // single group (main blocks vs sidebar blocks are separate arrays,
            // and nesting never crosses that boundary either). One shared
            // 'move-block' message (below) covers plain sibling reordering,
            // dropping a block INSIDE a container/grid, and pulling a nested
            // child back OUT to a shallower level — they're all "move this
            // path to this position under that parent" to the parent editor,
            // which owns the actual DOM (the source of truth) and never
            // trusts this iframe to move anything itself.
            var dragSrc = null;
            function clearDropMarkers() {
                document.querySelectorAll('.drop-before, .drop-after, .drop-into').forEach(function (n) {
                    n.classList.remove('drop-before', 'drop-after', 'drop-into');
                });
            }
            document.addEventListener('dragstart', function (e) {
                var el = e.target.closest('[data-block-path]');
                if (!el) return;
                dragSrc = el;
                el.classList.add('is-dragging');
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', el.dataset.blockPath); } catch (err) {}
                }
            });
            document.addEventListener('dragend', function () {
                if (dragSrc) dragSrc.classList.remove('is-dragging');
                clearDropMarkers();
                dragSrc = null;
            });
            // ── Drag a NEW block in from the editor's Add Block panel ────────
            // The drag source lives in the PARENT document's sidebar (see
            // edit.blade.php), not in here — HTML5 dragstart/dragover/drop
            // fire across the iframe boundary natively (it's a browser-level
            // gesture, not restricted by same-origin/sandbox the way script
            // access is), but dataTransfer.getData() can only be READ on
            // drop, never during dragover (a spec-level security
            // restriction) — so during dragover we only know an external
            // add-block drag is in progress (via .types, which IS readable
            // early) and show a generic insertion indicator; the actual
            // group/type payload is read once, on drop.
            function isExternalBlockDrag(e) {
                return !dragSrc && !!e.dataTransfer
                    && Array.prototype.indexOf.call(e.dataTransfer.types, 'application/x-block-type') !== -1;
            }
            // Shared by both drag kinds (reposition an existing block, or add
            // a brand-new one): given the element currently under the
            // pointer, decide whether this is "insert as a sibling before/
            // after it" or — if it's a Container/Grid and the pointer isn't
            // near its top/bottom edge — "drop AS A CHILD of it". Returns
            // {toParentPath, toIndex} in both cases, ready to hand straight
            // to a move-block/add-block-at message; null if there's no valid
            // target under the pointer at all (drop => append at group root).
            function classifyDropTarget(el, clientY) {
                if (!el) return null;
                var path = parsePath(el.dataset.blockPath);
                var rect = el.getBoundingClientRect();
                var relY = clientY - rect.top;
                var isContainer = el.dataset.blockType === 'container' || el.dataset.blockType === 'grid';
                var edge = Math.min(24, rect.height * 0.25);
                if (isContainer && relY > edge && relY < rect.height - edge) {
                    return { mode: 'into', el: el, before: null, toParentPath: path, toIndex: null };
                }
                var before = relY < rect.height / 2;
                return {
                    mode: 'sibling', el: el, before: before,
                    toParentPath: path.slice(0, -1),
                    toIndex: path[path.length - 1] + (before ? 0 : 1),
                };
            }
            function markDropTarget(target) {
                clearDropMarkers();
                if (!target) return;
                target.el.classList.add(target.mode === 'into' ? 'drop-into' : (target.before ? 'drop-before' : 'drop-after'));
            }
            document.addEventListener('dragover', function (e) {
                if (dragSrc) {
                    var el = e.target.closest('[data-block-path]');
                    var group = dragSrc.dataset.blockGroup;
                    var dragPath = parsePath(dragSrc.dataset.blockPath);
                    if (!el || el === dragSrc || el.dataset.blockGroup !== group) { clearDropMarkers(); return; }
                    var targetPath = parsePath(el.dataset.blockPath);
                    // Can't drop a block into (or next to, in a way that's
                    // really "into") its own descendant subtree.
                    if (isWithin(targetPath, dragPath)) { clearDropMarkers(); return; }
                    e.preventDefault();
                    markDropTarget(classifyDropTarget(el, e.clientY));
                    return;
                }
                if (!isExternalBlockDrag(e)) return;
                e.preventDefault();
                document.body.classList.add('is-external-drag-over');
                var el = e.target.closest('[data-block-path]');
                markDropTarget(el ? classifyDropTarget(el, e.clientY) : null);
            });
            document.addEventListener('dragleave', function (e) {
                // relatedTarget is null when the pointer leaves the document
                // entirely (vs. just moving between two child elements).
                if (e.relatedTarget === null) {
                    clearDropMarkers();
                    document.body.classList.remove('is-external-drag-over');
                }
            });
            document.addEventListener('drop', function (e) {
                document.body.classList.remove('is-external-drag-over');
                if (dragSrc) {
                    var el = e.target.closest('[data-block-path]');
                    var group = dragSrc.dataset.blockGroup;
                    var dragPath = parsePath(dragSrc.dataset.blockPath);
                    if (!el || el === dragSrc || el.dataset.blockGroup !== group) return;
                    var targetPath = parsePath(el.dataset.blockPath);
                    if (isWithin(targetPath, dragPath)) return;
                    e.preventDefault();
                    var target = classifyDropTarget(el, e.clientY);
                    clearDropMarkers();
                    window.parent.postMessage({
                        source: 'page-preview', type: 'move-block', group: group,
                        fromPath: dragPath, toParentPath: target.toParentPath, toIndex: target.toIndex,
                    }, '*');
                    return;
                }
                if (!e.dataTransfer) return;
                var raw = e.dataTransfer.getData('application/x-block-type') || e.dataTransfer.getData('text/plain');
                if (!raw) return;
                var payload;
                try { payload = JSON.parse(raw); } catch (err) { return; }
                if (!payload || !payload.type || !payload.group) return;
                e.preventDefault();
                var el = e.target.closest('[data-block-path]');
                // Only honor the hovered insertion point if it's the SAME
                // group as what's being dropped (you can't insert a
                // Sidebar-only block type among main content blocks, or vice
                // versa) — otherwise fall back to appending at the end of
                // the correct group's root, same as clicking the picker item
                // would.
                var target = (el && el.dataset.blockGroup === payload.group) ? classifyDropTarget(el, e.clientY) : null;
                clearDropMarkers();
                window.parent.postMessage({
                    source: 'page-preview',
                    type: 'add-block-at',
                    group: payload.group,
                    blockType: payload.type,
                    toParentPath: target ? target.toParentPath : [],
                    toIndex: target ? target.toIndex : null,
                }, '*');
            });

            // ── Right-click context menu: Copy Style / Paste Style / Delete ──
            // A small hand-rolled menu (Bootstrap is loaded in this document,
            // but its dropdown JS is built around a trigger element, not an
            // arbitrary cursor position, so a plain absolutely-positioned menu
            // is simpler here). Actions are dispatched to the parent, which
            // already owns the copy/paste-style clipboard and block removal.
            // Element that had focus just before the menu opened — restored
            // when the menu closes via an explicit action (Escape, or
            // choosing a menu item), matching the WAI-ARIA APG menu-button
            // pattern ("focus is typically returned to the element that had
            // focus before the menu opened"). Deliberately NOT restored on a
            // plain outside click/scroll dismissal: a click may have landed
            // on a genuinely focusable element inside a block (a contact
            // form field, a button block's link), and forcing focus back to
            // the pre-menu element in that case would steal it right back
            // out from under whatever the user just clicked.
            var lastFocusedBeforeMenu = null;
            function closeContextMenu(restoreFocus) {
                var m = document.getElementById('editor-context-menu');
                if (!m) return;
                m.remove();
                if (restoreFocus && lastFocusedBeforeMenu && document.body.contains(lastFocusedBeforeMenu)
                    && typeof lastFocusedBeforeMenu.focus === 'function') {
                    lastFocusedBeforeMenu.focus();
                }
                lastFocusedBeforeMenu = null;
            }
            // ARIA menu semantics + Escape-to-close: everything this menu can
            // do (Copy/Paste Style, Remove) also has a real keyboard-operable
            // equivalent in the sidebar (Style tab's Copy/Paste Style
            // buttons, the rail's Remove button — see _card.blade.php/
            // _style_fields.blade.php), so this right-click shortcut isn't
            // the only way to reach these actions; it's still given proper
            // menu/menuitem roles and an Escape handler so a screen reader
            // that DOES land on it (e.g. via the OS/browser's own
            // Shift+F10-style context-menu key) announces and can dismiss it
            // correctly, rather than reading it as unlabeled plain buttons.
            document.addEventListener('contextmenu', function (e) {
                var el = e.target.closest('[data-block-path]');
                // Captured BEFORE closing any already-open menu, so a
                // right-click that lands on a new block still remembers
                // whatever had focus prior to the FIRST menu opening (not
                // a button inside that menu, which is about to be removed).
                var active = document.activeElement;
                closeContextMenu(false);
                if (!el) return;
                e.preventDefault();
                lastFocusedBeforeMenu = (active && active !== document.body) ? active : null;
                var menu = document.createElement('div');
                menu.id = 'editor-context-menu';
                menu.className = 'shadow-sm';
                menu.setAttribute('role', 'menu');
                menu.setAttribute('aria-label', @json(__('Block actions')));
                menu.style.cssText = 'position:fixed;z-index:99999;background:#fff;border:1px solid rgba(0,0,0,.15);'
                    + 'border-radius:.375rem;min-width:170px;padding:.25rem 0;font-family:system-ui,-apple-system,sans-serif;font-size:.875rem;';
                [
                    { action: 'copy', icon: 'bi-clipboard', label: @json(__('Copy Style')) },
                    { action: 'paste', icon: 'bi-clipboard-check', label: @json(__('Paste Style')) },
                    { action: 'delete', icon: 'bi-trash', label: @json(__('Remove')), danger: true },
                ].forEach(function (a) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.setAttribute('role', 'menuitem');
                    item.className = 'btn btn-sm w-100 text-start border-0 rounded-0 d-flex align-items-center gap-2 px-3 py-1' + (a.danger ? ' text-danger' : '');
                    item.innerHTML = '<i class="bi ' + a.icon + '" aria-hidden="true"></i> ' + a.label;
                    item.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        window.parent.postMessage({
                            source: 'page-preview', type: 'context-action', action: a.action,
                            group: el.dataset.blockGroup, path: parsePath(el.dataset.blockPath),
                        }, '*');
                        closeContextMenu(true);
                    });
                    menu.appendChild(item);
                });
                document.body.appendChild(menu);
                var rect = menu.getBoundingClientRect();
                var left = e.clientX, top = e.clientY;
                if (left + rect.width > window.innerWidth) left = window.innerWidth - rect.width - 8;
                if (top + rect.height > window.innerHeight) top = window.innerHeight - rect.height - 8;
                menu.style.left = Math.max(4, left) + 'px';
                menu.style.top = Math.max(4, top) + 'px';
                var firstItem = menu.querySelector('button');
                if (firstItem) firstItem.focus();
            }, true);
            document.addEventListener('click', function () { closeContextMenu(false); });
            document.addEventListener('scroll', function () { closeContextMenu(false); }, true);
            // Escape is the one dismissal that always restores focus — the
            // standard keyboard "back out" gesture, matching every other
            // Escape handler already in this app (sidebar panels, modals).
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeContextMenu(true);
            });
        })();
    </script>
</body>

</html>