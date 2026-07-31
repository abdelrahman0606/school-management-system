@extends('layouts.admin')
@section('title', __('Translations') . ' — ' . $language->native_name)
@section('content')
  @include('admin.partials.page-header', [
    'title' => __('Translations') . ' — ' . $language->flag . ' ' . $language->native_name,
    'crumbs' => [__('Settings'), __('Languages'), $language->name],
  ])

  <div class="card">
    <div class="card-header">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
          <input name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('Search Text…') }}">
        </div>
        <div class="col-auto form-check ms-2">
          <input class="form-check-input" type="checkbox" name="missing" value="1" id="missing" @checked($missingOnly) onchange="this.form.submit()">
          <label class="form-check-label small" for="missing">{{ __('Untranslated Only') }}</label>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Search') }}</button></div>
        <div class="col text-end">
          <a href="{{ route('admin.languages.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('Back To Languages') }}</a>
        </div>
      </form>
    </div>

    <form method="POST" action="{{ route('admin.languages.translations.save', $language->code) }}">
      @csrf @method('PUT')
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr>
            <th style="width:50%">{{ __('English (Source)') }}</th>
            <th>{{ $language->native_name }}</th>
          </tr></thead>
          <tbody>
            @forelse ($rows as $row)
              <tr>
                <td class="small">{{ $row->key }}</td>
                <td>
                  <input name="t[{{ $row->id }}]" value="{{ old("t.{$row->id}", $row->value) }}"
                         class="form-control form-control-sm {{ $row->value === null ? 'border-warning' : '' }}"
                         @if($language->is_rtl) dir="rtl" @endif>
                </td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted py-4">
                {{ __('No Strings Found — Run "Scan For New Strings" On The Languages Page.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div>{{ $rows->links() }}</div>
        <div class="d-flex gap-2">
          {{-- Second submit button on the SAME form, via formaction — reuses
               every t[id] field already on the page instead of a separate
               hidden-id-list form. Fills only the still-empty rows on THIS
               page with an AI draft (also saves anything you've already
               typed first — see suggestTranslations()'s own docblock) —
               review before trusting it, same as every other AI-suggest
               button in this app. --}}
          <button type="submit" id="suggest-translations-btn" class="btn btn-outline-secondary btn-sm"
                  formaction="{{ route('admin.languages.translations.suggest', $language->code) }}">
            <i class="bi bi-magic"></i> {{ __('Suggest translations (AI)') }}
          </button>
          <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('Save Translations') }}</button>
        </div>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var btn = document.getElementById('suggest-translations-btn');
      if (!btn) return;
      // Sequential per-row network calls (MyMemory) server-side — this can
      // take several seconds for a full page of untranslated rows. Disable
      // + spinner immediately so the admin isn't left wondering whether the
      // click registered (same pattern as the Menu editor's Save button).
      btn.closest('form').addEventListener('submit', function (e) {
        // e.submitter -- which of the form's (possibly several) submit
        // buttons actually triggered this submission; both Save and
        // Suggest live on the same <form>, so this is the reliable way to
        // tell them apart (a synchronous btn.disabled inside the button's
        // own click handler can race the browser's native submit and
        // silently cancel it in some browsers -- the form's submit event
        // has already committed by the time this fires).
        if (e.submitter !== btn) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + @json(__('Translating…'));
      });
    })();
  </script>
@endpush
