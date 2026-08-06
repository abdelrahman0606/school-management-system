{{-- Universal per-block "Advanced" tab (was "Layout" — renamed in
     _card.blade.php's nav-link label only, internal tab-layout-* IDs are
     unchanged for backward JS/CSS compat). Five independently-collapsible
     sections (Layout/Border/Background/Responsive/ID & Class, none exclusive
     — opening one does not close another, matching how this control is
     commonly laid out elsewhere), plus the always-visible grid-columns
     control for Container/Grid blocks at the top, outside the accordion.
     Vars: $prefix, $layout, $style, $isGrid
     See docs/modules/28-elementor-block-editor-plan.md §7x (padding/margin),
     §7aa (this restructure — width/border/background/responsive), and §7ai
     (ID & Class). --}}
@php
  $s = $style ?? [];
  $cols = $layout['columns'] ?? [];
  $hide = $layout['hide'] ?? [];
  $breakpoints = ['desktop' => 'Desktop', 'laptop' => 'Laptop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'];
  $icons = ['desktop' => 'bi-display', 'laptop' => 'bi-laptop', 'tablet' => 'bi-tablet', 'mobile' => 'bi-phone'];
  $tabId = preg_replace('/[^a-zA-Z0-9]/', '-', $prefix);

  // One connected 4-box strip per spacing/border/radius property — still
  // stored as [style][{property}_{top|bottom|left|right}]. No visible
  // per-box T/B/L/R label — Bootstrap's .input-group already merges the 4
  // plain inputs into one seamless strip for free (no gap, only the
  // leftmost/rightmost corners rounded) once there's nothing BETWEEN them;
  // a title tooltip + aria-label carry Top/Bottom/Left/Right for anyone who
  // needs to know which box is which without four permanent labels eating
  // space. A "link values" button sits just outside the strip (not part of
  // its own rounded corners) — toggling it on and then typing in any one
  // box copies that value into the other three, exactly like the
  // width/height "constrain proportions" chain link common in design
  // tools; see the delegated 'input' listener in edit.blade.php's
  // js-spacing-link handling. Order requested: top, bottom, left, right
  // (not CSS shorthand order). $max lets Border Width use a tighter cap
  // than padding/margin/radius (see PageRenderService::sanitizeStyle()'s
  // $borderPx).
  $spacingSides = ['top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right'];
  $boxGroup = function (string $key, string $label, int $max) use ($prefix, $s, $spacingSides) {
    $out = '<div class="mb-2"><label class="form-label small text-muted mb-1">'.e(__($label)).'</label>';
    $out .= '<div class="d-flex align-items-center gap-1" data-spacing-link-group>';
    $out .= '<div class="input-group input-group-sm flex-nowrap">';
    foreach ($spacingSides as $side => $sideLabel) {
        $name = $prefix.'[style]['.$key.'_'.$side.']';
        $val = $s[$key.'_'.$side] ?? '';
        $out .= '<input type="number" min="0" max="'.$max.'" name="'.e($name).'" value="'.e($val).'" class="form-control form-control-sm js-spacing-input" placeholder="0" title="'.e(__($sideLabel)).'" aria-label="'.e(__($label)).' — '.e(__($sideLabel)).'">';
    }
    $out .= '</div>';
    $out .= '<button type="button" class="btn btn-sm btn-outline-secondary js-spacing-link" aria-pressed="false" title="'.e(__('Link Values Together')).'" aria-label="'.e(__('Link Values Together')).'"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>';
    $out .= '</div></div>';

    return $out;
  };
@endphp

@if ($isGrid)
  <p class="small text-muted mb-1">{{ __('Columns per row') }}</p>
  <div class="row g-2 mb-3">
    @foreach ($breakpoints as $bp => $bpLabel)
      <div class="col-3">
        <label class="form-label small text-muted mb-1"><i class="bi {{ $icons[$bp] }}"></i> {{ __($bpLabel) }}</label>
        <input type="number" min="1" max="6" name="{{ $prefix }}[layout][columns][{{ $bp }}]" value="{{ $cols[$bp] ?? '' }}" class="form-control form-control-sm" placeholder="—">
      </div>
    @endforeach
  </div>
@else
  <p class="small text-muted mb-3 fst-italic">{{ __('This block has no grid — column count doesn’t apply.') }}</p>
@endif

