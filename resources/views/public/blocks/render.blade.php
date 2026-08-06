@php
  // Local alias so the (fairly long) FQCN does not have to be repeated at
  // every call site below — `use` imports are not valid inside a compiled
  // Blade php block (it compiles to PHP function-body code, not file-top-level).
  $bp = \App\Modules\Website\Support\BlockPresentation::class;

  $contained = $contained ?? false;
  $style = $style ?? [];
  $layout = $layout ?? [];

  // Identifies this rendered block back to its editor row — used only by the
  // click-to-select/drag-reorder/context-menu/drag-into-container bridge in
  // the admin live-preview iframe (see public/layout.blade.php); inert data
  // attributes (and draggable is simply absent) on the real public site.
  // $path is a list of indices from the root ([2] for the 3rd top-level
  // block, [2,0] for its 1st child, [2,0,1] for the 2nd child of that
  // child, …) — recursive nesting needs more than a single flat index to
  // address a block, see §7g in docs/modules/28-elementor-block-editor-plan.md.
  $editorAttrs = isset($path)
    ? ' data-block-path="'.e(implode(',', $path)).'" data-block-group="'.e($group ?? 'blocks').'" data-block-type="'.e($type).'" draggable="true"'
    : '';

  // hero manages its own spacing+background entirely (its own inner
  // <div class="container"> wraps the actual text/button content, while the
  // outer <header> bleeds full-width for the background image/gradient) —
  // every other block type gets the standard section+container+default-
  // padding treatment, with the Style tab overrides applied on the same
  // wrapper element so a custom value cleanly replaces the default instead
  // of adding to it (inline style always wins over the py-4/py-lg-5 utility
  // classes). admission_form does NOT belong in this list — its content
  // (a <form class="card">) has no full-bleed background of its own and
  // never had an inner container to compensate, so being self-contained
  // just made it render edge-to-edge with no width constraint at all.
  $selfContained = in_array($type, ['hero'], true);
  // announcement_bar wants to read as a slim strip, not a full section with
  // the usual py-4/py-lg-5 breathing room — its own inner markup carries
  // whatever padding it needs (see the announcement_bar switch case below),
  // and it gets a default brand-colored background (.announcement-bar-section,
  // public/layout.blade.php) that a Style tab bg_color override still wins
  // over, same inline-beats-class mechanism every other block's Style tab
  // already relies on.
  $slimBlock = $type === 'announcement_bar';
  $wrap = $bp::wrapper($style, $layout);
  // The Statistics block applies its entrance animation per-element (the
  // heading and each tile individually, see the 'stats' case below)
  // instead of once on this whole section wrapper — a single wrapper-level
  // reveal here would fade the ENTIRE block in as one unit, then its
  // children would (redundantly, and visually broken-looking) fade in
  // again on top of that. Stripped only for this type; every other block
  // keeps the normal single wrapper-level reveal from BlockPresentation.
  if ($type === 'stats' && $wrap['class'] !== '') {
    $wrap['class'] = trim(preg_replace('/\s*\breveal(?:-\w+)?\b/', '', $wrap['class']));
  }
  $defaultSpacing = $selfContained || $slimBlock ? '' : ($contained ? 'mb-3' : 'py-4 py-lg-5');
  $typeClass = $slimBlock ? ' announcement-bar-section' : '';
  $wrapClass = trim($wrap['class'].' '.$defaultSpacing.$typeClass);
  $wrapStyleAttr = $wrap['style'] !== '' ? ' style="'.$wrap['style'].'"' : '';
  // Advanced tab "ID" field (§7ai) — already whitelisted character-by-
  // character in PageRenderService::sanitizeStyle() ($htmlId), so this is
  // just echoed, not re-escaped (e() would double-escape nothing here since
  // the value can never contain a quote/space/angle-bracket in the first
  // place, but it costs nothing and matches every other attribute below).
  $wrapIdAttr = $wrap['id'] !== '' ? ' id="'.e($wrap['id']).'"' : '';

  $open = $contained || $selfContained ? '' : '<div class="container">';
  $close = $contained || $selfContained ? '' : '</div>';
@endphp
@if ($contained)
  <div class="{{ $wrapClass }}"{!! $wrapStyleAttr !!}{!! $wrapIdAttr !!}{!! $editorAttrs !!}>
@else
  <section class="{{ $wrapClass }}"{!! $wrapStyleAttr !!}{!! $wrapIdAttr !!}{!! $editorAttrs !!}>
