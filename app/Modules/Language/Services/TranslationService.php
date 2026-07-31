<?php

namespace App\Modules\Language\Services;

use App\Modules\School\Models\School;
use App\Modules\Website\Models\SiteSetting;

/**
 * Bulk-saves per-locale translations from a single admin form submit that
 * covers every active (non-default) language at once for one HasTranslations
 * host model. docs/modules/30-multilingual-content-plan.md Phase 4.
 *
 * Union-typed to School|SiteSetting rather than a generic Model — those are
 * the only two HasTranslations hosts in scope so far (same models the trait
 * itself is documented against); widen this the day a third one needs it.
 */
class TranslationService
{
    /**
     * @param  array<string, array<string, mixed>>  $translationsByLocale  [locale => [field => value]]
     */
    public function saveMany(School|SiteSetting $record, array $translationsByLocale): void
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
