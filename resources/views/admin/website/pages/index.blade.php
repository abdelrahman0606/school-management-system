@extends('layouts.admin')
@section('title', __('Website Pages'))
@section('content')
  @include('admin.partials.page-header', [
    'title'  => __('Website pages'),
    'crumbs' => [__('Website'), __('Pages')],
    'action' => ['label' => __('New page'), 'url' => route('admin.pages.create')],
  ])

  <div class="card"><div class="card-body">
    <table class="table table-hover align-middle w-100 js-dt">
      <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Status') }}</th><th>{{ __('Homepage') }}</th>
        {{-- One column per active non-default language, header = short code.
             Tick = a currently-PUBLISHED PageLayout exists for that language
             (what's actually live), not merely "a draft was started". --}}
        @if (isset($contentLanguages))
          @foreach ($contentLanguages as $lang)
            <th class="text-center" title="{{ $lang->native_name }}">{{ strtoupper($lang->code) }}</th>
          @endforeach
        @endif
        <th class="text-end" data-orderable="false">{{ __('Actions') }}</th></tr></thead>
      <tbody>
        @foreach ($pages as $p)
          <tr>
            <td class="fw-semibold">{{ $p->title }}</td>
            <td><code>/{{ $p->slug }}</code></td>
            <td>
              @if ($p->status === 'published')<x-badge variant="success">{{ __('Published') }}</x-badge>
              @else<x-badge variant="neutral">{{ __('Draft') }}</x-badge>@endif
            </td>
            <td>@if ($p->is_homepage)<x-badge variant="primary"><i class="bi bi-house"></i> {{ __('Homepage') }}</x-badge>@endif</td>
            @if (isset($contentLanguages))
              @foreach ($contentLanguages as $lang)
                <td class="text-center">
                  @if ($p->hasPublishedTranslation($lang->code))
                    <i class="bi bi-check-lg text-success" aria-label="{{ __('Translated') }}"></i>
                  @else
                    <i class="bi bi-x-lg text-muted" aria-label="{{ __('Not Translated') }}"></i>
                  @endif
                </td>
              @endforeach
            @endif
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.pages.edit', $p->id) }}">{{ __('Edit') }}</a>
              @if ($p->status === 'published')<a class="btn btn-sm btn-outline-secondary" href="{{ url('/' . $p->slug) }}" target="_blank">{{ __('View') }}</a>@endif
              @unless ($p->is_homepage)
                <form method="POST" action="{{ route('admin.pages.homepage', $p->id) }}" class="d-inline">
                  @csrf<button class="btn btn-sm btn-outline-secondary" title="{{ __('Set As Homepage') }}"><i class="bi bi-house"></i></button>
                </form>
              @endunless
              <form method="POST" action="{{ route('admin.pages.duplicate', $p->id) }}" class="d-inline">
                @csrf<button class="btn btn-sm btn-outline-secondary" title="{{ __('Duplicate') }}"><i class="bi bi-files"></i></button>
              </form>
              <form method="POST" action="{{ route('admin.pages.destroy', $p->id) }}" class="d-inline" onsubmit="return confirm('Delete “{{ $p->title }}”?')">
                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div></div>
@endsection
