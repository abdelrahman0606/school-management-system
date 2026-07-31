<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Translation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translations (AI)" for the flat UI-string catalog (Settings ->
 * Languages -> Translations editor) — same TranslationGatewayContract
 * (MyMemory) draft-suggestion contract as SuggestSchoolTranslationJob etc.
 * (docs/modules/30-multilingual-content-plan.md Phase 5), but bulk over a
 * list of Translation row ids instead of one model's fixed field list: this
 * catalog has no field list at all, every ROW is its own translatable unit,
 * keyed by the row's own `key` column (the literal English source string —
 * always 'en', never Language::defaultCode(), since this whole catalog is
 * English-as-key by construction; see Translation's own class docblock).
 *
 * Deliberately bulk-by-id rather than "translate everything missing for this
 * locale": LanguageController::suggestTranslations() passes exactly the row
 * ids on the admin's current paginated page (<=50, same set the Translations
 * editor's "Save Translations" button already submits) — never the whole
 * ~2,200-row catalog in one dispatchSync() call, which would be a long
 * enough sequence of network calls to risk a real HTTP timeout on shared
 * hosting. An admin working through several pages just clicks the button on
 * each one.
 */
class SuggestUiTranslationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    /** @param  list<int>  $ids */
    public function __construct(private readonly string $locale, private readonly array $ids) {}

    public function handle(TranslationGatewayContract $gateway): void
    {
        $rows = Translation::where('locale', $this->locale)->whereIn('id', $this->ids)->get();

        foreach ($rows as $row) {
            if ($row->value !== null) {
                continue; // already translated (by hand or a previous run) -- never overwrite
            }

            try {
                $translated = $gateway->translate($row->key, 'en', $this->locale);
            } catch (Throwable) {
                continue; // one row's failure (e.g. a MyMemory rate limit) must not abort the rest
            }

            if ($translated !== '') {
                $row->update(['value' => $translated]); // Translation::booted() flushes the locale's cache on save
            }
        }
    }
}
