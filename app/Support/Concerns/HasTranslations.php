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
        // An unsaved model (e.g. HomeController's `new SiteSetting` "no
        // school" fallback) has no key and therefore can't have any
        // translations -- short-circuit rather than passing a null $id into
        // ContentTranslation::linesFor(), which type-hints int|string.
        $key = $this->getKey();
        if ($key === null) {
            return null;
        }

        $locale ??= app()->getLocale();

        return ContentTranslation::linesFor(static::class, $key, $locale)[$field] ?? null;
    }

    /** Translated value, falling back to this record's own default-locale column value. */
    public function transOr(string $field, ?string $locale = null): ?string
    {
        return $this->trans($field, $locale) ?? $this->{$field};
    }

    /**
     * True only if EVERY given field has a non-null translation for $locale —
     * i.e. this record is fully (not just partially) translated into that
     * language. Backs the tick/cross translation-status columns on the admin
     * Staff/Designation/Department/Announcement list screens.
     *
     * @param  list<string>  $fields
     */
    public function isTranslated(array $fields, ?string $locale = null): bool
    {
        foreach ($fields as $field) {
            if ($this->trans($field, $locale) === null) {
                return false;
            }
        }

        return true;
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
