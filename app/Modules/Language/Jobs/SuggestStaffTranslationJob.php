<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Language;
use App\Modules\Staff\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translation" for one Staff member's name — same contract as
 * SuggestSchoolTranslationJob (docs/modules/30-multilingual-content-plan.md
 * Phase 5): fills the translation for one locale only if it's currently
 * empty, never overwrites a field the admin (or a previous run) already
 * translated.
 */
class SuggestStaffTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    public function __construct(private readonly int $staffId, private readonly string $locale) {}

    public function handle(TranslationGatewayContract $gateway): void
    {
        $staff = Staff::find($this->staffId);
        if (! $staff) {
            return;
        }

        if ($staff->trans('name', $this->locale) !== null) {
            return; // already translated -- never overwrite
        }

        $value = $staff->name;
        if (! is_string($value) || trim($value) === '') {
            return; // nothing to translate
        }

        try {
            $translated = $gateway->translate($value, Language::defaultCode(), $this->locale);
        } catch (Throwable) {
            return;
        }

        if ($translated !== '') {
            $staff->setTranslation('name', $this->locale, $translated);
        }
    }
}
