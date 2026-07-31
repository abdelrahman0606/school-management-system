<?php

namespace App\Http\Controllers\Admin\Setup;

use App\Modules\Language\Jobs\SuggestUiTranslationsJob;
use App\Modules\Language\Models\Language;
use App\Modules\Language\Models\Translation;
use App\Modules\Language\Services\TranslationScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Language management (Botble-style): enable languages, pick the default, and
 * edit every UI string per language. English is the source (English-as-key),
 * so only non-English locales carry editable rows.
 */
class LanguageController extends Controller
{
    public function index(): View
    {
        return view('admin.setup.languages.index', [
            'languages' => Language::orderBy('sort_order')->get(),
            'counts' => Translation::selectRaw('locale, count(*) as total, sum(case when value is not null then 1 else 0 end) as done')
                ->groupBy('locale')->get()->keyBy('locale'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['required', 'string', 'max:100'],
            'flag' => ['nullable', 'string', 'max:10'],
            'is_rtl' => ['nullable', 'boolean'],
        ]);

        Language::create($data + ['is_active' => true, 'sort_order' => Language::max('sort_order') + 1]);
        Language::flushCache();

        return back()->with('status', __('Language Added.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $language = Language::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'native_name' => ['sometimes', 'string', 'max:100'],
            'flag' => ['nullable', 'string', 'max:10'],
            'is_rtl' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // The default language can never be deactivated.
        if ($language->is_default) {
            $data['is_active'] = true;
        }
        $language->update($data);
        Language::flushCache();

        return back()->with('status', __('Language Updated.'));
    }

    public function setDefault(int $id): RedirectResponse
    {
        $language = Language::findOrFail($id);
        Language::query()->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);
        Language::flushCache();

        return back()->with('status', __('Default Language Changed.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $language = Language::findOrFail($id);
        if ($language->is_default || $language->code === 'en') {
            return back()->with('error', __('The Default And English Languages Cannot Be Removed.'));
        }
        Translation::where('locale', $language->code)->delete();
        Translation::flushCache($language->code);
        $language->delete();
        Language::flushCache();

        return back()->with('status', __('Language Removed.'));
    }

    // ── Translations editor ──────────────────────────────────────────────────

    public function translations(Request $request, string $code): View
    {
        $language = Language::where('code', $code)->where('code', '!=', 'en')->firstOrFail();

        $query = Translation::where('locale', $code)->orderBy('key');
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('key', 'like', "%{$search}%")->orWhere('value', 'like', "%{$search}%"));
        }
        if ($request->boolean('missing')) {
            $query->whereNull('value');
        }

        return view('admin.setup.languages.translations', [
            'language' => $language,
            'rows' => $query->paginate(50)->withQueryString(),
            'search' => $search,
            'missingOnly' => $request->boolean('missing'),
        ]);
    }

    public function saveTranslations(Request $request, string $code): RedirectResponse
    {
        Language::where('code', $code)->firstOrFail();
        $values = $request->input('t', []); // [translation_id => value]

        foreach ($values as $id => $value) {
            $row = Translation::where('locale', $code)->find((int) $id);
            if ($row) {
                $row->update(['value' => filled($value) ? $value : null]);
            }
        }
        Translation::flushCache($code);

        return back()->with('status', __('Translations Saved.'));
    }

    /**
     * "Suggest translations (AI)" for every row currently on screen — exactly
     * the ids the Translations editor's own "Save Translations" button
     * already submits as t[id]=value (same page/search/missing-only filter
     * the admin is looking at; the button is a second submit on that same
     * form, via formaction). Fills only whatever is still empty after that;
     * never overwrites an existing translation.
     *
     * Persists any manually-typed values from this same submission FIRST
     * (identical to saveTranslations()'s own loop) before running AI-suggest
     * — otherwise an in-progress edit the admin had typed but not yet saved
     * would be silently discarded by the back() redirect re-fetching from
     * the DB. This makes "Suggest" a strict superset of "Save": it saves
     * whatever you typed, then drafts AI suggestions for whatever's left.
     */
    public function suggestTranslations(Request $request, string $code): RedirectResponse
    {
        Language::where('code', $code)->where('code', '!=', 'en')->firstOrFail();

        $values = (array) $request->input('t', []);
        $ids = array_map('intval', array_keys($values));
        if ($ids === []) {
            return back()->with('status', __('No Strings On This Page To Translate.'));
        }

        foreach ($values as $id => $value) {
            if (filled($value)) {
                Translation::where('locale', $code)->where('id', (int) $id)->update(['value' => $value]);
            }
        }
        // The mass-update above goes through the query builder, not a loaded
        // model's save() -- Translation::booted()'s saved-event cache flush
        // never fires for it, so it needs an explicit flush here (same as
        // saveTranslations()'s own explicit call after its per-row loop).
        Translation::flushCache($code);

        // dispatchSync() -- see SchoolController::suggestTranslation()'s own
        // comment: queued dispatch() returns before Horizon actually runs
        // the job under this app's normal QUEUE_CONNECTION=redis. Re-queries
        // fresh from the DB, so it correctly sees (and skips) the rows just
        // saved above.
        SuggestUiTranslationsJob::dispatchSync($code, $ids);

        return back()->with('status', __('Translation suggestions filled in below — review before saving.'));
    }

    /** Re-scan the codebase for __() strings and register missing keys. */
    public function scan(TranslationScanner $scanner): RedirectResponse
    {
        $added = $scanner->sync();

        return back()->with('status', __('Scan Complete — :count New Strings Registered.', ['count' => $added]));
    }
}
