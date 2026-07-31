@php
    $primary = $settings->primary_color ?? '#1d4ed8';
    $topText = $settings->topbar_text_color ?? '#ffffff';
    // docs/modules/30-multilingual-content-plan.md Phase 4: same fallback
    // swap as layout.blade.php's own $siteName computation.
    $siteName = $settings->site_name ?? ($school?->transOr('name') ?? 'Our School');

    // Follows the VISITOR's browsing locale (app()->getLocale()), same as
    // every other date on the public site (footer copyright year, notices,
    // sidebar) -- LocalizedDate::format() already defaults its $locale param
    // to app()->getLocale() when null, so no override is passed here. (This
    // used to pin to $school->locale -- the school's configured home-
    // language column, seeded 'en' -- on the theory that "today" should
    // reflect the institution's own language regardless of visitor. That
    // made the header date the one place on the site that silently ignored
    // the language switcher, which read as "the date hasn't changed" rather
    // than as an intentional institutional-language date.)
    $tz = $school?->timezone ?? config('app.timezone');
    try {
        $today = \Illuminate\Support\Carbon::now($tz);
    } catch (\Throwable $e) {
        $today = now();
    }
    // App\Support\LocalizedDate -- translatedFormat() for the month/day
    // names (Carbon's own bundled locale data, no API call) + a native-digit
    // swap Carbon doesn't do on its own.
    $dateStr = \App\Support\LocalizedDate::format($today, 'l j F Y');

    $tickerPos = $settings->ticker_position ?? 'below_nav';
    $showTicker = $tickerPos !== 'hidden' && ($ticker ?? collect())->isNotEmpty();
    $headerPhones = $school ? $school->phones->where('show_in_header', true)->values() : collect();
    $logoUrl = \App\Support\Media::url($school?->logo);
@endphp

{{-- Row 1: a slim utility strip — date + phone(s) + language switcher only.
     Institution codes and the established year moved to the footer (see
     public/layout.blade.php) — they're reference info, not something a
     visitor needs before they've even seen the nav. This is the "one header
     row, not three" change from docs/modules/29-frontend-modernization-proposal.md
     Phase 2: the old logo/identity row and nav row are merged into a single
     bar below instead of stacking three rows before any real content. --}}
<div style="background: {{ $primary }}; color: {{ $topText }};">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-2 small py-1" style="min-height:28px;">
            <span class="text-capitalize d-none d-sm-inline">{{ $dateStr }}</span>
            <div class="d-flex align-items-center gap-3 ms-auto">
                @if($headerPhones->isNotEmpty())
                    <span>
                        <i class="bi bi-telephone-fill"></i>
                        {{-- href stays plain ASCII digits (preg_replace already strips
                             everything but 0-9/+) -- a tel: link has to stay dialable,
                             native-digit glyphs there would break click-to-call on most
                             devices. Only the visible TEXT is localized, same as every
                             other number on the public site. --}}
                        @foreach($headerPhones as $ph)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $ph->phone) }}"
                            style="color: {{ $topText }}; text-decoration:none;">{{ \App\Support\LocalizedDate::digits($ph->phone) }}</a>@if(!$loop->last), @endif
                        @endforeach
                    </span>
                @endif
                @if(($appLanguages ?? collect())->count() > 1)
                    <span>
                        @foreach($appLanguages as $lang)
                            <a href="{{ route('language.switch', $lang->code) }}"
                               style="color: {{ $topText }}; text-decoration:{{ $lang->code === app()->getLocale() ? 'underline' : 'none' }};">{{ $lang->native_name }}</a>@if(!$loop->last) <span style="opacity:.5">|</span> @endif
                        @endforeach
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Notice ticker above the merged bar --}}
@if($showTicker && $tickerPos === 'above_nav')
    @include('public.partials.ticker')
@endif

{{-- Row 2: logo + name + nav + CTA, all one sticky bar. Shrinks slightly
     once scrolled (see the .pub-mainbar.is-scrolled rules + script in
     public/layout.blade.php) rather than staying full-height forever. --}}
<nav class="navbar navbar-expand-lg pub-mainbar sticky-top bg-white border-bottom" data-bs-theme="light" id="pub-mainbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 py-1" href="{{ route('home') }}">
            @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="pub-logo">
            @else<i class="bi bi-mortarboard-fill fs-3" style="color: {{ $primary }};"></i>@endif
            <span class="fw-bold" style="color: {{ $primary }};">{{ $siteName }}</span>
        </a>
        <button class="navbar-toggler ms-auto" data-bs-toggle="collapse" data-bs-target="#pubnav"><span
                class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="pubnav">
            <ul class="navbar-nav mx-auto">
                @if(($navMenu ?? null) && $navMenu->items->isNotEmpty())
                    @foreach($navMenu->items as $item)
                        @if($item->children->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">{{ $item->label }}</a>
                                <ul class="dropdown-menu">
                                    @foreach($item->children as $child)
                                        <li><a class="dropdown-item" href="{{ $child->resolvedUrl() }}" target="{{ $child->target }}">{{ $child->label }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item"><a class="nav-link" href="{{ $item->resolvedUrl() }}" target="{{ $item->target }}">{{ $item->label }}</a></li>
                        @endif
                    @endforeach
                @else
                    {{-- Fallback nav when no menu has been built yet --}}
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">{{ __('Home') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/faculty') }}">{{ __('Faculty') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/online-admission') }}">{{ __('Online Admission') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/notices') }}">{{ __('Notices') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/contact') }}">{{ __('Contact') }}</a></li>
                @endif
            </ul>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="btn btn-brand btn-sm px-3" href="{{ url('/online-admission') }}"><i class="bi bi-mortarboard"></i> {{ __('Apply Now') }}</a>
                <a class="btn btn-outline-secondary btn-sm px-3" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right"></i> {{ __('Login') }}</a>
            </div>
        </div>
    </div>
</nav>

{{-- Notice ticker (below the merged bar) --}}
@if($showTicker && $tickerPos === 'below_nav')
    @include('public.partials.ticker')
@endif
