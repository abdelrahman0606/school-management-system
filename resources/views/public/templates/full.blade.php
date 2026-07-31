@if (($page->show_title ?? true) && empty($view['blocks']))
  {{-- $view['meta']['title'] is this locale's own title (docs/modules/30-multilingual-content-plan.md Phase 2) — falls back to the shared default-locale $page->title. --}}
  <div class="container py-5"><h1 class="section-title h2">{{ ($view['meta']['title'] ?? null) ?: $page->title }}</h1></div>
@endif
@foreach ($view['blocks'] as $i => $b)
  @include('public.blocks.render', ['type' => $b['type'], 'd' => $b['d'], 'style' => $b['style'] ?? [], 'layout' => $b['layout'] ?? [], 'path' => [$i], 'group' => 'blocks'])
@endforeach