@endif
@switch($type)
  @case('hero')
    @php
      // Background is a universal, Advanced-tab-only, explicit three-way
      // either/or now (§7ah — 'bg_mode': 'image' | 'color' | 'gradient',
      // never more than one applied at once), applied to the outer SECTION
      // wrapper exactly like every other block, via the universal
      // BlockPresentation::wrapper() mechanism (no special-casing needed
      // there at all). What's special about hero is the inner
      // <header class="hero">: its own .hero class (layout.blade.php)
      // paints an opaque gradient (or, in 'image' mode, this case paints
      // the Content-tab image directly on the header too) that would
      // otherwise sit on TOP of the section's background and hide it
      // completely — a wrapper-level bg_color/gradient has always been
      // silently invisible on a hero block for exactly this reason. So
      // whenever 'color'/'gradient' mode actually has its value(s) set,
      // the header's own background is neutralized (background:none)
      // instead, letting the section's already-applied background show
      // through where the header used to paint over it. A page saved
      // before 'bg_mode' existed has it unset (null) and keeps the
      // original image-only behavior unchanged.
      $heroBgMode = $style['bg_mode'] ?? null;
      $heroBgNeutralize = ($heroBgMode === 'color' && ! empty($style['bg_color']))
        || ($heroBgMode === 'gradient' && ! empty($style['bg_gradient_start']) && ! empty($style['bg_gradient_end']));
      // Bootstrap's .text-white-50 is !important — can't be beaten by a
      // plain inline style, so it's dropped entirely (not fought) only once
      // a real subtitle_color override is set; same trick as stats'
      // tile-subtext and staff's designation color above.
      $heroSubtitleOverride = ! empty($style['subtitle_color']);
    @endphp
    <header class="hero py-5 py-lg-6"
      @if($heroBgNeutralize)
        style="background:none;"
      @elseif(($heroBgMode === 'image' || $heroBgMode === null) && !empty($d['image']))
        style="background-image:linear-gradient(rgba(0,0,0,.45),rgba(0,0,0,.45)),url('{{ $d['image'] }}');background-size:cover;background-position:center;"
      @endif
    >
      <div class="container py-4 py-lg-5 text-center">
        <h1 class="display-4 mb-3"@if(!empty($style['heading_color'])) style="color:{{ $style['heading_color'] }}"@endif>{{ $d['title'] ?? '' }}</h1>
        @if(!empty($d['subtitle']))
          <p class="lead{{ $heroSubtitleOverride ? '' : ' text-white-50' }} mx-auto" style="max-width:42rem;{{ $heroSubtitleOverride ? 'color:'.$style['subtitle_color'].';' : '' }}">{{ $d['subtitle'] }}</p>
        @endif
        @if(!empty($d['button_text']))
          @php
            $heroBtnId = 'hero-btn-'.uniqid();
            $heroBtnOverride = ! empty($style['button_text_color']) || ! empty($style['button_bg_color'])
              || ! empty($style['button_hover_text_color']) || ! empty($style['button_hover_bg_color']);
          @endphp
          <a href="{{ $d['button_url'] ?? '#' }}" id="{{ $heroBtnId }}" class="btn btn-light btn-lg mt-2 px-4">{{ $d['button_text'] }}</a>
          @if($heroBtnOverride)
            {{-- Hover states can't be expressed via an inline style attribute
                 — a tiny id-scoped <style> block is the least invasive way to
                 add one without a shared/global CSS class per color
                 combination. uniqid() is per-render (not persisted), so this
                 is safe even with multiple Hero blocks or the same block
                 re-rendered by the live-preview's per-block AJAX path. --}}
            <style>
              #{{ $heroBtnId }} {
                @if(!empty($style['button_text_color']))color: {{ $style['button_text_color'] }};@endif
                @if(!empty($style['button_bg_color']))background-color: {{ $style['button_bg_color'] }}; border-color: {{ $style['button_bg_color'] }};@endif
              }
              #{{ $heroBtnId }}:hover {
                @if(!empty($style['button_hover_text_color']))color: {{ $style['button_hover_text_color'] }};@endif
                @if(!empty($style['button_hover_bg_color']))background-color: {{ $style['button_hover_bg_color'] }}; border-color: {{ $style['button_hover_bg_color'] }};@endif
              }
            </style>
          @endif
        @endif
      </div>
    </header>
    @break

  @case('heading')
    {!! $open !!}
      <h2 class="section-title h3 text-{{ $d['align'] ?? 'start' }} mb-0">{{ $d['text'] ?? '' }}</h2>
    {!! $close !!}
    @break

  @case('richtext')
    {!! $open !!}
      @if(!empty($d['heading']))<h2 class="section-title h3 mb-3">{{ $d['heading'] }}</h2>@endif
      <div class="lh-lg prose">{!! $d['html'] ?? '' !!}</div>
    {!! $close !!}
    @break

  @case('image')
    {!! $open !!}
      <figure class="text-center mb-0">
        @if(!empty($d['url']))
          <span class="img-zoom"><img src="{{ $d['url'] }}" class="img-fluid" alt="{{ $d['caption'] ?? '' }}"></span>
        @else
          {{-- A bare <img src=""> would render as a broken-image icon —
               real bug, not just cosmetic: before this fix a freshly-added
               Image block with no URL yet looked broken in the live editor
               preview, not merely empty. --}}
          <div class="d-flex flex-column align-items-center justify-content-center text-muted bg-body-secondary rounded-3 py-5" aria-hidden="true">
            <i class="bi bi-image fs-1 mb-2"></i>
            <span class="small">{{ __('No image selected') }}</span>
          </div>
        @endif
        @if(!empty($d['caption']))<figcaption class="text-muted small mt-2">{{ $d['caption'] }}</figcaption>@endif
      </figure>
    {!! $close !!}
    @break

  @case('video')
    {!! $open !!}
      @php
        // Video Options panel — see docs/modules/28-elementor-block-editor-plan.md
        // §7e. `controls` defaults ON (matches the spec-level default in
        // _fields.blade.php for a freshly added block); the rest default
        // OFF, same as an unset checkbox anywhere else in this app.
        $vSource = in_array($d['source'] ?? null, ['youtube', 'vimeo', 'dailymotion', 'videopress', 'self_hosted'], true) ? $d['source'] : 'youtube';
        $vStart = isset($d['start_time']) && $d['start_time'] !== '' ? max(0, (int) $d['start_time']) : null;
        $vEnd = isset($d['end_time']) && $d['end_time'] !== '' ? max(0, (int) $d['end_time']) : null;
        $vAutoplay = ! empty($d['autoplay']);
        $vMute = ! empty($d['mute']);
        $vLoop = ! empty($d['loop']);
        $vControls = ($d['controls'] ?? '') !== '' ? ! empty($d['controls']) : true;
        $vDownload = ! empty($d['download']);
        $vPreload = in_array($d['preload'] ?? null, ['none', 'metadata', 'auto'], true) ? $d['preload'] : 'metadata';
      @endphp
      @if(!empty($d['heading']))<h2 class="section-title h3 mb-3">{{ $d['heading'] }}</h2>@endif
      @if ($vSource === 'self_hosted')
        @if(!empty($d['file_url']))
          <video
            class="w-100 rounded-3 media-shadow"
            @if($vPreload !== 'none') preload="{{ $vPreload }}" @endif
            @if(!empty($d['poster'])) poster="{{ $d['poster'] }}" @endif
            @if($vControls) controls @endif
            @if(!$vDownload) controlslist="nodownload" @endif
            @if($vAutoplay) autoplay muted @elseif($vMute) muted @endif
            @if($vLoop) loop @endif
          ><source src="{{ $d['file_url'] }}{{ $vStart ? '#t='.$vStart.($vEnd ? ','.$vEnd : '') : '' }}"></video>
          @if(!empty($d['caption']))<p class="text-muted small mt-2 mb-0">{{ $d['caption'] }}</p>@endif
        @else
          <div class="d-flex flex-column align-items-center justify-content-center text-muted bg-body-secondary rounded-3 py-5" aria-hidden="true">
            <i class="bi bi-camera-video fs-1 mb-2"></i>
            <span class="small">{{ __('No video file selected') }}</span>
          </div>
        @endif
      @else
        @php
          $embedUrl = trim((string) ($d['url'] ?? ''));
          // Best-effort YouTube watch/short-link -> embed normalization +
          // start/end/autoplay/mute/loop/controls as URL params. Other
          // platforms (Vimeo/Dailymotion/VideoPress) are trusted to already
          // be a pasted embeddable URL — no per-platform param mapping for
          // those, to avoid guessing at APIs this app does not integrate with.
          if ($vSource === 'youtube' && $embedUrl !== '') {
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([a-zA-Z0-9_-]{6,})/', $embedUrl, $m)) {
              $embedUrl = 'https://www.youtube.com/embed/'.$m[1];
            }
            $ytParams = array_filter([
              $vAutoplay ? 'autoplay=1' : null,
              $vMute ? 'mute=1' : null,
              $vLoop ? 'loop=1' : null,
              $vControls ? null : 'controls=0',
              $vStart !== null ? 'start='.$vStart : null,
              $vEnd !== null ? 'end='.$vEnd : null,
            ]);
            if ($ytParams) {
              $embedUrl .= (str_contains($embedUrl, '?') ? '&' : '?').implode('&', $ytParams);
            }
          }
        @endphp
        @if($embedUrl !== '')
          <div class="ratio ratio-16x9 rounded-3 overflow-hidden media-shadow"><iframe src="{{ $embedUrl }}" allowfullscreen loading="lazy"@if($vAutoplay) allow="autoplay"@endif></iframe></div>
          @if(!empty($d['caption']))<p class="text-muted small mt-2 mb-0">{{ $d['caption'] }}</p>@endif
        @else
          <div class="d-flex flex-column align-items-center justify-content-center text-muted bg-body-secondary rounded-3 py-5" aria-hidden="true">
            <i class="bi bi-camera-video fs-1 mb-2"></i>
            <span class="small">{{ __('No video URL selected') }}</span>
          </div>
        @endif
      @endif
    {!! $close !!}
    @break

  @case('button')
    {!! $open !!}
      <div class="text-{{ $d['align'] ?? 'start' }}">
        <a href="{{ $d['url'] ?? '#' }}" class="btn btn-brand"@if(!empty($d['open_new_tab'])) target="_blank" rel="noopener"@endif>{{ $d['text'] ?? __('Click Here') }}</a>
      </div>
    {!! $close !!}
    @break

  @case('divider')
    {!! $open !!}
      <hr class="my-0" style="border-top-style:{{ in_array($d['line_style'] ?? null, ['solid','dashed','dotted'], true) ? $d['line_style'] : 'solid' }};width:{{ max(1, min(100, (int) ($d['width_pct'] ?? 100))) }}%;margin-left:auto;margin-right:auto;">
    {!! $close !!}
    @break

  @case('spacer')
    {!! $open !!}
      <div style="height:{{ max(0, min(400, (int) ($d['height'] ?? 40))) }}px;" aria-hidden="true"></div>
    {!! $close !!}
    @break

  @case('icon')
    {!! $open !!}
      @php
        $iconMarkup = '<i class="bi '.e($d['icon'] ?? 'bi-star').'" style="font-size:'.max(12, min(200, (int) ($d['size'] ?? 32))).'px;color:'.(!empty($d['color']) ? e($d['color']) : 'var(--brand)').';"></i>';
      @endphp
      <div class="text-{{ $d['align'] ?? 'center' }}">
        @if(!empty($d['url']))<a href="{{ $d['url'] }}" class="icon-link">{!! $iconMarkup !!}</a>@else{!! $iconMarkup !!}@endif
      </div>
    {!! $close !!}
    @break

  @case('google_maps')
    {!! $open !!}
      @if(!empty($d['embed_url']))
        <div class="rounded-3 overflow-hidden media-shadow" style="height:{{ max(120, min(1000, (int) ($d['height'] ?? 320))) }}px;">
          <iframe src="{{ $d['embed_url'] }}" style="width:100%;height:100%;border:0;" loading="lazy" allowfullscreen></iframe>
        </div>
      @else
        <p class="text-muted mb-0">{{ __('No Map URL Set.') }}</p>
      @endif
    {!! $close !!}
    @break

  @case('image_text')
    {!! $open !!}
      <div class="row g-4 align-items-center {{ ($d['image_side'] ?? 'left') === 'right' ? 'flex-row-reverse' : '' }}">
        <div class="col-md-5">
          @if(!empty($d['image']))
            <span class="img-zoom d-block"><img src="{{ $d['image'] }}" class="img-fluid" alt=""></span>
          @else
            <div class="d-flex flex-column align-items-center justify-content-center text-muted bg-body-secondary rounded-3 py-5" aria-hidden="true">
              <i class="bi bi-image fs-1 mb-2"></i>
              <span class="small">{{ __('No image selected') }}</span>
            </div>
          @endif
        </div>
        <div class="col-md-7">
          @if(!empty($d['heading']))<h2 class="section-title h4 mb-3">{{ $d['heading'] }}</h2>@endif
          <div class="lh-lg prose">{!! $d['html'] ?? '' !!}</div>
        </div>
      </div>
    {!! $close !!}
    @break

  @case('staff')
    {!! $open !!}
      @php
        // Per-element color overrides (Style tab's staff-only fields) — same
        // reasoning as stats: .section-title/.text-muted each carry their own
        // explicit color, so a single wrapper-level text_color can't reach
        // any of these. .text-muted is Bootstrap's !important utility (see
        // layout.blade.php's own override of it), so designation_color drops
        // that class entirely rather than fighting it — but only once a real
        // override is set; with no override the class (and its default
        // look) stays exactly as before.
        $staffRingStyle = ! empty($style['ring_color']) ? ';box-shadow:0 0 0 3px #fff, 0 0 0 5px '.e($style['ring_color']) : '';
      @endphp
      @if(!empty($d['heading']))<h2 class="section-title h3 mb-4 text-center"@if(!empty($style['heading_color'])) style="color:{{ $style['heading_color'] }}"@endif>{{ $d['heading'] }}</h2>@endif
      <div class="row {{ $bp::columnClasses($layout, ['mobile' => 2, 'tablet' => 3, 'laptop' => 4, 'desktop' => 4]) }} g-4 text-center">
        @forelse($d['members'] ?? [] as $m)
          <div>
            <div class="rounded-circle bg-white avatar-ring d-inline-flex align-items-center justify-content-center mb-3" style="width:88px;height:88px;{{ $staffRingStyle }}">
              @if($m->photo)<img src="{{ $m->photo }}" class="rounded-circle" style="width:88px;height:88px;object-fit:cover;" alt="">
              @else<span class="text-brand fw-bold fs-3"@if(!empty($style['avatar_text_color'])) style="color:{{ $style['avatar_text_color'] }}"@endif>{{ strtoupper(mb_substr($m->name, 0, 1)) }}</span>@endif
            </div>
            <div class="fw-semibold small"@if(!empty($style['name_color'])) style="color:{{ $style['name_color'] }}"@endif>{{ $m->name }}</div>
            <div class="small{{ empty($style['designation_color']) ? ' text-muted' : '' }}"@if(!empty($style['designation_color'])) style="color:{{ $style['designation_color'] }}"@endif>{{ $m->designation?->name ?? __('Staff') }}</div>
          </div>
        @empty
          <p class="text-muted mb-0">{{ __('No Staff To Show.') }}</p>
        @endforelse
      </div>
    {!! $close !!}
    @break

  @case('notices')
    {!! $open !!}
      {{-- Per-element color overrides (Style tab's notices-only fields) —
           same reasoning as staff/stats above: .section-title/.text-muted
           each carry their own explicit color, so a single wrapper-level
           text_color can never reach them. .card's own background-color
           rule (layout.blade.php) isn't !important, so card_bg_color is a
           plain inline override with no class-swap needed. --}}
      <h2 class="section-title h3 mb-4"@if(!empty($style['heading_color'])) style="color:{{ $style['heading_color'] }}"@endif>{{ $d['heading'] ?? __('Notices') }}</h2>
      <div class="row {{ $bp::columnClasses($layout, ['mobile' => 1, 'tablet' => 2, 'laptop' => 3, 'desktop' => 3]) }} g-4">
        @forelse(($d['notices'] ?? collect())->take($d['limit'] ?? 6) as $n)
          <div><div class="card h-100"@if(!empty($style['card_bg_color'])) style="background-color:{{ $style['card_bg_color'] }}"@endif><div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-center notice-icon mb-3"@if(!empty($style['icon_color'])) style="color:{{ $style['icon_color'] }}"@endif><i class="bi bi-megaphone-fill"></i></div>
            <div class="small{{ empty($style['date_color']) ? ' text-muted' : '' }} mb-1"@if(!empty($style['date_color'])) style="color:{{ $style['date_color'] }}"@endif>{{ \App\Support\LocalizedDate::format($n->publish_at ?? $n->created_at, 'd M Y') }}</div>
            <h3 class="h6 fw-semibold"@if(!empty($style['card_title_color'])) style="color:{{ $style['card_title_color'] }}"@endif>{{ $n->title }}</h3>
            <p class="{{ empty($style['card_text_color']) ? 'text-muted ' : '' }}small mb-0"@if(!empty($style['card_text_color'])) style="color:{{ $style['card_text_color'] }}"@endif>{{ \Illuminate\Support\Str::limit(strip_tags($n->body), 110) }}</p>
          </div></div></div>
        @empty
          <p class="text-muted mb-0">{{ __('No Notices Published.') }}</p>
        @endforelse
      </div>
    {!! $close !!}
    @break

  @case('stats')
    {!! $open !!}
      @php
        // Per-element color overrides (Style tab's stats-only fields — see
        // _style_fields.blade.php and PageRenderService::sanitizeStyle()).
        // A single wrapper-level text_color can't reach any of these: the
        // heading and tile text all carry their own explicit `color` in
        // layout.blade.php's stylesheet, and inheritance never wins over an
        // element's own explicit value. Tile background is the same
        // problem from the other direction — .bg-light is an !important
        // Bootstrap utility, so a wrapper-level bg_color could never beat
        // it either; the class is swapped out below instead of fought.
        $statHeadingStyle = ! empty($style['heading_color']) ? ' style="color:'.e($style['heading_color']).'"' : '';
        $statTileBgClass = ! empty($style['tile_bg_color']) ? '' : ' bg-light';
        $statTileBgStyle = ! empty($style['tile_bg_color']) ? ' style="background-color:'.e($style['tile_bg_color']).'"' : '';
        $statNumStyle = ! empty($style['tile_number_color']) ? ' style="color:'.e($style['tile_number_color']).'"' : '';
        $statSubStyle = ! empty($style['tile_subtext_color']) ? ' style="color:'.e($style['tile_subtext_color']).'"' : '';
        // Same 'reveal'/'reveal-{preset}' the wrapper would otherwise carry
        // (BlockPresentation::animationClass()) — applied per-element here
        // instead; see the $wrap['class'] strip above this switch statement.
        $statRevealClass = ! empty($style['animation']) ? ' reveal reveal-'.$style['animation'] : '';
      @endphp
      @if(!empty($d['heading']))<h2 class="section-title h3 mb-4{{ $statRevealClass }}"{!! $statHeadingStyle !!}>{{ $d['heading'] }}</h2>@endif
      <div class="row {{ $bp::columnClasses($layout, ['mobile' => 2, 'tablet' => 4, 'laptop' => 4, 'desktop' => 4]) }} g-3 text-center">
        {{-- App\Support\LocalizedDate::digits() -- number_format() only ever
             produces ASCII 0-9, so under bn these tiles rendered "1,234" even
             though every other number on the site (dates, footer year) was
             already native-digit. digits() is a no-op strtr() for any locale
             with no NATIVE_DIGITS entry (i.e. still ASCII under en), so this
             is safe to apply unconditionally. --}}
        <div><div class="p-3{{ $statTileBgClass }} stat-tile{{ $statRevealClass }}"{!! $statTileBgStyle !!}><div class="stat-num"{!! $statNumStyle !!}>{{ \App\Support\LocalizedDate::digits(number_format($d['stats']['active_students'] ?? 0)) }}</div><div class="small mt-1"{!! $statSubStyle !!}>{{ __('Students') }}</div></div></div>
        <div><div class="p-3{{ $statTileBgClass }} stat-tile{{ $statRevealClass }}"{!! $statTileBgStyle !!}><div class="stat-num"{!! $statNumStyle !!}>{{ \App\Support\LocalizedDate::digits(number_format($d['stats']['active_staff'] ?? 0)) }}</div><div class="small mt-1"{!! $statSubStyle !!}>{{ __('Teachers & Staff') }}</div></div></div>
        @foreach($d['items'] ?? [] as $it)
          <div><div class="p-3{{ $statTileBgClass }} stat-tile{{ $statRevealClass }}"{!! $statTileBgStyle !!}><div class="stat-num"{!! $statNumStyle !!}>{{ \App\Support\LocalizedDate::digits($it['value'] ?? '') }}</div><div class="small mt-1"{!! $statSubStyle !!}>{{ $it['label'] ?? '' }}</div></div></div>
        @endforeach
      </div>
    {!! $close !!}
    @break

  @case('gallery_photo')
    {!! $open !!}
      @php
        // A fresh id every render (this partial's output isn't itself what
        // PageRenderService caches — only the upstream $d data is, see
        // PageRenderService::renderPage() — so a plain uniqid() here is
        // safe and just needs to be unique within THIS page load, not
        // stable across requests). Lets a page carry more than one Gallery
        // Photo block without their modals colliding.
        $galleryId = 'gallery-photo-' . uniqid();
        $imageUrls = collect($d['images'] ?? [])
          ->map(fn ($img) => is_array($img) ? ($img['url'] ?? '') : $img)
          ->filter()->values();
      @endphp
      @if(!empty($d['heading']))<h2 class="section-title h3 mb-4">{{ $d['heading'] }}</h2>@endif
      <div class="row {{ $bp::columnClasses($layout, ['mobile' => 2, 'tablet' => 3, 'laptop' => 4, 'desktop' => 4]) }} g-3">
        @forelse($imageUrls as $i => $url)
          <div>
            <button type="button" class="img-zoom d-block w-100 border-0 p-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#{{ $galleryId }}" data-gallery-index="{{ $i }}" aria-label="{{ __('View Photo') }} {{ $i + 1 }}">
              <img src="{{ $url }}" class="img-fluid" style="aspect-ratio:1;object-fit:cover;width:100%;" alt="">
            </button>
          </div>
        @empty
          <p class="text-muted mb-0">{{ __('No Photos Yet.') }}</p>
        @endforelse
      </div>
      @if($imageUrls->isNotEmpty())
        <div class="modal fade js-photo-gallery-modal" id="{{ $galleryId }}" tabindex="-1" aria-hidden="true" data-images="{{ json_encode($imageUrls) }}">
          <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0">
              <button type="button" class="btn-close btn-close-white ms-auto m-2" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
              <div class="modal-body p-0 text-center position-relative">
                <img src="" class="img-fluid rounded-3 js-gallery-img" alt="">
                @if($imageUrls->count() > 1)
                  <button type="button" class="btn btn-light btn-sm rounded-circle position-absolute top-50 start-0 translate-middle-y ms-2 js-gallery-prev" aria-label="{{ __('Previous Photo') }}"><i class="bi bi-chevron-left"></i></button>
                  <button type="button" class="btn btn-light btn-sm rounded-circle position-absolute top-50 end-0 translate-middle-y me-2 js-gallery-next" aria-label="{{ __('Next Photo') }}"><i class="bi bi-chevron-right"></i></button>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif
    {!! $close !!}
    @break

  @case('gallery_video')
    {!! $open !!}
      @php
        $galleryId = 'gallery-video-' . uniqid();
        $videoUrls = collect($d['videos'] ?? [])
          ->map(fn ($v) => is_array($v) ? ($v['url'] ?? '') : $v)
          ->filter()->values();
      @endphp
      @if(!empty($d['heading']))<h2 class="section-title h3 mb-4">{{ $d['heading'] }}</h2>@endif
      <div class="row {{ $bp::columnClasses($layout, ['mobile' => 1, 'tablet' => 2, 'laptop' => 2, 'desktop' => 2]) }} g-3">
        @forelse($videoUrls as $i => $url)
          <div>
            <button type="button" class="video-thumb ratio ratio-16x9 rounded-3 overflow-hidden media-shadow border-0 p-0 w-100" data-bs-toggle="modal" data-bs-target="#{{ $galleryId }}" data-gallery-index="{{ $i }}" aria-label="{{ __('Play Video') }} {{ $i + 1 }}">
              <i class="bi bi-play-circle-fill" aria-hidden="true"></i>
            </button>
          </div>
        @empty
          <p class="text-muted mb-0">{{ __('No Videos Yet.') }}</p>
        @endforelse
      </div>
      @if($videoUrls->isNotEmpty())
        <div class="modal fade js-video-gallery-modal" id="{{ $galleryId }}" tabindex="-1" aria-hidden="true" data-videos="{{ json_encode($videoUrls) }}">
          <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0">
              <button type="button" class="btn-close btn-close-white ms-auto m-2" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
              <div class="modal-body p-0 position-relative">
                <div class="ratio ratio-16x9"><iframe src="" class="js-gallery-video-frame" allowfullscreen loading="lazy"></iframe></div>
                @if($videoUrls->count() > 1)
                  <button type="button" class="btn btn-light btn-sm rounded-circle position-absolute top-50 start-0 translate-middle-y ms-2 js-gallery-prev" aria-label="{{ __('Previous Video') }}"><i class="bi bi-chevron-left"></i></button>
                  <button type="button" class="btn btn-light btn-sm rounded-circle position-absolute top-50 end-0 translate-middle-y me-2 js-gallery-next" aria-label="{{ __('Next Video') }}"><i class="bi bi-chevron-right"></i></button>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif
    {!! $close !!}
    @break

  @case('admission_form')
    @include('public.blocks.admission_form')
    @break

  @case('contact')
    {!! $open !!}
      <div class="row g-4">
        <div class="col-md-6">
          <h2 class="section-title h4 mb-3">{{ $d['heading'] ?? __('Get In Touch') }}</h2>
          <ul class="list-unstyled">
            {{-- $d['address'] is a per-block admin override (already locale-scoped via the
                 page's own PageLayout row); the school-level fallback goes through transOr()
                 since School::address is a HasTranslations field -- a raw ->address here would
                 always render the school's default-locale address regardless of visitor locale. --}}
            @if(($d['address'] ?? null) || ($d['school']?->transOr('address') ?? null))<li class="d-flex align-items-center gap-3 mb-3"><span class="icon-badge d-inline-flex align-items-center justify-content-center"><i class="bi bi-geo-alt"></i></span> {{ $d['address'] ?? $d['school']->transOr('address') }}</li>@endif
            @if($d['phone'] ?? null)<li class="d-flex align-items-center gap-3 mb-3"><span class="icon-badge d-inline-flex align-items-center justify-content-center"><i class="bi bi-telephone"></i></span> {{ \App\Support\LocalizedDate::digits($d['phone']) }}</li>@endif
            @if(($d['email'] ?? null) || ($d['school']->email ?? null))<li class="d-flex align-items-center gap-3 mb-3"><span class="icon-badge d-inline-flex align-items-center justify-content-center"><i class="bi bi-envelope"></i></span> {{ $d['email'] ?? $d['school']->email }}</li>@endif
          </ul>
          @if(!empty($d['map_embed']))<div class="ratio ratio-4x3 mt-3 rounded-3 overflow-hidden media-shadow"><iframe src="{{ $d['map_embed'] }}" loading="lazy" style="border:0;"></iframe></div>@endif
        </div>
        <div class="col-md-6"><div class="card"><div class="card-body">
          @if(session('contact_sent'))
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> {{ __('Thanks — Your Message Has Been Sent.') }}</div>
          @endif
          @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
          @endif
          <form method="POST" action="{{ route('contact.submit') }}">
            @csrf
            <div class="row g-2">
              <div class="col-md-6"><input name="name" class="form-control" placeholder="{{ __('Your Name') }}" value="{{ old('name') }}" required></div>
              <div class="col-md-6"><input name="email" type="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}"></div>
              <div class="col-md-6"><input name="phone" class="form-control" placeholder="{{ __('Phone') }}" value="{{ old('phone') }}"></div>
              <div class="col-md-6"><input name="subject" class="form-control" placeholder="{{ __('Subject') }}" value="{{ old('subject') }}"></div>
            </div>
            <div class="my-2"><textarea name="message" class="form-control" rows="4" placeholder="{{ __('Message') }}" required>{{ old('message') }}</textarea></div>
            <button class="btn btn-brand"><i class="bi bi-send"></i> {{ __('Send Message') }}</button>
          </form>
        </div></div></div>
      </div>
    {!! $close !!}
    @break

  @case('announcement_bar')
    {!! $open !!}
      @php
        // Distinct from the notices ticker (a scrolling feed of Announcement
        // module records) — this is a single, admin-authored, high-intent
        // message ("Admissions open for 2026-27"), placed like any other
        // block rather than tied to a live data feed.
        $abText = trim((string) ($d['text'] ?? ''));
        $abLinkUrl = trim((string) ($d['link_url'] ?? ''));
        $abLinkText = trim((string) ($d['link_text'] ?? ''));
        $abDismissible = ! empty($d['dismissible']);
        // Keyed off the message text itself (not a random id) — editing the
        // message to say something new naturally re-shows it to someone who
        // dismissed the old one, without needing a separate "reset" step.
        $abKey = substr(md5($abText), 0, 12);
      @endphp
      @if ($abText !== '')
        <div class="announcement-bar d-flex align-items-center justify-content-center flex-wrap gap-2 text-center py-2 px-3"
             @if($abDismissible) data-announcement-bar="{{ $abKey }}" @endif>
          {{-- .announcement-bar-link/the message span both use color:inherit
               from the section wrapper (see layout.blade.php), which the
               universal Background color (Advanced tab) already reaches —
               but that means message and link can't be given DIFFERENT
               colors from each other via that one shared value. These two
               targeted fields are a direct inline override on each element
               instead, same pattern as every other block above. --}}
          <span class="small fw-semibold"@if(!empty($style['message_color'])) style="color:{{ $style['message_color'] }}"@endif>{{ $abText }}</span>
          @if ($abLinkUrl !== '' && $abLinkText !== '')
            <a href="{{ $abLinkUrl }}" class="announcement-bar-link small"@if(!empty($style['link_color'])) style="color:{{ $style['link_color'] }}"@endif>{{ $abLinkText }} <i class="bi bi-arrow-right"></i></a>
          @endif
          @if ($abDismissible)
            <button type="button" class="announcement-bar-dismiss js-announcement-dismiss ms-1" aria-label="{{ __('Dismiss') }}"><i class="bi bi-x-lg"></i></button>
          @endif
        </div>
      @else
        <div class="d-flex align-items-center justify-content-center text-muted py-2 px-3" aria-hidden="true">
          <span class="small">{{ __('No announcement text set') }}</span>
        </div>
      @endif
    {!! $close !!}
    @break

  @case('faq')
    {!! $open !!}
      @php
        $faqId = 'faq-'.uniqid();
        $faqItems = collect($d['faq_items'] ?? [])
          ->filter(fn ($it) => is_array($it) && trim((string) ($it['question'] ?? '')) !== '')
          ->values();
      @endphp
      @if (!empty($d['heading']))<h2 class="section-title h3 mb-4">{{ $d['heading'] }}</h2>@endif
      @if ($faqItems->isNotEmpty())
        <div class="accordion" id="{{ $faqId }}">
          @foreach ($faqItems as $i => $item)
            @php $itemId = $faqId.'-item-'.$i; @endphp
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button @if($i > 0) collapsed @endif" type="button" data-bs-toggle="collapse"
                        data-bs-target="#{{ $itemId }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="{{ $itemId }}">
                  {{ $item['question'] }}
                </button>
              </h3>
              <div id="{{ $itemId }}" class="accordion-collapse collapse @if($i === 0) show @endif" data-bs-parent="#{{ $faqId }}">
                <div class="accordion-body text-muted">{!! $item['answer'] ?? '' !!}</div>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-muted mb-0">{{ __('No FAQs Added Yet.') }}</p>
      @endif
    {!! $close !!}
    @break

  @case('container')
    {!! $open !!}
      @php
        $containerDir = ($d['direction'] ?? 'column') === 'row' ? 'row' : 'column';
        $containerGap = max(0, min(80, (int) ($d['gap'] ?? 16)));
      @endphp
      <div class="d-flex flex-{{ $containerDir }}" style="gap:{{ $containerGap }}px;">
        @forelse ($d['blocks'] ?? [] as $childIndex => $child)
          @if($containerDir === 'row')<div class="flex-fill">@endif
          @include('public.blocks.render', ['type' => $child['type'], 'd' => $child['d'], 'style' => $child['style'], 'layout' => $child['layout'], 'contained' => true, 'group' => $group ?? null, 'path' => isset($path) ? array_merge($path, [$childIndex]) : null])
          @if($containerDir === 'row')</div>@endif
        @empty
          <p class="text-muted mb-0">{{ __('Empty Container — Add Blocks In The Editor.') }}</p>
        @endforelse
      </div>
    {!! $close !!}
    @break

  @case('grid')
    {!! $open !!}
      <div class="row {{ $bp::columnClasses($layout, ['mobile' => 1, 'tablet' => 2, 'laptop' => 3, 'desktop' => 3]) }} g-3">
        @forelse ($d['blocks'] ?? [] as $childIndex => $child)
          <div>
            @include('public.blocks.render', ['type' => $child['type'], 'd' => $child['d'], 'style' => $child['style'], 'layout' => $child['layout'], 'contained' => true, 'group' => $group ?? null, 'path' => isset($path) ? array_merge($path, [$childIndex]) : null])
          </div>
        @empty
          <p class="text-muted mb-0">{{ __('Empty Grid — Add Blocks In The Editor.') }}</p>
        @endforelse
      </div>
    {!! $close !!}
    @break
@endswitch
@if ($contained)
  </div>
@else
  </section>
@endif
