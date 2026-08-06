{{-- Universal per-block "Style" tab — same two fields (text color, entrance
     animation) for most block types, with targeted per-element fields for the
     block types where a single wrapper-level color/animation can never
     actually reach the text it's meant to affect.
     Vars: $prefix, $style, $type
     Everything that isn't purely typographic/color lives on the Advanced tab
     now (_layout_fields.blade.php) — see docs/modules/28-elementor-block-editor-plan.md
     §7x (padding/margin) and §7aa (background/border/radius/shadow/width).
     All still `[style][...]` keys underneath (unchanged data model), just
     rendered from a different tab's partial.

     Per-element exception, first added for 'stats' (§7ab) and since extended
     to 'notices'/'staff'/'hero'/'announcement_bar' (§7ae): each of these
     blocks' visible text/background elements carry their own explicit CSS
     color in layout.blade.php's stylesheet (.section-title, .text-muted,
     .card, .hero, .hero h1, .text-white-50, .avatar-ring, .announcement-bar-*,
     …), and CSS inheritance only ever fills in a value when the element has
     NO explicit one of its own — an inherited wrapper color always loses to
     that, wrapper override or not. Showing the generic Text Color field on
     these blocks was actively misleading (it visibly did nothing for most of
     their content), so each gets targeted fields instead — see
     public/blocks/render.blade.php's matching @case, which applies each one
     directly to its own element. 'heading_color' and 'bg_color' are reused
     across multiple block types for their own heading/background element
     (same key, different element per $type) rather than one dedicated key
     per block — harmless dead weight on any block that doesn't render it,
     exactly like width_mode/border_style already are elsewhere. --}}
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
  @elseif ($type === 'notices')
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Heading color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['heading_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][heading_color]" value="{{ $s['heading_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Card background color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['card_bg_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][card_bg_color]" value="{{ $s['card_bg_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Card date color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['date_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][date_color]" value="{{ $s['date_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Card title color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['card_title_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][card_title_color]" value="{{ $s['card_title_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Card text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['card_text_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][card_text_color]" value="{{ $s['card_text_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
  @elseif ($type === 'staff')
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Heading color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['heading_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][heading_color]" value="{{ $s['heading_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Avatar ring color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['ring_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][ring_color]" value="{{ $s['ring_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Name color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['name_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][name_color]" value="{{ $s['name_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Designation color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['designation_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][designation_color]" value="{{ $s['designation_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
  @elseif ($type === 'hero')
    <div class="col-12">
      <label class="form-label small text-muted mb-1 d-block">{{ __('Background') }}</label>
      <div class="btn-group btn-group-sm w-100" role="group" aria-label="{{ __('Background Type') }}">
        <input type="radio" class="btn-check" name="{{ $prefix }}[style][bg_mode]" id="{{ $prefix }}-bgmode-image" value="image" autocomplete="off" @checked(($s['bg_mode'] ?? 'image') !== 'color')>
        <label class="btn btn-outline-secondary" for="{{ $prefix }}-bgmode-image"><i class="bi bi-image"></i> {{ __('Image') }}</label>

        <input type="radio" class="btn-check" name="{{ $prefix }}[style][bg_mode]" id="{{ $prefix }}-bgmode-color" value="color" autocomplete="off" @checked(($s['bg_mode'] ?? 'image') === 'color')>
        <label class="btn btn-outline-secondary" for="{{ $prefix }}-bgmode-color"><i class="bi bi-palette"></i> {{ __('Solid color') }}</label>
      </div>
    </div>
    <div class="col-12" data-depends-on="style.bg_mode" data-depends-values="image" @if(($s['bg_mode'] ?? 'image') !== 'image') style="display:none" @endif>
      <p class="form-text small text-muted mb-0">{{ __('Uses the Background Image field in the Content tab. Only one of Image or Solid Color is ever applied.') }}</p>
    </div>
    <div class="col-12" data-depends-on="style.bg_mode" data-depends-values="color" @if(($s['bg_mode'] ?? 'image') !== 'color') style="display:none" @endif>
      <label class="form-label small text-muted mb-1">{{ __('Background color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['bg_color'] ?? null) ?: '#1d4ed8' }}">
        <input type="text" name="{{ $prefix }}[style][bg_color]" value="{{ $s['bg_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Title text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['heading_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][heading_color]" value="{{ $s['heading_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Subtitle color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['subtitle_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][subtitle_color]" value="{{ $s['subtitle_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Button text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['button_text_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][button_text_color]" value="{{ $s['button_text_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Button background color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['button_bg_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][button_bg_color]" value="{{ $s['button_bg_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Button hover text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['button_hover_text_color'] ?? null) ?: '#000000' }}">
        <input type="text" name="{{ $prefix }}[style][button_hover_text_color]" value="{{ $s['button_hover_text_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Button hover background color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['button_hover_bg_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][button_hover_bg_color]" value="{{ $s['button_hover_bg_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
  @elseif ($type === 'announcement_bar')
    <div class="col-12">
      <p class="form-text small text-muted mb-0">{{ __('Background color is set in the Advanced tab.') }}</p>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Message text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['message_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][message_color]" value="{{ $s['message_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted mb-1">{{ __('Link text color') }}</label>
      <div class="input-group input-group-sm js-color-pair">
        <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['link_color'] ?? null) ?: '#ffffff' }}">
        <input type="text" name="{{ $prefix }}[style][link_color]" value="{{ $s['link_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
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
