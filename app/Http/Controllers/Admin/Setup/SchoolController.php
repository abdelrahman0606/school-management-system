<?php

namespace App\Http\Controllers\Admin\Setup;

use App\Modules\Language\Jobs\SuggestSchoolTranslationJob;
use App\Modules\Language\Models\Language;
use App\Modules\Language\Services\TranslationService;
use App\Modules\School\Models\ModuleSetting;
use App\Modules\School\Models\School;
use App\Modules\School\Models\SchoolOpeningHour;
use App\Modules\School\Services\ModuleSettingService;
use App\Modules\School\Services\SchoolService;
use App\Modules\Website\Models\SiteSetting;
use App\Modules\Website\Services\SiteSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SchoolController extends Controller
{
    /** School/SiteSetting fields translatable per docs/modules/30-multilingual-content-plan.md Phase 4. */
    private const SCHOOL_TRANSLATABLE_FIELDS = [
        'name', 'institution_code_label', 'institution_code',
        'school_code_label', 'school_code',
        'technical_branch_code_label', 'technical_branch_code', 'address',
    ];

    private const SETTING_TRANSLATABLE_FIELDS = ['meta_title', 'meta_description'];

    public function __construct(
        private readonly SchoolService $schools,
        private readonly SiteSettingService $siteSettings,
        private readonly ModuleSettingService $modules,
        private readonly TranslationService $translations,
    ) {}

    public function edit(): View
    {
        $schoolId = app('current_school_id');
        $school = School::with(['phones', 'openingHours'])->findOrFail($schoolId);

        return view('admin.setup.school.edit', [
            'school' => $school,
            'settings' => SiteSetting::forSchool($schoolId),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'countries' => config('geo.countries'),
            'currencies' => config('geo.currencies'),
            'languages' => config('geo.languages'),
            'moduleSettings' => $this->modules->allForSchool($schoolId),
            'moduleMeta' => ModuleSetting::META,
            'patterns' => [
                'jan_dec' => 'January – December',
                'apr_mar' => 'April – March',
                'jul_jun' => 'July – June',
                'sep_aug' => 'September – August',
            ],
            // Content languages (docs/modules/30-multilingual-content-plan.md
            // Phase 4) -- deliberately a different variable from 'languages'
            // above, which is config('geo.languages') feeding the school's
            // OWN default-locale dropdown, not the multilingual-content
            // language list. The default language's own value already lives
            // in the plain columns edited above, so it's excluded here.
            'contentLanguages' => Language::activeCached()->reject(fn (Language $l) => $l->code === Language::defaultCode())->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = School::findOrFail(app('current_school_id'));

        // Country/currency codes are stored uppercase — normalise before validating
        // against the geo lists (dropdowns already send uppercase; this covers any
        // manual/lowercase input too).
        $request->merge([
            'currency' => strtoupper((string) $request->input('currency')),
            'country_code' => $request->filled('country_code') ? strtoupper((string) $request->input('country_code')) : null,
        ]);

        $validated = $request->validate([
            // Profile
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            // School codes — three configurable label/value pairs
            'institution_code_label' => ['nullable', 'string', 'max:50'],
            'institution_code' => ['nullable', 'string', 'max:50'],
            'school_code_label' => ['nullable', 'string', 'max:50'],
            'school_code' => ['nullable', 'string', 'max:50'],
            'technical_branch_code_label' => ['nullable', 'string', 'max:50'],
            'technical_branch_code' => ['nullable', 'string', 'max:50'],
            'established' => ['nullable', 'integer', 'min:1800', 'max:'.date('Y')],
            'address' => ['nullable', 'string', 'max:2000'],
            'country_code' => ['nullable', 'string', 'size:2', 'in:'.implode(',', array_keys(config('geo.countries')))],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', array_keys(config('geo.currencies')))],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('geo.languages')))],
            'academic_year_pattern' => ['required', 'string', 'in:jan_dec,apr_mar,jul_jun,sep_aug'],
            // Appearance / branding (merged in from the old Appearance page)
            'primary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'heading_color' => ['nullable', 'string', 'max:20'],
            'topbar_text_color' => ['nullable', 'string', 'max:20'],
            'ticker_position' => ['nullable', 'in:above_nav,below_nav,hidden'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            // Advanced theme — colors (hex, same shape as the fields above)
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'surface_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'link_color' => ['nullable', 'string', 'max:20'],
            'link_hover_color' => ['nullable', 'string', 'max:20'],
            'border_color' => ['nullable', 'string', 'max:20'],
            // Advanced theme — typography. Fonts are a fixed allow-list, not
            // free text — see SiteSetting::FONTS's docblock for why.
            'font_heading' => ['nullable', 'string', 'in:'.implode(',', SiteSetting::FONTS)],
            'font_body' => ['nullable', 'string', 'in:'.implode(',', SiteSetting::FONTS)],
            'base_font_size' => ['nullable', 'integer', 'min:12', 'max:24'],
            'container_width' => ['nullable', 'integer', 'min:960', 'max:1600'],
            // Advanced theme — buttons. btn_filled_json/btn_outline_json are
            // assembled server-side from these flat sub-fields (below) rather
            // than posted as raw JSON, so there's no client-side JSON encoding
            // and no arbitrary-key JSON to validate.
            'btn_radius' => ['nullable', 'integer', 'min:0', 'max:50'],
            'btn_font_weight' => ['nullable', 'string', 'in:'.implode(',', SiteSetting::BTN_FONT_WEIGHTS)],
            'btn_transition_ms' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'btn_filled_bg' => ['nullable', 'string', 'max:20'],
            'btn_filled_text' => ['nullable', 'string', 'max:20'],
            'btn_outline_border' => ['nullable', 'string', 'max:20'],
            'btn_outline_text' => ['nullable', 'string', 'max:20'],
            // Advanced theme — global background
            'global_bg_type' => ['nullable', 'in:'.implode(',', SiteSetting::GLOBAL_BG_TYPES)],
            'global_bg_color' => ['nullable', 'string', 'max:20'],
            'global_bg_overlay' => ['nullable', 'numeric', 'min:0', 'max:1'],
            // Images (uploads)
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'og_image' => ['nullable', 'image', 'max:2048'],
            'global_bg_image' => ['nullable', 'image', 'max:4096'],
            // Translations (docs/modules/30-multilingual-content-plan.md
            // Phase 4) -- translations[{locale}][{field}]. Locale keys are
            // re-validated against the live active-language list below
            // (never trusted from the request directly), so a generic
            // max-length here is enough; the per-field 255/2000 limits above
            // are for the default-locale columns, not every locale's copy.
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.*' => ['nullable', 'string', 'max:2000'],
        ]);

        // ── School profile ──────────────────────────────────────────────────
        $schoolData = collect($validated)->only([
            'name', 'email',
            'institution_code', 'institution_code_label',
            'school_code', 'school_code_label',
            'technical_branch_code', 'technical_branch_code_label',
            'address', 'country_code', 'currency', 'timezone', 'locale', 'academic_year_pattern',
        ])->all();
        // "established" is entered as a plain year; the column stores a date.
        $schoolData['established'] = filled($validated['established'] ?? null) ? $validated['established'].'-01-01' : null;
        if ($path = $this->storeImage($request, 'logo')) {
            $schoolData['logo'] = $path;
        }
        $this->schools->updateSettings($school, $schoolData);

        // ── Appearance / SEO (SiteSetting) ──────────────────────────────────
        $settingData = collect($validated)->only([
            'primary_color', 'accent_color', 'heading_color',
            'topbar_text_color', 'ticker_position', 'meta_title', 'meta_description',
            'secondary_color', 'background_color', 'surface_color', 'text_color',
            'link_color', 'link_hover_color', 'border_color',
            'font_heading', 'font_body', 'base_font_size', 'container_width',
            'btn_radius', 'btn_font_weight', 'btn_transition_ms',
            'global_bg_type', 'global_bg_color', 'global_bg_overlay',
        ])->all();
        // btn_filled_json/btn_outline_json are stored as a small fixed-key
        // JSON object each ({bg,text} / {border,text}) assembled from their
        // own flat form fields — array_filter() drops empty sub-fields so an
        // admin who only sets one of the two colors doesn't have the other
        // silently stored as an empty string; an entirely-empty result
        // becomes null (falls back to the CSS defaults in layout.blade.php)
        // rather than an empty-but-truthy [] the render side would have to
        // special-case.
        $filled = array_filter([
            'bg' => $validated['btn_filled_bg'] ?? null,
            'text' => $validated['btn_filled_text'] ?? null,
        ]);
        $settingData['btn_filled_json'] = $filled ?: null;
        $outline = array_filter([
            'border' => $validated['btn_outline_border'] ?? null,
            'text' => $validated['btn_outline_text'] ?? null,
        ]);
        $settingData['btn_outline_json'] = $outline ?: null;
        if ($path = $this->storeImage($request, 'favicon')) {
            $settingData['favicon'] = $path;
        }
        if ($path = $this->storeImage($request, 'og_image')) {
            $settingData['og_image'] = $path;
        }
        if ($path = $this->storeImage($request, 'global_bg_image')) {
            $settingData['global_bg_image'] = $path;
        }
        $settings = $this->siteSettings->update($school->id, $settingData);

        // ── Translations (docs/modules/30-multilingual-content-plan.md Phase 4) ──
        // Only iterate over locales the DB actually knows are active -- an
        // arbitrary/stale locale key in the submitted payload (deactivated
        // language, tampered form) is simply ignored rather than stored.
        $activeLocales = Language::activeCached()
            ->pluck('code')
            ->reject(fn ($code) => $code === Language::defaultCode());

        $submitted = (array) ($validated['translations'] ?? []);
        $schoolTranslations = [];
        $settingTranslations = [];
        foreach ($activeLocales as $locale) {
            $fields = is_array($submitted[$locale] ?? null) ? $submitted[$locale] : [];
            $schoolTranslations[$locale] = array_intersect_key($fields, array_flip(self::SCHOOL_TRANSLATABLE_FIELDS));
            $settingTranslations[$locale] = array_intersect_key($fields, array_flip(self::SETTING_TRANSLATABLE_FIELDS));
        }
        $this->translations->saveMany($school, $schoolTranslations);
        $this->translations->saveMany($settings, $settingTranslations);

        // ── Phones (dynamic list; each can be flagged to show in the header) ─
        $phones = collect($request->input('phones', []))
            ->filter(fn ($p) => filled($p['phone'] ?? null))
            ->map(fn ($p, $i) => [
                'phone' => $p['phone'],
                'is_primary' => (int) $request->input('primary_phone', 0) === (int) $i,
                'show_in_header' => (bool) ($p['show_in_header'] ?? false),
            ])->values()->all();

        $this->schools->syncPhones($school->id, $phones);

        return back()->with('status', __('School Settings Saved.'));
    }

    /** Store an uploaded image on the public disk; returns the path or null if none uploaded. */
    private function storeImage(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $request->file($field)->store('site', 'public');
    }

    /**
     * Weekly opening hours — drives Attendance working-days (is_open per day).
     */
    public function updateHours(Request $request): RedirectResponse
    {
        $schoolId = app('current_school_id');

        $request->validate([
            'days' => ['required', 'array'],
            'days.*.open_time' => ['nullable', 'date_format:H:i'],
            'days.*.close_time' => ['nullable', 'date_format:H:i', 'after:days.*.open_time'],
        ]);

        foreach ((array) $request->input('days', []) as $dow => $row) {
            SchoolOpeningHour::updateOrCreate(
                ['school_id' => $schoolId, 'day_of_week' => (int) $dow],
                [
                    'is_open' => (bool) ($row['is_open'] ?? false),
                    'open_time' => $row['open_time'] ?? null,
                    'close_time' => $row['close_time'] ?? null,
                ],
            ); // SchoolOpeningHourObserver flushes the school cache
        }

        return back()->with('status', __('Opening Hours Saved.'));
    }

    /**
     * "Suggest translations (AI)" — docs/modules/30-multilingual-content-plan.md
     * Phase 5. Dispatches SuggestSchoolTranslationJob, which only fills
     * currently-empty translation fields; it never overwrites anything the
     * admin already translated by hand.
     */
    public function suggestTranslation(Request $request): RedirectResponse
    {
        $schoolId = app('current_school_id');
        $locale = Language::resolve($request->input('locale'));

        if ($locale === Language::defaultCode()) {
            return back()->with('status', __('Nothing to translate — that is the default language.'));
        }

        // dispatchSync() — see PageController::suggestTranslation()'s own
        // comment: queued dispatch() returns before Horizon actually runs
        // the job under this app's normal QUEUE_CONNECTION=redis, so the
        // redirect used to land back on this page before anything was
        // actually filled in yet. Inline keeps it just as best-effort/
        // tries=1 as before.
        SuggestSchoolTranslationJob::dispatchSync($schoolId, $locale);

        return back()->with('status', __('Translation suggestions filled in below — review before saving.'));
    }
}