{{-- ── Layout ─────────────────────────────────────────────────────────── --}}
<div class="mb-1">
  <button type="button" class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold w-100 text-start d-flex justify-content-between align-items-center js-adv-section-toggle"
          data-bs-toggle="collapse" data-bs-target="#adv-layout-{{ $tabId }}" aria-expanded="true" aria-controls="adv-layout-{{ $tabId }}">
    <span>{{ __('Layout') }}</span>
    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
  </button>
  <div class="collapse show" id="adv-layout-{{ $tabId }}">
    <div class="pt-1 pb-2">
      {!! $boxGroup('margin', 'Margin (px)', 400) !!}
      {!! $boxGroup('padding', 'Padding (px)', 400) !!}
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">{{ __('Width') }}</label>
        <select name="{{ $prefix }}[style][width_mode]" class="form-select form-select-sm">
          <option value="default" @selected(empty($s['width_mode']) || ($s['width_mode'] ?? '') === 'default')>{{ __('Default') }}</option>
          <option value="full" @selected(($s['width_mode'] ?? '') === 'full')>{{ __('Full Width') }}</option>
          <option value="inline" @selected(($s['width_mode'] ?? '') === 'inline')>{{ __('Inline (Auto)') }}</option>
          <option value="custom" @selected(($s['width_mode'] ?? '') === 'custom')>{{ __('Custom') }}</option>
        </select>
      </div>
      <div class="mb-2" data-depends-on="style.width_mode" data-depends-values="custom" @if(($s['width_mode'] ?? '') !== 'custom') style="display:none" @endif>
        <label class="form-label small text-muted mb-1">{{ __('Custom Width') }}</label>
        <div class="input-group input-group-sm">
          <input type="number" min="0" max="1000" step="0.1" name="{{ $prefix }}[style][width_value]" value="{{ $s['width_value'] ?? '' }}" class="form-control form-control-sm">
          <select name="{{ $prefix }}[style][width_unit]" class="form-select form-select-sm" style="max-width:85px;">
            <option value="%" @selected(($s['width_unit'] ?? '%') === '%')>%</option>
            <option value="px" @selected(($s['width_unit'] ?? '') === 'px')>px</option>
            <option value="em" @selected(($s['width_unit'] ?? '') === 'em')>em</option>
            <option value="rem" @selected(($s['width_unit'] ?? '') === 'rem')>rem</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ── Border ─────────────────────────────────────────────────────────── --}}
<div class="mb-1">
  <button type="button" class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold w-100 text-start d-flex justify-content-between align-items-center js-adv-section-toggle"
          data-bs-toggle="collapse" data-bs-target="#adv-border-{{ $tabId }}" aria-expanded="false" aria-controls="adv-border-{{ $tabId }}">
    <span>{{ __('Border') }}</span>
    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
  </button>
  <div class="collapse" id="adv-border-{{ $tabId }}">
    <div class="pt-1 pb-2">
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">{{ __('Border Type') }}</label>
        <select name="{{ $prefix }}[style][border_style]" class="form-select form-select-sm">
          <option value="none" @selected(empty($s['border_style']) || ($s['border_style'] ?? '') === 'none')>{{ __('None') }}</option>
          <option value="solid" @selected(($s['border_style'] ?? '') === 'solid')>{{ __('Solid') }}</option>
          <option value="dashed" @selected(($s['border_style'] ?? '') === 'dashed')>{{ __('Dashed') }}</option>
          <option value="dotted" @selected(($s['border_style'] ?? '') === 'dotted')>{{ __('Dotted') }}</option>
          <option value="double" @selected(($s['border_style'] ?? '') === 'double')>{{ __('Double') }}</option>
        </select>
      </div>
      <div data-depends-on="style.border_style" data-depends-values="solid,dashed,dotted,double"
           @if(empty($s['border_style']) || $s['border_style'] === 'none') style="display:none" @endif>
        {!! $boxGroup('border_width', 'Border Width (px)', 50) !!}
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">{{ __('Border Color') }}</label>
          <div class="input-group input-group-sm js-color-pair">
            <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['border_color'] ?? null) ?: '#000000' }}">
            <input type="text" name="{{ $prefix }}[style][border_color]" value="{{ $s['border_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
          </div>
        </div>
      </div>
      {!! $boxGroup('radius', 'Border Radius (px)', 400) !!}
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">{{ __('Shadow') }}</label>
        <select name="{{ $prefix }}[style][shadow]" class="form-select form-select-sm">
          <option value="" @selected(empty($s['shadow']))>{{ __('None') }}</option>
          <option value="sm" @selected(($s['shadow'] ?? '') === 'sm')>{{ __('Small') }}</option>
          <option value="md" @selected(($s['shadow'] ?? '') === 'md')>{{ __('Medium') }}</option>
          <option value="lg" @selected(($s['shadow'] ?? '') === 'lg')>{{ __('Large') }}</option>
        </select>
      </div>
    </div>
  </div>
