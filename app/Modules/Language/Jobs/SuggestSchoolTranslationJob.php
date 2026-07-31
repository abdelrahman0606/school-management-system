<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Language;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translation" for School settings (docs/modules/30-multilingual-
 * content-plan.md Phase 5) — fills ONLY the currently-empty translation
 * fields for one locale, via TranslationGatewayContract (MyMemory). Never
 * overwrites a field the admin (or a previous run of this job) already
 * translated — matches HasTranslations::transOr()'s own "empty means
 * untranslated" contract from Phase 4, so re-running this after a school
 * hand-edits some fields only fills in the gaps.
 *
 * Same "gateway throws, job catches everything" split LMS's
 * AssignmentAiCheckJob already established — under this app's default
 * QUEUE_CONNECTION=sync, an uncaught exception here would surface as a 500
 * on whatever admin request dispatched it. Each field is additionally
 * wrapped individually so one field's failure (a MyMemory rate limit hit
 * partway through) doesn't abort the rest.
 */
class SuggestSchoolTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    private const SCHOOL_FIELDS = [
        'name', 'institution_code_label', 'institution_code',
        'school_code_label', 'school_code',
        'technical_branch_code_label', 'technical_branch_code', 'address',
    ];

    private const SETTING_FIELDS = ['meta_title', 'meta_description'];

    public function __construct(private readonly int $schoolId, private readonly string $locale) {}

    public function handle(TranslationGatewayContract $gateway): void
    {
        $school = School::find($this->schoolId);
        if (! $school) {
            return;
        }

        $source = Language::defaultCode();

        $this->fillEmpty($school, self::SCHOOL_FIELDS, $source, $gateway);
        $this->fillEmpty(SiteSetting::forSchool($this->schoolId), self::SETTING_FIELDS, $source, $gateway);
    }

    /** @param  array<int, string>  $fields */
    private function fillEmpty(School|SiteSetting $record, array $fields, string $source, TranslationGatewayContract $gateway): void
    {
        foreach ($fields as $field) {
            if ($record->trans($field, $this->locale) !== null) {
                continue; // already translated -- never overwrite
            }

            $value = $record->{$field};
            if (! is_string($value) || trim($value) === '') {
                continue; // nothing to translate
            }

            try {
                $translated = $gateway->translate($value, $source, $this->locale);
            } catch (Throwable $e) {
                continue;
            }

            if ($translated !== '') {
                $record->setTranslation($field, $this->locale, $translated);
            }
        }
    }
}
