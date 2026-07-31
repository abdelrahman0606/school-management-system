<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Language;
use App\Modules\Staff\Models\Department;
use App\Modules\Staff\Models\Designation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translation" for one Designation or Department's name — same
 * contract as SuggestSchoolTranslationJob (docs/modules/30-multilingual-
 * content-plan.md Phase 5). Designation and Department are both
 * `['school_id', 'name']` with nothing else translatable, so one job serves
 * both the same way StaffReferenceController serves both admin CRUD
 * actions — a model class string instead of two near-identical job classes.
 */
class SuggestStaffReferenceTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    /**
     * @param  class-string<Designation|Department>  $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly int $recordId,
        private readonly string $locale,
    ) {}

    public function handle(TranslationGatewayContract $gateway): void
    {
        if (! in_array($this->modelClass, [Designation::class, Department::class], true)) {
            return;
        }

        $record = $this->modelClass::find($this->recordId);
        if (! $record) {
            return;
        }

        if ($record->trans('name', $this->locale) !== null) {
            return; // already translated -- never overwrite
        }

        $value = $record->name;
        if (! is_string($value) || trim($value) === '') {
            return; // nothing to translate
        }

        try {
            $translated = $gateway->translate($value, Language::defaultCode(), $this->locale);
        } catch (Throwable) {
            return;
        }

        if ($translated !== '') {
            $record->setTranslation('name', $this->locale, $translated);
        }
    }
}
