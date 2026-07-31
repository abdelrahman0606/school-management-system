<?php

namespace App\Support\Concerns;

use App\Modules\Language\Models\ContentTranslation;

/**
 * Adds per-field, per-locale translations to a scalar-column model (School,
 * SiteSetting — see docs/modules/30-multilingual-content-plan.md). Backed by
 * ContentTranslation, keyed by (static::class, $this->getKey()).
 *
 * Usage:
 *   $school->trans('name', 'bn');               // translated value, or null
 *   $school->transOr('name', 'bn');              // translated, falls back to $school->name
 *   $school->setTranslation('name', 'bn', '...'); // upsert
 */
trait HasTranslations
{
    /** Translated value for $field in $locale (defaults to the current app locale), or null if not set. */
    public function trans(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return ContentTranslation::linesFor(static::class, $this->getKey(), $locale)[$field] ?? null;
    }

    /** Translated value, falling back to this record's own default-locale column value. */
    public function transOr(string $field, ?string $locale = null): ?string
    {
        return $this->trans($field, $locale) ?? $this->{$field};
    }

    /** Upsert a translation. A null $value clears it back to "untranslated" (falls through to transOr's default) — linesFor() only plucks non-null values. An empty string is stored and treated as a real (blank) translation, not a clear. */
    public function setTranslation(string $field, string $locale, ?string $value): ContentTranslation
    {
        return ContentTranslation::updateOrCreate(
            [
                'translatable_type' => static::class,
                'translatable_id' => $this->getKey(),
                'locale' => $locale,
                'field' => $field,
            ],
            ['school_id' => $this->translationSchoolId(), 'value' => $value],
        );
    }

    /**
     * The owning school_id to stamp onto a ContentTranslation row. Works for
     * both shapes in scope so far: a model with its own school_id column
     * (SiteSetting), and School itself, which IS the school — its own id.
     */
    protected function translationSchoolId(): int
    {
        return (int) ($this->school_id ?? $this->getKey());
    }
}
