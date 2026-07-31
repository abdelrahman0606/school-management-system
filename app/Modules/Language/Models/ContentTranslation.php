<?php

namespace App\Modules\Language\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Per-record, per-field translations — see the creating migration and
 * docs/modules/30-multilingual-content-plan.md. Read/written through the
 * HasTranslations trait (App\Support\Concerns\HasTranslations), not directly.
 */
class ContentTranslation extends Model
{
    protected $fillable = ['school_id', 'translatable_type', 'translatable_id', 'locale', 'field', 'value'];

    protected static function booted(): void
    {
        static::saved(fn (ContentTranslation $t) => static::flushCache($t->translatable_type, $t->translatable_id, $t->locale));
        static::deleted(fn (ContentTranslation $t) => static::flushCache($t->translatable_type, $t->translatable_id, $t->locale));
    }

    /**
     * Translated [field => value] map for one record in one locale.
     *
     * Mirrors Translation::linesFor() exactly (plain Cache::remember()/forget(),
     * no Cache::tags() — see App\Support\CacheTags' docblock on why the raw
     * facade's tagging is off-limits, but a bare remember/forget pair is fine
     * on every driver since it never calls ->tags()).
     *
     * @return array<string, string>
     */
    public static function linesFor(string $type, int|string $id, string $locale): array
    {
        return Cache::remember(
            static::cacheKey($type, $id, $locale),
            3600,
            fn () => static::query()
                ->where('translatable_type', $type)
                ->where('translatable_id', $id)
                ->where('locale', $locale)
                ->whereNotNull('value')
                ->pluck('value', 'field')
                ->all(),
        );
    }

    public static function flushCache(string $type, int|string $id, string $locale): void
    {
        Cache::forget(static::cacheKey($type, $id, $locale));
    }

    private static function cacheKey(string $type, int|string $id, string $locale): string
    {
        return "content_translations:lines:{$type}:{$id}:{$locale}";
    }
}
