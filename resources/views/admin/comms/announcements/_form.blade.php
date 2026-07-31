@php
  $isEdit = ($mode ?? 'create') === 'edit';
  $modalId = $isEdit ? 'editModal' . $a->id : 'createModal';
  $action = $isEdit ? route('admin.announcements.update', $a->id) : route('admin.announcements.store');
  $types = ['general','event','holiday','exam','fee','other'];
  $audiences = ['all','teachers','students','parents'];
  $priorities = ['normal','important','urgent'];
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <div class="modal-header"><h5 class="modal-title">{{ $isEdit ? 'Edit announcement' : 'New announcement' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
      <div class="col-12"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
        <input name="title" class="form-control" value="{{ $isEdit ? $a->title : old('title') }}" required></div>
      <div class="col-12"><label class="form-label">{{ __('Body') }} <span class="text-danger">*</span></label>
        <textarea name="body" rows="4" class="form-control" required>{{ $isEdit ? $a->body : old('body') }}</textarea></div>
      <div class="col-md-4"><label class="form-label">{{ __('Type') }}</label>
        <select name="type" class="form-select">@foreach ($types as $t)<option value="{{ $t }}" @selected(($isEdit ? $a->type : old('type','general'))===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label">{{ __('Audience') }}</label>
        <select name="audience" class="form-select">@foreach ($audiences as $au)<option value="{{ $au }}" @selected(($isEdit ? $a->audience : old('audience','all'))===$au)>{{ ucfirst($au) }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label">{{ __('Priority') }}</label>
        <select name="priority" class="form-select">@foreach ($priorities as $p)<option value="{{ $p }}" @selected(($isEdit ? $a->priority : old('priority','normal'))===$p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">{{ __('Expire At') }} <span class="text-muted small">(optional)</span></label>
        <input type="datetime-local" name="expire_at" class="form-control" value="{{ $isEdit && $a->expire_at ? $a->expire_at->format('Y-m-d\TH:i') : '' }}"></div>
      @unless ($isEdit)
        <div class="col-md-6"><label class="form-label">{{ __('Schedule For') }} <span class="text-muted small">(optional)</span></label>
          <input type="datetime-local" name="publish_at" class="form-control"></div>
      @endunless
      <div class="col-12 d-flex gap-4">
        <div class="form-check"><input type="hidden" name="is_pinned" value="0"><input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="pin{{ $modalId }}" @checked($isEdit ? $a->is_pinned : false)><label class="form-check-label" for="pin{{ $modalId }}">{{ __('Pin To Top') }}</label></div>
        @unless ($isEdit)
          <div class="form-check"><input class="form-check-input" type="checkbox" name="publish_now" value="1" id="pubnow" checked><label class="form-check-label" for="pubnow">{{ __('Publish Immediately') }}</label></div>
        @endunless
      </div>

      {{-- Translations — docs/modules/30-multilingual-content-plan.md Phase
           4/5. Only offered once the announcement exists (create has no id
           yet to attach translation rows to) — same collapsible-panel-per-
           language convention as School settings, just nested inside this
           row's own edit modal instead of a full page. --}}
      @if ($isEdit && isset($contentLanguages) && $contentLanguages->isNotEmpty())
        <div class="col-12">
          <hr class="my-2">
          <p class="fw-semibold small mb-2">{{ __('Translations') }}</p>
          <p class="text-muted small mb-3">{{ __('Leave a field blank to fall back to the default-language content above.') }}</p>
          @foreach ($contentLanguages as $lang)
            @php
              $t = old('translations.'.$lang->code, [
                  'title' => $a->trans('title', $lang->code),
                  'body' => $a->trans('body', $lang->code),
              ]);
            @endphp
            <details class="card mb-2">
              <summary class="card-header py-1" style="cursor:pointer;">
                @if ($lang->flag){{ $lang->flag }} @endif {{ $lang->native_name }}
              </summary>
              <div class="card-body">
                <button type="button" class="btn btn-sm btn-outline-secondary mb-2"
                        onclick="document.getElementById('ai-suggest-announcement-{{ $a->id }}-{{ $lang->code }}').submit()">
                  <i class="bi bi-magic"></i> {{ __('Suggest translations (AI)') }}
                </button>
                <p class="form-text mt-0 mb-3">{{ __('Fills only the empty fields below using a free machine-translation service — always review a suggestion before saving.') }}</p>
                <div class="row g-3">
                  <div class="col-12"><label class="form-label">{{ __('Title') }}</label>
                    <input name="translations[{{ $lang->code }}][title]" class="form-control"
                        value="{{ $t['title'] }}" placeholder="{{ $a->title }}">
                  </div>
                  <div class="col-12"><label class="form-label">{{ __('Body') }}</label>
                    <textarea name="translations[{{ $lang->code }}][body]" rows="3" class="form-control"
                        placeholder="{{ $a->body }}">{{ $t['body'] }}</textarea>
                  </div>
                </div>
              </div>
            </details>
          @endforeach
        </div>
      @endif
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
  </form>
</div></div></div>

{{-- One tiny standalone form per language, submitted via JS from the
     "Suggest translations (AI)" button inside each language panel above —
     kept OUTSIDE this row's own <form> since HTML forms can't nest.
     docs/modules/30-multilingual-content-plan.md Phase 5. --}}
@if ($isEdit && isset($contentLanguages))
  @foreach ($contentLanguages as $lang)
    <form method="POST" action="{{ route('admin.announcements.translations.suggest', $a->id) }}" id="ai-suggest-announcement-{{ $a->id }}-{{ $lang->code }}" class="d-none">
      @csrf
      <input type="hidden" name="locale" value="{{ $lang->code }}">
    </form>
  @endforeach
@endif
