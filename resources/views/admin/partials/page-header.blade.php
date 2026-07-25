{{--
  Reusable page header: breadcrumb + title + one or more action buttons.
  Usage (single action, unchanged from before):
    @include('admin.partials.page-header', [
      'title' => __('Academic years'),
      'crumbs' => [__('Setup'), __('Academic years')],
      'action' => ['label' => __('New year'), 'modal' => 'createModal'],  // or 'url' => '...'
    ])
  Usage (multiple actions — 'variant' defaults to 'primary', 'icon' to 'bi-plus-lg'):
    @include('admin.partials.page-header', [
      'title' => __('Invoices'),
      'crumbs' => [__('Finance'), __('Invoices')],
      'actions' => [
        ['label' => __('Bulk Generate'), 'modal' => 'bulkModal', 'icon' => 'bi-collection', 'variant' => 'outline-primary'],
        ['label' => __('Generate Invoice'), 'modal' => 'singleModal'],
      ],
    ])
  'action' (singular) is still accepted and just becomes a one-item list — every
  existing @include call across the admin panel keeps working unchanged.
--}}
@php
  $pageHeaderActions = $actions ?? (isset($action) ? [$action] : []);
@endphp
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">{{ __('Home') }}</a></li>
        @foreach ($crumbs ?? [] as $c)
          <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">{{ __($c) }}</li>
        @endforeach
      </ol>
    </nav>
    <h1 class="h4 mb-0 page-title">{{ __($title) }}</h1>
  </div>
  @if (!empty($pageHeaderActions))
    <div class="d-flex gap-2">
      @foreach ($pageHeaderActions as $a)
        @php $variant = 'btn-' . ($a['variant'] ?? 'primary'); $icon = $a['icon'] ?? 'bi-plus-lg'; @endphp
        @if (!empty($a['modal']))
          <button class="btn {{ $variant }}" data-bs-toggle="modal" data-bs-target="#{{ $a['modal'] }}">
            <i class="bi {{ $icon }}"></i> {{ __($a['label']) }}
          </button>
        @elseif (!empty($a['url']))
          <a class="btn {{ $variant }}" href="{{ $a['url'] }}"><i class="bi {{ $icon }}"></i> {{ __($a['label']) }}</a>
        @endif
      @endforeach
    </div>
  @endif
</div>
