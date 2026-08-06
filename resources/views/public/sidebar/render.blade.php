@php
  $style = $style ?? [];
  $layout = $layout ?? [];
  $wrap = \App\Modules\Website\Support\BlockPresentation::wrapper($style, $layout);
  $wrapClass = trim($wrap['class'].' mb-3');
  $wrapStyleAttr = $wrap['style'] !== '' ? ' style="'.$wrap['style'].'"' : '';
  // Advanced tab "ID" field (§7ai) — see public/blocks/render.blade.php for
  // the full reasoning; same whitelisted-not-escaped value, same attribute.
  $wrapIdAttr = $wrap['id'] !== '' ? ' id="'.e($wrap['id']).'"' : '';
  // See public/blocks/render.blade.php — same click-to-select/drag-reorder/
  // context-menu bridge attributes (sidebar blocks never nest, so $path is
  // always a single-element array here, but the attribute name matches the
  // main blocks render partial so the parent-side JS does not need two
  // different lookup schemes).
  $editorAttrs = isset($path)
    ? ' data-block-path="'.e(implode(',', $path)).'" data-block-group="'.e($group ?? 'sidebar').'" data-block-type="'.e($type).'" draggable="true"'
    : '';
@endphp
<div class="{{ $wrapClass }}"{!! $wrapStyleAttr !!}{!! $wrapIdAttr !!}{!! $editorAttrs !!}>
@switch($type)
  @case('quick_links')
    <div class="card"><div class="card-body">
      <h3 class="h6 section-title mb-3">{{ $d['heading'] ?? __('Quick Links') }}</h3>
      <div class="d-flex flex-column gap-2">
        @foreach($d['links'] ?? [] as $l)
          <a href="{{ $l['url'] ?? '#' }}" class="link-arrow w-100 justify-content-between text-brand">{{ $l['label'] ?? '' }} <i class="bi bi-chevron-right small"></i></a>
        @endforeach
      </div>
    </div></div>
    @break

  @case('office_hours')
    <div class="card"><div class="card-body">
      <h3 class="h6 section-title mb-3">{{ $d['heading'] ?? __('Office Hours') }}</h3>
      <ul class="list-unstyled small mb-0">
        @foreach($d['lines'] ?? [] as $line)
          <li class="d-flex justify-content-between border-bottom py-1">
            <span>{{ is_array($line) ? ($line['label'] ?? '') : $line }}</span>
            @if(is_array($line) && !empty($line['value']))<span class="text-muted">{{ $line['value'] }}</span>@endif
          </li>
        @endforeach
      </ul>
    </div></div>
    @break

  @case('contact_info')
    <div class="card"><div class="card-body">
      <h3 class="h6 section-title mb-3">{{ $d['heading'] ?? __('Contact') }}</h3>
      <ul class="list-unstyled small mb-0">
        {{-- School::address is a HasTranslations field -- transOr() (not a raw ->address)
             so the sidebar contact card follows the visitor's locale, same fix as the main
             contact block in public/blocks/render.blade.php. --}}
        @if(($d['address'] ?? null) || ($d['school']?->transOr('address') ?? null))<li class="d-flex align-items-center gap-2 mb-2"><span class="icon-badge d-inline-flex align-items-center justify-content-center" style="width:1.75rem;height:1.75rem;font-size:.8rem;"><i class="bi bi-geo-alt"></i></span> {{ $d['address'] ?? $d['school']->transOr('address') }}</li>@endif
        @if($d['phone'] ?? null)<li class="d-flex align-items-center gap-2 mb-2"><span class="icon-badge d-inline-flex align-items-center justify-content-center" style="width:1.75rem;height:1.75rem;font-size:.8rem;"><i class="bi bi-telephone"></i></span> {{ \App\Support\LocalizedDate::digits($d['phone']) }}</li>@endif
        @if(($d['email'] ?? null) || ($d['school']->email ?? null))<li class="d-flex align-items-center gap-2"><span class="icon-badge d-inline-flex align-items-center justify-content-center" style="width:1.75rem;height:1.75rem;font-size:.8rem;"><i class="bi bi-envelope"></i></span> {{ $d['email'] ?? $d['school']->email }}</li>@endif
      </ul>
    </div></div>
    @break

  @case('recent_notices')
    <div class="card"><div class="card-body">
      <h3 class="h6 section-title mb-3">{{ $d['heading'] ?? __('Recent Notices') }}</h3>
      @forelse(($d['notices'] ?? collect())->take($d['limit'] ?? 5) as $n)
        <div class="small border-bottom py-2">
          <div class="fw-semibold">{{ $n->title }}</div>
          <div class="text-muted">{{ \App\Support\LocalizedDate::format($n->publish_at ?? $n->created_at, 'd M Y') }}</div>
        </div>
      @empty
        <p class="text-muted small mb-0">{{ __('No Notices.') }}</p>
      @endforelse
    </div></div>
    @break
@endswitch
</div>