</div>

{{-- ── Background ─────────────────────────────────────────────────────── --}}
<div class="mb-1">
  <button type="button" class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold w-100 text-start d-flex justify-content-between align-items-center js-adv-section-toggle"
          data-bs-toggle="collapse" data-bs-target="#adv-bg-{{ $tabId }}" aria-expanded="false" aria-controls="adv-bg-{{ $tabId }}">
    <span>{{ __('Background') }}</span>
    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
  </button>
  <div class="collapse" id="adv-bg-{{ $tabId }}">
    <div class="pt-1 pb-2">
      {{-- One universal background control for every block type (§7ah) —
           an explicit three-way choice (Image/Solid Color/Gradient, never
           more than one applied at once) instead of two separate fields
           with a silent "image wins if both are set" priority. A page
           saved before 'bg_mode' existed has it unset; default the radio
           to whichever mode its existing stored value actually implies
           (Solid Color if only bg_color was ever set, Image otherwise —
           matching BlockPresentation::inlineStyle()'s own fallback
           priority) so re-opening an old block shows the option that's
           actually already in effect, not just always "Image". --}}
      @php
        $bgMode = $s['bg_mode'] ?? ((! empty($s['bg_color']) && empty($s['bg_image'])) ? 'color' : 'image');
      @endphp
      <div class="mb-2">
        <div class="btn-group btn-group-sm w-100" role="group" aria-label="{{ __('Background Type') }}">
          <input type="radio" class="btn-check" name="{{ $prefix }}[style][bg_mode]" id="{{ $tabId }}-bgmode-image" value="image" autocomplete="off" @checked($bgMode === 'image')>
          <label class="btn btn-outline-secondary" for="{{ $tabId }}-bgmode-image"><i class="bi bi-image"></i> {{ __('Image') }}</label>

          <input type="radio" class="btn-check" name="{{ $prefix }}[style][bg_mode]" id="{{ $tabId }}-bgmode-color" value="color" autocomplete="off" @checked($bgMode === 'color')>
          <label class="btn btn-outline-secondary" for="{{ $tabId }}-bgmode-color"><i class="bi bi-palette"></i> {{ __('Solid color') }}</label>

          <input type="radio" class="btn-check" name="{{ $prefix }}[style][bg_mode]" id="{{ $tabId }}-bgmode-gradient" value="gradient" autocomplete="off" @checked($bgMode === 'gradient')>
          <label class="btn btn-outline-secondary" for="{{ $tabId }}-bgmode-gradient"><i class="bi bi-paint-bucket"></i> {{ __('Gradient') }}</label>
        </div>
      </div>
      <div data-depends-on="style.bg_mode" data-depends-values="image" @if($bgMode !== 'image') style="display:none" @endif>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">{{ __('Background image URL') }}</label>
          <input type="text" name="{{ $prefix }}[style][bg_image]" value="{{ $s['bg_image'] ?? '' }}" class="form-control form-control-sm" placeholder="https://…">
        </div>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1 d-flex justify-content-between">
            <span>{{ __('Overlay darkness') }}</span>
            <span class="text-muted">{{ $s['bg_overlay'] ?? 0 }}%</span>
          </label>
          <input type="range" min="0" max="100" name="{{ $prefix }}[style][bg_overlay]" value="{{ $s['bg_overlay'] ?? 0 }}" class="form-range js-range-echo">
        </div>
      </div>
      <div class="mb-2" data-depends-on="style.bg_mode" data-depends-values="color" @if($bgMode !== 'color') style="display:none" @endif>
        <label class="form-label small text-muted mb-1">{{ __('Background color') }}</label>
        <div class="input-group input-group-sm js-color-pair">
          <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['bg_color'] ?? null) ?: '#ffffff' }}">
          <input type="text" name="{{ $prefix }}[style][bg_color]" value="{{ $s['bg_color'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
        </div>
      </div>
      <div data-depends-on="style.bg_mode" data-depends-values="gradient" @if($bgMode !== 'gradient') style="display:none" @endif>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">{{ __('Gradient start color') }}</label>
          <div class="input-group input-group-sm js-color-pair">
            <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['bg_gradient_start'] ?? null) ?: '#1d4ed8' }}">
            <input type="text" name="{{ $prefix }}[style][bg_gradient_start]" value="{{ $s['bg_gradient_start'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">{{ __('Gradient end color') }}</label>
          <div class="input-group input-group-sm js-color-pair">
            <input type="color" class="form-control form-control-color js-color-swatch" value="{{ ($s['bg_gradient_end'] ?? null) ?: '#f59e0b' }}">
            <input type="text" name="{{ $prefix }}[style][bg_gradient_end]" value="{{ $s['bg_gradient_end'] ?? '' }}" class="form-control js-color-text" placeholder="{{ __('None') }}" maxlength="9">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">{{ __('Direction (degrees)') }}</label>
          <input type="number" min="0" max="360" name="{{ $prefix }}[style][bg_gradient_angle]" value="{{ $s['bg_gradient_angle'] ?? 135 }}" class="form-control form-control-sm">
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ── Responsive ─────────────────────────────────────────────────────── --}}
<div class="mb-1">
  <button type="button" class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold w-100 text-start d-flex justify-content-between align-items-center js-adv-section-toggle"
          data-bs-toggle="collapse" data-bs-target="#adv-responsive-{{ $tabId }}" aria-expanded="false" aria-controls="adv-responsive-{{ $tabId }}">
    <span>{{ __('Responsive') }}</span>
    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
  </button>
  <div class="collapse" id="adv-responsive-{{ $tabId }}">
    <div class="pt-1 pb-2 d-flex flex-column gap-2">
      @foreach ($breakpoints as $bp => $bpLabel)
        @php $hideId = 'hide-'.$tabId.'-'.$bp; @endphp
        <div class="d-flex align-items-center justify-content-between">
          <label class="form-check-label small mb-0" for="{{ $hideId }}"><i class="bi {{ $icons[$bp] }}" aria-hidden="true"></i> {{ __('Hide on') }} {{ __($bpLabel) }}</label>
          <div class="form-check form-switch mb-0">
            <input type="hidden" name="{{ $prefix }}[layout][hide][{{ $bp }}]" value="0">
            <input type="checkbox" role="switch" name="{{ $prefix }}[layout][hide][{{ $bp }}]" value="1" class="form-check-input" id="{{ $hideId }}" @checked(! empty($hide[$bp]))>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- ── ID & Class ─────────────────────────────────────────────────────── --}}
