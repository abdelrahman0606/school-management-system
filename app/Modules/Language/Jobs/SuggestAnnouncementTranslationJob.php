<?php

namespace App\Modules\Language\Jobs;

use App\Modules\Announcement\Models\Announcement;
use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Language\Models\Language;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * "Suggest translation" for one Announcement's title + body — same contract
 * as SuggestSchoolTranslationJob (docs/modules/30-multilingual-content-plan.md
 * Phase 5): fills ONLY the currently-empty translation fields for one
 * locale, never overwrites a field the admin (or a previous run) already
 * translated. Unlike School (a singleton per school), an Announcement is one
 * row among many, so this takes a specific announcement id rather than just
 * a school id.
 */
class SuggestAnnouncementTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort draft suggestion, not a critical write — no retry storm against a free, rate-limited API. */
    public int $tries = 1;

    private const FIELDS = ['title', 'body'];

    public function __construct(private readonly int $announcementId, private readonly string $locale) {}

    public function handle(TranslationGatewayContract $gateway): void
    {
        $announcement = Announcement::find($this->announcementId);
        if (! $announcement) {
            return;
        }

        $source = Language::defaultCode();

        foreach (self::FIELDS as $field) {
            if ($announcement->trans($field, $this->locale) !== null) {
                continue; // already translated -- never overwrite
            }

            $value = $announcement->{$field};
            if (! is_string($value) || trim($value) === '') {
                continue; // nothing to translate
            }

            try {
                $translated = $gateway->translate($value, $source, $this->locale);
            } catch (Throwable) {
                continue;
            }

            if ($translated !== '') {
                $announcement->setTranslation($field, $this->locale, $translated);
            }
        }
    }
}
