<?php

namespace App\Modules\Language\Services;

use App\Modules\Announcement\Models\Announcement;
use App\Modules\School\Models\School;
use App\Modules\Staff\Models\Department;
use App\Modules\Staff\Models\Designation;
use App\Modules\Staff\Models\Staff;
use App\Modules\Website\Models\SiteSetting;

/**
 * Bulk-saves per-locale translations from a single admin form submit that
 * covers every active (non-default) language at once for one HasTranslations
 * host model. docs/modules/30-multilingual-content-plan.md Phase 4.
 *
 * Union-typed rather than a generic Model — HasTranslations doesn't declare
 * an interface of its own (see the trait's own docblock), so there's nothing
 * narrower than "list every current host" to type-hint against. Widen this
 * union the day a new HasTranslations host needs it (originally just
 * School|SiteSetting; Announcement/Staff/Designation/Department added when
 * those modules gained per-field translation).
 */
class TranslationService
{
    /**
     * @param  array<string, array<string, mixed>>  $translationsByLocale  [locale => [field => value]]
     */
    public function saveMany(School|SiteSetting|Announcement|Staff|Designation|Department $record, array $translationsByLocale): void
    {
        foreach ($translationsByLocale as $locale => $fields) {
            foreach ($fields as $field => $value) {
                $value = is_string($value) ? trim($value) : $value;

                // A blank submitted field means "no override for this locale"
                // -- store null so transOr() falls back to the default-locale
                // column (HasTranslations::setTranslation()'s own contract).
                // An admin who wants a genuinely blank translation has no way
                // to express that via this form; that's the right trade-off,
                // since a freshly-added language's untouched panel must never
                // silently blank out the whole public site for that locale.
                $record->setTranslation((string) $field, (string) $locale, $value !== '' ? $value : null);
            }
        }
    }
}
