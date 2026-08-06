{{-- Universal per-block "Style" tab — same fields for every block type,
     with one deliberate exception (see below).
     Vars: $prefix, $style, $type
     Everything that isn't purely typographic/color lives on the Advanced tab
     now (_layout_fields.blade.php) — see docs/modules/28-elementor-block-editor-plan.md
     §7x (padding/margin) and §7aa (background/border/radius/shadow/width).
     All still `[style][...]` keys underneath (unchanged data model), just
     rendered from a different tab's partial. This tab is left with the two
     fields that are genuinely about this block's own look, not its box
     model: text color and entrance animation.

     Statistics block exception: a single wrapper-level Text Color can never
     actually reach the heading/tile-number/tile-subtext text — they each
     carry their own explicit `color` in layout.blade.php's stylesheet
     (.section-title/.stat-num/tile subtext), and CSS inheritance only fills
     in a value when an element has none of its own; an inherited wrapper
     color always loses to that. Showing the generic Text Color field there
     was actively misleading (it visibly did nothing), so 'stats' gets four
     targeted fields instead — see public/blocks/render.blade.php's 'stats'
     @case, which applies each one directly to its own element. --}}
@php $s = $style ?? []; $type = $type ?? null; @endphp
<div class="d-flex justify-content-end gap-1 mb-2">
  <button type="button" class="btn btn-sm btn-outline-secondary js-copy-style" title="{{ __('Copy This Block\'s Style') }}"><i class="bi bi-clipboard"></i> {{ __('Copy Style') }}</button>
  <button type="button" class="btn btn-sm btn-outline-secondary js-paste-style" disabled title="{{ __('Paste The Copied Style Here') }}"><i class="bi bi-clipboard-check"></i> {{ __('Paste Style') }}</button>
</div>
<div class="row g-2">
  @if ($type === 'stats')
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Heading color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['heading_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][heading_color]" value="{{ $s['heading_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Tile background color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['tile_bg_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][tile_bg_color]" value="{{ $s['tile_bg_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Tile number color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['tile_number_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][tile_number_color]" value="{{ $s['tile_number_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Tile subtext color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['tile_subtext_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][tile_subtext_color]" value="{{ $s['tile_subtext_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
  @else
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['text_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][text_color]" value="{{ $s['text_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
  @endif

  <div class="col-12">
    <label class="form-label small text-muted mb-1">{{ __('Entrance animation') }}</label>
    <select name="{{ $prefix }}[style][animation]" class="form-select form-select-sm">
      <option value="" @selected(empty($s['animation']))>{{ __('None') }}</option>
      <option value="fade" @selected(($s['animation'] ?? '') === 'fade')>{{ __('Fade in') }}</option>
      <option value="up" @selected(($s['animation'] ?? '') === 'up')>{{ __('Slide up') }}</option>
    </select>
    @if ($type === 'stats')
      <p class="form-text small text-muted mb-0">{{ __('Applies to the heading and each tile individually, not the section as a whole.') }}</p>
    @endif
  </div>
</div>
