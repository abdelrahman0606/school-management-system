@extends('public.layout')
{{-- Sections below deliberately use the BLOCK form (@section(...)@endsection)
     with a raw {!! !!} echo, not the inline @section('name', $value) form.
     Laravel's inline form silently HTML-escapes $value itself (see
     ManagesLayouts::startSection() -> e($content)) — since layout.blade.php's
     $pageTitle/$metaDesc/$ogUrl already run every yielded section through
     Blade's own {{ }} once (see its <title>/og:title/twitter:title tags),
     escaping here too meant every title/description containing &, ", <, or >
     was rendered double-escaped ("&amp;amp;" instead of "&amp;") while the
     page's own <h1> (echoed once via a plain {{ }}, never through a
     section, in public/templates/full.blade.php and sidebar.blade.php)
     was correctly single-escaped — the inconsistency
     PageSeoMetaTagsTest::test_page_title_is_escaped_consistently_across_title_og_and_twitter_tags
     catches. Keeping the escape in layout.blade.php (used consistently
     everywhere else in this app for text content) and echoing raw here
     makes every title/description escape exactly once, no matter which
     section supplied it. --}}
{{-- docs/modules/30-multilingual-content-plan.md Phase 2: $view['meta'] (set
     by PageRenderService::renderPage()) is this locale's OWN SEO meta on the
     resolved PageLayout row, when one exists — preferred over $page's shared
     default-locale columns. Safe even when $view has no 'meta' key at all
     (the admin preview()/previewBlock() endpoints and the "no published
     layout at all" fallback in PageController::show() never set it) — PHP's
     ?? chains through missing array keys at every level without a notice. --}}
@php
  $effectiveTitle = ($view['meta']['title'] ?? null) ?: $page->title;
  $effectiveMetaTitle = ($view['meta']['meta_title'] ?? null) ?: $page->meta_title;
  $effectiveMetaDesc = ($view['meta']['meta_desc'] ?? null) ?: $page->meta_desc;
  $effectiveOgImage = ($view['meta']['og_image'] ?? null) ?: $page->og_image;
@endphp
@section('title')
{!! ($effectiveMetaTitle ?: $effectiveTitle) . ' · ' . ($settings->site_name ?? $school?->transOr('name') ?? 'School') !!}
@endsection
{{-- Only defined when the page has its own value — layout.blade.php falls
     back to the site-wide Website > Settings default otherwise (see its
     $metaDesc/$ogUrl computation). --}}
@if ($effectiveMetaDesc)
@section('meta_description')
{!! $effectiveMetaDesc !!}
@endsection
@endif
@if ($effectiveOgImage)
@section('og_image')
{!! $effectiveOgImage !!}
@endsection
@endif
@section('content')
  @includeFirst(['public.templates.' . $view['template'], 'public.templates.full'], ['view' => $view, 'page' => $page])
@endsection
