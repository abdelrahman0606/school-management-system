@extends('layouts.admin')
@section('title', $label)
@section('content')
  @include('admin.partials.page-header', [
    'title'  => $label,
    'crumbs' => [__('People'), $label],
    'action' => ['label' => 'New ' . \Illuminate\Support\Str::lower($singular), 'modal' => 'createModal'],
  ])

  <div class="card"><div class="card-body">
    <table class="table table-hover align-middle w-100 js-dt">
      <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Staff') }}</th><th class="text-end" data-orderable="false">{{ __('Actions') }}</th></tr></thead>
      <tbody>
        @foreach ($items as $item)
          <tr>
            <td class="fw-semibold">{{ $item->name }}</td>
            <td><x-badge variant="neutral">{{ $item->staff_count }}</x-badge></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal{{ $item->id }}">{{ __('Edit') }}</button>
              <form method="POST" action="{{ route('admin.' . $type . '.destroy', $item->id) }}" class="d-inline" onsubmit="return confirm('Delete {{ $item->name }}?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div></div>

  <div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('admin.' . $type . '.store') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">New {{ \Illuminate\Support\Str::lower($singular) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input name="name" class="form-control" value="{{ old('name') }}" required>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
  </div></div></div>

  @foreach ($items as $item)
    <div class="modal fade" id="editModal{{ $item->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
      <form method="POST" action="{{ route('admin.' . $type . '.update', $item->id) }}">
        @csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit {{ \Illuminate\Support\Str::lower($singular) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
          <input name="name" class="form-control" value="{{ $item->name }}" required>

          {{-- Translations — docs/modules/30-multilingual-content-plan.md
               Phase 4/5. --}}
          @if (isset($contentLanguages) && $contentLanguages->isNotEmpty())
            <hr class="my-3">
            <p class="fw-semibold small mb-2">{{ __('Translations') }}</p>
            <p class="text-muted small mb-3">{{ __('Leave a field blank to fall back to the default-language content above.') }}</p>
            @foreach ($contentLanguages as $lang)
              @php
                $t = old('translations.'.$lang->code, ['name' => $item->trans('name', $lang->code)]);
              @endphp
              <details class="card mb-2">
                <summary class="card-header py-1" style="cursor:pointer;">
                  @if ($lang->flag){{ $lang->flag }} @endif {{ $lang->native_name }}
                </summary>
                <div class="card-body">
                  <button type="button" class="btn btn-sm btn-outline-secondary mb-2"
                          onclick="document.getElementById('ai-suggest-{{ $type }}-{{ $item->id }}-{{ $lang->code }}').submit()">
                    <i class="bi bi-magic"></i> {{ __('Suggest translations (AI)') }}
                  </button>
                  <p class="form-text mt-0 mb-3">{{ __('Fills only the empty fields below using a free machine-translation service — always review a suggestion before saving.') }}</p>
                  <label class="form-label">{{ __('Name') }}</label>
                  <input name="translations[{{ $lang->code }}][name]" class="form-control"
                      value="{{ $t['name'] }}" placeholder="{{ $item->name }}">
                </div>
              </details>
            @endforeach
          @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
      </form>
    </div></div></div>
  @endforeach

  {{-- One tiny standalone form per language, submitted via JS from the
       "Suggest translations (AI)" button inside each language panel above —
       kept OUTSIDE this row's own <form> since HTML forms can't nest.
       docs/modules/30-multilingual-content-plan.md Phase 5. --}}
  @if (isset($contentLanguages))
    @foreach ($items as $item)
      @foreach ($contentLanguages as $lang)
        <form method="POST" action="{{ route('admin.' . $type . '.translations.suggest', $item->id) }}" id="ai-suggest-{{ $type }}-{{ $item->id }}-{{ $lang->code }}" class="d-none">
          @csrf
          <input type="hidden" name="locale" value="{{ $lang->code }}">
        </form>
      @endforeach
    @endforeach
  @endif
@endsection