<div class="mb-1">
  <button type="button" class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold w-100 text-start d-flex justify-content-between align-items-center js-adv-section-toggle"
          data-bs-toggle="collapse" data-bs-target="#adv-idclass-{{ $tabId }}" aria-expanded="false" aria-controls="adv-idclass-{{ $tabId }}">
    <span>{{ __('ID & Class') }}</span>
    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
  </button>
  <div class="collapse" id="adv-idclass-{{ $tabId }}">
    <div class="pt-1 pb-2">
      {{-- For custom CSS/JS hooks only — no field above ever sets these, and
           nothing else in the editor reads them back out. Whitelisted to
           safe id/class characters server-side (PageRenderService::sanitizeStyle()),
           so an invalid value is simply dropped on save rather than shown as
           an error here; the pattern/title below just steers a well-meaning
           admin toward a value that will actually be kept. --}}
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">{{ __('CSS ID') }}</label>
        <input type="text" name="{{ $prefix }}[style][custom_id]" value="{{ $s['custom_id'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('e.g. site-hero') }}" pattern="[A-Za-z][A-Za-z0-9_-]*" maxlength="64" title="{{ __('Letters, numbers, hyphen, underscore — must start with a letter.') }}">
        <div class="form-text small">{{ __('Sets id="…" on this block for custom CSS or JS. Must start with a letter.') }}</div>
      </div>
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">{{ __('CSS Class') }}</label>
        <input type="text" name="{{ $prefix }}[style][custom_class]" value="{{ $s['custom_class'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('e.g. promo-banner highlight') }}" maxlength="200">
        <div class="form-text small">{{ __('One or more class names, separated by spaces, added to this block for custom CSS.') }}</div>
      </div>
    </div>
  </div>
</div>
