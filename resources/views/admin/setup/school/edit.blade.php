@extends('layouts.admin')
@section('title', __('School Settings'))
@section('content')
    @include('admin.partials.page-header', ['title' => __('School settings'), 'crumbs' => [__('Setup'), __('School settings')]])

    <form method="POST" action="{{ route('admin.school.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">{{ __('School Information') }}</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">{{ __('Name') }} <span
                                        class="text-danger">*</span></label>
                                <input name="name" class="form-control" value="{{ old('name', $school->name) }}" required>
                            </div>
                            <div class="col-md-4"><label class="form-label">{{ __('Established') }}</label>
                                <input type="number" name="established" class="form-control" min="1800"
                                    max="{{ date('Y') }}"
                                    value="{{ old('established', optional($school->established)->format('Y')) }}"
                                    placeholder="{{ __('E.g. 1942') }}">
                            </div>
                            <div class="col-md-12"><label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $school->email) }}">
                            </div>
                            <div class="col-12"><label class="form-label">{{ __('Address') }}</label>
                                <input type="text" name="address" class="form-control"
                                    value="{{ old('address', $school->address) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">{{ __('School Codes') }}</div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Up to three institution codes with custom labels (e.g. EIIN, School
                            code, Technical branch code). Leave a code blank to hide it from the site header.</p>
                        <div class="row g-3">
                            <div class="col-md-5"><label class="form-label">{{ __('Field 1 Label') }}</label>
                                <input name="institution_code_label" class="form-control"
                                    value="{{ old('institution_code_label', $school->institution_code_label) }}"
                                    placeholder="{{ __('E.g. EIIN') }}">
                            </div>
                            <div class="col-md-7"><label class="form-label">{{ __('Field 1 Code') }}</label>
                                <input name="institution_code" class="form-control"
                                    value="{{ old('institution_code', $school->institution_code) }}">
                            </div>
                            <div class="col-md-5"><label class="form-label">{{ __('Field 2 Label') }}</label>
                                <input name="school_code_label" class="form-control"
                                    value="{{ old('school_code_label', $school->school_code_label) }}"
                                    placeholder="{{ __('E.g. School Code') }}">
                            </div>
                            <div class="col-md-7"><label class="form-label">{{ __('Field 2 Code') }}</label>
                                <input name="school_code" class="form-control"
                                    value="{{ old('school_code', $school->school_code) }}">
                            </div>
                            <div class="col-md-5"><label class="form-label">{{ __('Field 3 Label') }}</label>
                                <input name="technical_branch_code_label" class="form-control"
                                    value="{{ old('technical_branch_code_label', $school->technical_branch_code_label) }}"
                                    placeholder="{{ __('E.g. Technical Branch Code') }}">
                            </div>
                            <div class="col-md-7"><label class="form-label">{{ __('Field 3 Code') }}</label>
                                <input name="technical_branch_code" class="form-control"
                                    value="{{ old('technical_branch_code', $school->technical_branch_code) }}">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-5">

                <div class="card mb-4">
                    <div class="card-header">{{ __('Locale') }}</div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">{{ __('Country') }}</label>
                            <select name="country_code" class="form-select js-select">
                                <option value="">— Select country —</option>
                                @foreach ($countries as $code => $name)
                                    <option value="{{ $code }}" @selected(old('country_code', $school->country_code) === $code)>
                                        {{ $name }} ({{ $code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">{{ __('Currency') }} <span class="text-danger">*</span></label>
                            <select name="currency" class="form-select js-select" required>
                                @foreach ($currencies as $code => $name)
                                    <option value="{{ $code }}" @selected(old('currency', $school->currency) === $code)>
                                        {{ $code }} — {{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">{{ __('Timezone') }} <span class="text-danger">*</span></label>
                            <select name="timezone" class="form-select js-select" required>
                                @foreach ($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', $school->timezone) === $tz)>{{ $tz }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">{{ __('Language') }} <span class="text-danger">*</span></label>
                            <select name="locale" class="form-select js-select" required>
                                @foreach ($languages as $code => $name)
                                    <option value="{{ $code }}" @selected(old('locale', $school->locale) === $code)>{{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="form-label">{{ __('Academic Year Pattern') }} <span class="text-danger">*</span></label>
                            <select name="academic_year_pattern" class="form-select" required>
                                @foreach ($patterns as $val => $lbl)
                                    <option value="{{ $val }}" @selected(old('academic_year_pattern', $school->academic_year_pattern) === $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ __('Mobile Numbers') }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addPhone"><i
                                class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
                    </div>
                    <div class="card-body">
                        <div id="phoneRows" class="vstack gap-2">
                            @php $phones = old('phones', $school->phones->map(fn($p) => ['phone' => $p->phone, 'is_primary' => $p->is_primary, 'show_in_header' => $p->show_in_header])->all()); @endphp
                            @forelse ($phones as $i => $p)
                                <div class="input-group phone-row">
                                    <input name="phones[{{ $i }}][phone]" class="form-control" placeholder="{{ __('Phone') }}"
                                        value="{{ $p['phone'] ?? '' }}">
                                    <input type="hidden" name="phones[{{ $i }}][show_in_header]" value="0">
                                    <span class="input-group-text">
                                        <input class="form-check-input mt-0 me-1" type="checkbox"
                                            name="phones[{{ $i }}][show_in_header]" value="1" id="hdr{{ $i }}" {{ ($p['show_in_header'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="hdr{{ $i }}">{{ __('Header') }}</label>
                                    </span>
                                    <button type="button" class="btn btn-outline-danger rm-phone"><i
                                            class="bi bi-trash"></i></button>
                                </div>
                            @empty
                            @endforelse
                        </div>
                        <div class="form-text mt-2">{{ __('Tick') }} <strong>{{ __('Top Bar') }}</strong> to show a number
                            (clickable, tel:) in the site header's top bar.</div>
                    </div>
                </div>

            </div>
        </div>

        @php
            $logoUrl = \App\Support\Media::url($school->logo);
            $faviconUrl = \App\Support\Media::url($settings->favicon);
            $ogUrl = \App\Support\Media::url($settings->og_image);
        @endphp
        <div class="row g-4 mt-0">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Branding &amp; Appearance</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">{{ __('Logo') }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="border rounded d-inline-flex align-items-center justify-content-center bg-light flex-shrink-0"
                                        style="width:48px;height:48px;overflow:hidden;">
                                        @if($logoUrl)<img src="{{ $logoUrl }}" alt="logo"
                                        style="max-width:100%;max-height:100%;">@else<i
                                            class="bi bi-image text-muted"></i>@endif
                                    </span>
                                    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml"
                                        class="form-control form-control-sm">
                                </div>
                                <div class="form-text">PNG recommended, 512×512.</div>
                            </div>
                            <div class="col-md-6"><label class="form-label">{{ __('Favicon') }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="border rounded d-inline-flex align-items-center justify-content-center bg-light flex-shrink-0"
                                        style="width:48px;height:48px;overflow:hidden;">
                                        @if($faviconUrl)<img src="{{ $faviconUrl }}" alt="favicon"
                                        style="max-width:100%;max-height:100%;">@else<i
                                            class="bi bi-star text-muted"></i>@endif
                                    </span>
                                    <input type="file" name="favicon" accept="image/png,image/x-icon"
                                        class="form-control form-control-sm">
                                </div>
                                <div class="form-text">PNG recommended, 512×512.</div>
                            </div>

                            <div class="col-sm-6"><label class="form-label">{{ __('Primary Color') }}</label>
                                <input type="color" name="primary_color" class="form-control form-control-color w-100"
                                    value="{{ old('primary_color', $settings->primary_color ?: '#1d4ed8') }}">
                            </div>
                            <div class="col-sm-6"><label class="form-label">{{ __('Accent Color') }}</label>
                                <input type="color" name="accent_color" class="form-control form-control-color w-100"
                                    value="{{ old('accent_color', $settings->accent_color ?: '#f59e0b') }}">
                            </div>
                            <div class="col-sm-6"><label class="form-label">{{ __('Heading Color') }}</label>
                                <input type="color" name="heading_color" class="form-control form-control-color w-100"
                                    value="{{ old('heading_color', $settings->heading_color ?: '#0f172a') }}">
                            </div>
                            <div class="col-md-6"><label class="form-label">{{ __('Topbar Text Color') }}</label>
                                <input type="color" name="topbar_text_color" class="form-control form-control-color w-100"
                                    value="{{ old('topbar_text_color', $settings->topbar_text_color ?: '#ffffff') }}">
                            </div>

                            <div class="col-md-6"><label class="form-label">{{ __('Announcement Ticker') }}</label>
                                <select name="ticker_position" class="form-select">
                                    @foreach (['below_nav' => 'Show below the menu bar', 'above_nav' => 'Show above the menu bar', 'hidden' => 'Hidden'] as $val => $lbl)
                                        <option value="{{ $val }}" @selected(old('ticker_position', $settings->ticker_position ?? 'below_nav') === $val)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>
                        <div class="form-text mt-2">Primary color drives the top bar, brand text, nav, and buttons. The
                            ticker pauses on hover and hides automatically when there are no active notices. Header phone
                            numbers are set on the Mobile Numbers list above (tick “Header”).</div>
                    </div>
                </div>

                <details class="card mt-4">
                    <summary class="card-header" style="cursor:pointer;">{{ __('Advanced Theme (Typography, Buttons, Backgrounds)') }}</summary>
                    <div class="card-body">
                        <p class="text-muted small mb-3">{{ __('Everything here is optional — leave a field blank and the site keeps its current look for that value. Nothing below changes anything until you set it.') }}</p>

                        <h6 class="text-uppercase small fw-bold text-muted mb-2">{{ __('Additional Colors') }}</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-4"><label class="form-label">{{ __('Secondary Color') }}</label>
                                <input type="color" name="secondary_color" class="form-control form-control-color w-100"
                                    value="{{ old('secondary_color', $settings->secondary_color ?: '#0f172a') }}">
                                <div class="form-text">{{ __('Footer background.') }}</div>
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Page Background') }}</label>
                                <input type="color" name="background_color" class="form-control form-control-color w-100"
                                    value="{{ old('background_color', $settings->background_color ?: '#ffffff') }}">
                                <div class="form-text">{{ __('Overridden by Global Background below if that\'s also set.') }}</div>
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Card / Surface Color') }}</label>
                                <input type="color" name="surface_color" class="form-control form-control-color w-100"
                                    value="{{ old('surface_color', $settings->surface_color ?: '#ffffff') }}">
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Body Text Color') }}</label>
                                <input type="color" name="text_color" class="form-control form-control-color w-100"
                                    value="{{ old('text_color', $settings->text_color ?: '#1f2937') }}">
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Link Color') }}</label>
                                <input type="color" name="link_color" class="form-control form-control-color w-100"
                                    value="{{ old('link_color', $settings->link_color ?: ($settings->primary_color ?: '#1d4ed8')) }}">
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Link Hover Color') }}</label>
                                <input type="color" name="link_hover_color" class="form-control form-control-color w-100"
                                    value="{{ old('link_hover_color', $settings->link_hover_color ?: '#0f172a') }}">
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Border Color') }}</label>
                                <input type="color" name="border_color" class="form-control form-control-color w-100"
                                    value="{{ old('border_color', $settings->border_color ?: '#e5e7eb') }}">
                            </div>
                        </div>

                        <h6 class="text-uppercase small fw-bold text-muted mb-2">{{ __('Typography') }}</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-4"><label class="form-label">{{ __('Heading Font') }}</label>
                                <select name="font_heading" class="form-select">
                                    <option value="">{{ __('System default') }}</option>
                                    @foreach (\App\Modules\Website\Models\SiteSetting::FONTS as $font)
                                        <option value="{{ $font }}" @selected(old('font_heading', $settings->font_heading) === $font)>{{ $font }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Body Font') }}</label>
                                <select name="font_body" class="form-select">
                                    <option value="">{{ __('System default') }}</option>
                                    @foreach (\App\Modules\Website\Models\SiteSetting::FONTS as $font)
                                        <option value="{{ $font }}" @selected(old('font_body', $settings->font_body) === $font)>{{ $font }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('Loaded from Google Fonts only when set — no extra request otherwise.') }}</div>
                            </div>
                            <div class="col-sm-2"><label class="form-label">{{ __('Base Size (px)') }}</label>
                                <input type="number" name="base_font_size" class="form-control" min="12" max="24"
                                    value="{{ old('base_font_size', $settings->base_font_size) }}" placeholder="16">
                            </div>
                            <div class="col-sm-2"><label class="form-label">{{ __('Page Width (px)') }}</label>
                                <input type="number" name="container_width" class="form-control" min="960" max="1600" step="10"
                                    value="{{ old('container_width', $settings->container_width) }}" placeholder="1140">
                            </div>
                        </div>

                        <h6 class="text-uppercase small fw-bold text-muted mb-2">{{ __('Buttons') }}</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-3"><label class="form-label">{{ __('Corner Radius (px)') }}</label>
                                <input type="number" name="btn_radius" class="form-control" min="0" max="50"
                                    value="{{ old('btn_radius', $settings->btn_radius) }}" placeholder="{{ __('Default') }}">
                            </div>
                            <div class="col-sm-3"><label class="form-label">{{ __('Font Weight') }}</label>
                                <select name="btn_font_weight" class="form-select">
                                    <option value="">{{ __('Default') }}</option>
                                    @foreach (\App\Modules\Website\Models\SiteSetting::BTN_FONT_WEIGHTS as $w)
                                        <option value="{{ $w }}" @selected(old('btn_font_weight', $settings->btn_font_weight) === $w)>{{ $w }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-3"><label class="form-label">{{ __('Hover Transition (ms)') }}</label>
                                <input type="number" name="btn_transition_ms" class="form-control" min="0" max="1000" step="10"
                                    value="{{ old('btn_transition_ms', $settings->btn_transition_ms) }}" placeholder="150">
                            </div>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-sm-3"><label class="form-label small">{{ __('Filled Button Background') }}</label>
                                <input type="color" name="btn_filled_bg" class="form-control form-control-color w-100"
                                    value="{{ old('btn_filled_bg', data_get($settings->btn_filled_json, 'bg') ?: ($settings->primary_color ?: '#1d4ed8')) }}">
                            </div>
                            <div class="col-sm-3"><label class="form-label small">{{ __('Filled Button Text') }}</label>
                                <input type="color" name="btn_filled_text" class="form-control form-control-color w-100"
                                    value="{{ old('btn_filled_text', data_get($settings->btn_filled_json, 'text') ?: '#ffffff') }}">
                            </div>
                            <div class="col-sm-3"><label class="form-label small">{{ __('Outline Button Border') }}</label>
                                <input type="color" name="btn_outline_border" class="form-control form-control-color w-100"
                                    value="{{ old('btn_outline_border', data_get($settings->btn_outline_json, 'border') ?: ($settings->primary_color ?: '#1d4ed8')) }}">
                            </div>
                            <div class="col-sm-3"><label class="form-label small">{{ __('Outline Button Text') }}</label>
                                <input type="color" name="btn_outline_text" class="form-control form-control-color w-100"
                                    value="{{ old('btn_outline_text', data_get($settings->btn_outline_json, 'text') ?: ($settings->primary_color ?: '#1d4ed8')) }}">
                            </div>
                        </div>

                        <h6 class="text-uppercase small fw-bold text-muted mb-2">{{ __('Global Background') }}</h6>
                        <p class="text-muted small mb-2">{{ __('Set Type to Image to layer a background image over this color (used as its tint) instead of the plain Page Background above.') }}</p>
                        <div class="row g-3">
                            <div class="col-sm-4"><label class="form-label">{{ __('Type') }}</label>
                                <select name="global_bg_type" class="form-select">
                                    @foreach (\App\Modules\Website\Models\SiteSetting::GLOBAL_BG_TYPES as $type)
                                        <option value="{{ $type }}" @selected(old('global_bg_type', $settings->global_bg_type) === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Override / Tint Color') }}</label>
                                <input type="color" name="global_bg_color" class="form-control form-control-color w-100"
                                    value="{{ old('global_bg_color', $settings->global_bg_color ?: ($settings->background_color ?: '#ffffff')) }}">
                            </div>
                            <div class="col-sm-4"><label class="form-label">{{ __('Overlay Darkness') }}</label>
                                <input type="range" name="global_bg_overlay" class="form-range" min="0" max="1" step="0.05"
                                    value="{{ old('global_bg_overlay', $settings->global_bg_overlay ?? 0) }}">
                                <div class="form-text">{{ __('Only applies to a background image.') }}</div>
                            </div>
                            <div class="col-12"><label class="form-label">{{ __('Background Image') }}</label>
                                @php $globalBgImageUrl = \App\Support\Media::url($settings->global_bg_image ?? null); @endphp
                                @if($globalBgImageUrl)
                                    <div class="mb-1"><img src="{{ $globalBgImageUrl }}" alt="" class="img-fluid rounded" style="max-height:90px;"></div>
                                @endif
                                <input type="file" name="global_bg_image" accept="image/*" class="form-control form-control-sm">
                                <div class="form-text">{{ __('Only used when Type is set to Image.') }}</div>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">SEO &amp; social share</div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">{{ __('Meta Title') }}</label>
                            <input name="meta_title" class="form-control"
                                value="{{ old('meta_title', $settings->meta_title) }}"
                                placeholder="{{ __('Defaults To The Site Name') }}">
                        </div>
                        <div class="mb-3"><label class="form-label">{{ __('Meta Description') }}</label>
                            <textarea name="meta_description" rows="2" class="form-control"
                                placeholder="{{ __('Short Description For Search Engines') }}">{{ old('meta_description', $settings->meta_description) }}</textarea>
                        </div>
                        <div class="mb-0"><label class="form-label">{{ __('Social Share Image') }}</label>
                            @if($ogUrl)
                                <div class="mb-1"><img src="{{ $ogUrl }}" alt="share image" class="img-fluid rounded"
                            style="max-height:90px;"></div>@endif
                            <input type="file" name="og_image" accept="image/*" class="form-control form-control-sm">
                            <div class="form-text">Featured image shown when the site is shared on social media (1200×630
                                recommended).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Translations — docs/modules/30-multilingual-content-plan.md Phase
             4. Unlike the Pages/Menus editors (separate resource per locale,
             so they use a page-reload language tab), School/SiteSetting are
             singleton rows — every language's overrides for these ~10 fields
             save together in this same form submit. One collapsed <details>
             panel per active non-default language, matching this page's own
             "Advanced Theme" collapsible convention above. --}}
        <div class="row g-4 mt-0">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">{{ __('Translations') }}</div>
                    <div class="card-body">
                        @if ($contentLanguages->isEmpty())
                            <p class="text-muted small mb-0">{{ __('Add another active language in Settings > Languages to translate this content.') }}</p>
                        @else
                            <p class="text-muted small mb-3">{{ __('Leave a field blank to fall back to the default-language content above.') }}</p>
                            @foreach ($contentLanguages as $lang)
                                @php
                                    $t = old('translations.'.$lang->code, [
                                        'name' => $school->trans('name', $lang->code),
                                        'institution_code_label' => $school->trans('institution_code_label', $lang->code),
                                        'institution_code' => $school->trans('institution_code', $lang->code),
                                        'school_code_label' => $school->trans('school_code_label', $lang->code),
                                        'school_code' => $school->trans('school_code', $lang->code),
                                        'technical_branch_code_label' => $school->trans('technical_branch_code_label', $lang->code),
                                        'technical_branch_code' => $school->trans('technical_branch_code', $lang->code),
                                        'address' => $school->trans('address', $lang->code),
                                        'meta_title' => $settings->trans('meta_title', $lang->code),
                                        'meta_description' => $settings->trans('meta_description', $lang->code),
                                    ]);
                                @endphp
                                <details class="card mb-3">
                                    <summary class="card-header" style="cursor:pointer;">
                                        @if ($lang->flag){{ $lang->flag }} @endif {{ $lang->native_name }}
                                    </summary>
                                    <div class="card-body">
                                        {{-- AI-assisted draft translation (docs/modules/30-multilingual-content-plan.md
                                             Phase 5) — submits the small standalone form below (outside this page's
                                             main <form>, since forms can't nest) via id, so this button never
                                             triggers the whole School Settings save. Free MyMemory API, no key
                                             needed; only fills fields that are still empty above. --}}
                                        <button type="button" class="btn btn-sm btn-outline-secondary mb-2"
                                                onclick="document.getElementById('ai-suggest-{{ $lang->code }}').submit()">
                                            <i class="bi bi-magic"></i> {{ __('Suggest translations (AI)') }}
                                        </button>
                                        <p class="form-text mt-0 mb-3">{{ __('Fills only the empty fields below using a free machine-translation service — always review a suggestion before saving.') }}</p>
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">{{ __('Name') }}</label>
                                                <input name="translations[{{ $lang->code }}][name]" class="form-control"
                                                    value="{{ $t['name'] }}" placeholder="{{ $school->name }}">
                                            </div>
                                            <div class="col-md-6"><label class="form-label">{{ __('Address') }}</label>
                                                <input name="translations[{{ $lang->code }}][address]" class="form-control"
                                                    value="{{ $t['address'] }}" placeholder="{{ $school->address }}">
                                            </div>
                                            <div class="col-md-4"><label class="form-label">{{ __('Field 1 Label') }}</label>
                                                <input name="translations[{{ $lang->code }}][institution_code_label]" class="form-control"
                                                    value="{{ $t['institution_code_label'] }}" placeholder="{{ $school->institution_code_label }}">
                                            </div>
                                            <div class="col-md-8"><label class="form-label">{{ __('Field 1 Code') }}</label>
                                                <input name="translations[{{ $lang->code }}][institution_code]" class="form-control"
                                                    value="{{ $t['institution_code'] }}" placeholder="{{ $school->institution_code }}">
                                            </div>
                                            <div class="col-md-4"><label class="form-label">{{ __('Field 2 Label') }}</label>
                                                <input name="translations[{{ $lang->code }}][school_code_label]" class="form-control"
                                                    value="{{ $t['school_code_label'] }}" placeholder="{{ $school->school_code_label }}">
                                            </div>
                                            <div class="col-md-8"><label class="form-label">{{ __('Field 2 Code') }}</label>
                                                <input name="translations[{{ $lang->code }}][school_code]" class="form-control"
                                                    value="{{ $t['school_code'] }}" placeholder="{{ $school->school_code }}">
                                            </div>
                                            <div class="col-md-4"><label class="form-label">{{ __('Field 3 Label') }}</label>
                                                <input name="translations[{{ $lang->code }}][technical_branch_code_label]" class="form-control"
                                                    value="{{ $t['technical_branch_code_label'] }}" placeholder="{{ $school->technical_branch_code_label }}">
                                            </div>
                                            <div class="col-md-8"><label class="form-label">{{ __('Field 3 Code') }}</label>
                                                <input name="translations[{{ $lang->code }}][technical_branch_code]" class="form-control"
                                                    value="{{ $t['technical_branch_code'] }}" placeholder="{{ $school->technical_branch_code }}">
                                            </div>
                                            <div class="col-md-6"><label class="form-label">{{ __('Meta Title') }}</label>
                                                <input name="translations[{{ $lang->code }}][meta_title]" class="form-control"
                                                    value="{{ $t['meta_title'] }}" placeholder="{{ $settings->meta_title }}">
                                            </div>
                                            <div class="col-md-6"><label class="form-label">{{ __('Meta Description') }}</label>
                                                <textarea name="translations[{{ $lang->code }}][meta_description]" rows="2" class="form-control"
                                                    placeholder="{{ $settings->meta_description }}">{{ $t['meta_description'] }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save Settings') }}</button></div>
    </form>

    {{-- One tiny standalone form per language, submitted via JS from the
         "Suggest translations (AI)" button inside each language panel above
         — kept OUTSIDE the main settings <form> since HTML forms can't
         nest. docs/modules/30-multilingual-content-plan.md Phase 5. --}}
    @foreach ($contentLanguages as $lang)
        <form method="POST" action="{{ route('admin.school.translations.suggest') }}" id="ai-suggest-{{ $lang->code }}" class="d-none">
            @csrf
            <input type="hidden" name="locale" value="{{ $lang->code }}">
        </form>
    @endforeach

    <form method="POST" action="{{ route('admin.modules.update') }}" class="mt-4">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header">{{ __('Optional Modules') }}</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Enable the optional modules your school uses. Disabled modules are
                    hidden from the menu and their APIs return 403.</p>
                <div class="row g-2">
                    @foreach ($moduleSettings as $s)
                        @php [$label, $desc] = $moduleMeta[$s['module']] ?? [ucfirst($s['module']), '']; @endphp
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between border rounded p-3 h-100">
                                <div class="pe-3">
                                    <div class="fw-semibold">{{ $label }}</div>
                                    <div class="text-muted small">{{ $desc }}</div>
                                </div>
                                <div class="form-check form-switch fs-5 mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="enabled[]"
                                        value="{{ $s['module'] }}" id="mod-{{ $s['module'] }}" @checked($s['is_enabled'])>
                                    <label class="form-check-label visually-hidden"
                                        for="mod-{{ $s['module'] }}">{{ $label }}</label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="text-end mt-3"><button class="btn btn-primary"><i class="bi bi-save"></i> Save
                        modules</button></div>
            </div>
        </div>
    </form>

    @php $hours = $school->openingHours->keyBy('day_of_week');
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; @endphp
    <form method="POST" action="{{ route('admin.school.hours') }}" class="mt-4">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header">{{ __('Opening Hours') }} <span class="text-muted small">(drives attendance working days)</span>
            </div>
            <div class="card-body">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Day') }}</th>
                            <th style="width:120px">{{ __('Open') }}</th>
                            <th>{{ __('From') }}</th>
                            <th>{{ __('To') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dayNames as $dow => $name)
                            @php $h = $hours[$dow] ?? null; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $name }}</td>
                                <td>
                                    <div class="form-check form-switch mb-0"><input type="hidden"
                                            name="days[{{ $dow }}][is_open]" value="0"><input class="form-check-input"
                                            type="checkbox" name="days[{{ $dow }}][is_open]" value="1" @checked($h ? $h->is_open : true)></div>
                                </td>
                                <td><input type="time" name="days[{{ $dow }}][open_time]" class="form-control form-control-sm"
                                        value="{{ $h && $h->open_time ? \Illuminate\Support\Str::of($h->open_time)->substr(0, 5) : '' }}">
                                </td>
                                <td><input type="time" name="days[{{ $dow }}][close_time]" class="form-control form-control-sm"
                                        value="{{ $h && $h->close_time ? \Illuminate\Support\Str::of($h->close_time)->substr(0, 5) : '' }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="text-end mt-2"><button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save Hours') }}</button>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var wrap = document.getElementById('phoneRows');
                var idx = {{ count($phones) }};
                document.getElementById('addPhone').addEventListener('click', function () {
                    var row = document.createElement('div');
                    row.className = 'input-group phone-row';
                    row.innerHTML =
                        '<span class="input-group-text"><input class="form-check-input mt-0" type="radio" name="primary_phone" value="' + idx + '" title="{{ __('Primary') }}"></span>' +
                        '<input name="phones[' + idx + '][phone]" class="form-control" placeholder="{{ __('Phone') }}">' +
                        '<input type="hidden" name="phones[' + idx + '][show_in_header]" value="0">' +
                        '<span class="input-group-text"><input class="form-check-input mt-0 me-1" type="checkbox" name="phones[' + idx + '][show_in_header]" value="1"><label class="form-check-label small">{{ __('Header') }}</label></span>' +
                        '<button type="button" class="btn btn-outline-danger rm-phone"><i class="bi bi-trash"></i></button>';
                    wrap.appendChild(row); idx++;
                });
                wrap.addEventListener('click', function (e) {
                    var btn = e.target.closest('.rm-phone'); if (btn) btn.closest('.phone-row').remove();
                });
            })();
        </script>
    @endpush
@endsection
