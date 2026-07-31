<?php

namespace App\Http\Controllers\Admin\People;

use App\Modules\Language\Jobs\SuggestStaffReferenceTranslationJob;
use App\Modules\Language\Models\Language;
use App\Modules\Language\Services\TranslationService;
use App\Modules\Staff\Models\Department;
use App\Modules\Staff\Models\Designation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Designations and Departments — simple per-school name lists on the Staff
 * module. Both are `['school_id', 'name']` with a `staff()` relation, so one
 * controller serves both. The {type} comes from the route's ->defaults(), read
 * via $request->route()->parameter() (not a method arg — mixing a defaulted
 * param with a URL {id} binds positionally and swaps the two).
 */
class StaffReferenceController extends Controller
{
    /** @var array<string, array{model: class-string<Model>, table: string, label: string, singular: string}> */
    private const TYPES = [
        'designations' => ['model' => Designation::class, 'table' => 'designations', 'label' => 'Designations', 'singular' => 'Designation'],
        'departments' => ['model' => Department::class,  'table' => 'departments',  'label' => 'Departments',  'singular' => 'Department'],
    ];

    /** Both Designation and Department only ever have 'name' to translate. */
    private const TRANSLATABLE_FIELDS = ['name'];

    public function __construct(private readonly TranslationService $translations) {}

    public function index(Request $request): View
    {
        $meta = $this->meta($request);
        $items = $meta['model']::where('school_id', app('current_school_id'))
            ->withCount('staff')
            ->orderBy('name')
            ->get();

        return view('admin.people.reference.index', [
            'type' => $request->route()->parameter('type'),
            'label' => $meta['label'],
            'singular' => $meta['singular'],
            'items' => $items,
            // docs/modules/30-multilingual-content-plan.md Phase 4/5 — same
            // "active languages minus the default" list School's own editor
            // uses for its translation panels.
            'contentLanguages' => Language::activeCached()->reject(fn (Language $l) => $l->code === Language::defaultCode())->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $meta = $this->meta($request);
        $schoolId = app('current_school_id');
        $meta['model']::create($this->validated($request, $meta['table'], $schoolId, null) + ['school_id' => $schoolId]);

        return back()->with('status', "{$meta['singular']} created.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $meta = $this->meta($request);
        $schoolId = app('current_school_id');
        $item = $meta['model']::where('school_id', $schoolId)->findOrFail($id);
        $data = $this->validated($request, $meta['table'], $schoolId, $id);
        $item->update(Arr::except($data, 'translations'));

        // TYPES only ever maps to Designation|Department (both HasTranslations
        // hosts) — the assert narrows the type for TranslationService::saveMany(),
        // which can't be typed any looser than a named union (see its own docblock).
        assert($item instanceof Designation || $item instanceof Department);
        $this->translations->saveMany($item, $this->translationsPayload($data));

        return back()->with('status', "{$meta['singular']} updated.");
    }

    /**
     * "Suggest translation (AI)" — docs/modules/30-multilingual-content-plan.md
     * Phase 5. Fills only the currently-empty name translation for one
     * locale; never overwrites a field the admin (or a previous run)
     * already translated.
     */
    public function suggestTranslation(Request $request, int $id): RedirectResponse
    {
        $meta = $this->meta($request);
        $schoolId = app('current_school_id');
        $item = $meta['model']::where('school_id', $schoolId)->findOrFail($id);
        $locale = Language::resolve($request->input('locale'));

        if ($locale === Language::defaultCode()) {
            return back()->with('status', __('Nothing to translate — that is the default language.'));
        }

        // dispatchSync() — see SchoolController::suggestTranslation()'s own
        // comment: queued dispatch() returns before Horizon actually runs
        // the job under this app's normal QUEUE_CONNECTION=redis.
        SuggestStaffReferenceTranslationJob::dispatchSync($meta['model'], $item->id, $locale);

        return back()->with('status', __('Translation suggestions filled in below — review before saving.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $meta = $this->meta($request);
        $item = $meta['model']::where('school_id', app('current_school_id'))->withCount('staff')->findOrFail($id);

        if ($item->staff_count > 0) {
            return back()->with('error', "Cannot delete a {$meta['singular']} that still has staff assigned.");
        }

        $item->delete();

        return back()->with('status', "{$meta['singular']} deleted.");
    }

    /**
     * @return array{model: class-string<Model>, table: string, label: string, singular: string}
     */
    private function meta(Request $request): array
    {
        $type = $request->route()->parameter('type');
        abort_unless(is_string($type) && isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $table, int $schoolId, ?int $id): array
    {
        $ignore = $id ?? 'NULL';

        return $request->validate([
            'name' => ['required', 'string', 'max:100', "unique:{$table},name,{$ignore},id,school_id,{$schoolId}"],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.*' => ['nullable', 'string', 'max:200'],
        ]);
    }

    /**
     * Whitelists the submitted translations payload against the currently
     * active, non-default locales and against TRANSLATABLE_FIELDS — never
     * trusts an arbitrary locale/field key straight from the request, same
     * defensive pattern as SchoolController::update().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    private function translationsPayload(array $data): array
    {
        $activeLocales = Language::activeCached()->pluck('code')->reject(fn ($code) => $code === Language::defaultCode());
        $submitted = (array) ($data['translations'] ?? []);
        $out = [];

        foreach ($activeLocales as $locale) {
            $fields = is_array($submitted[$locale] ?? null) ? $submitted[$locale] : [];
            $out[$locale] = array_intersect_key($fields, array_flip(self::TRANSLATABLE_FIELDS));
        }

        return $out;
    }
}
