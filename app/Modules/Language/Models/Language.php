<?php

namespace App\Modules\Language\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $fillable = [
        'code', 'name', 'native_name', 'flag',
        'is_rtl', 'is_active', 'is_default', 'sort_order',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @param Builder<Language> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Active languages, cached — read on every request by the switcher/middleware. */
    public static function activeCached(): Collection
    {
        return Cache::remember('languages:active', 3600, fn () => static::active()->get());
    }

    /** The default language code (falls back to app config). */
    public static function defaultCode(): string
    {
        return Cache::remember(
            'languages:default',
            3600,
            fn () => static::where('is_default', true)->value('code') ?? config('app.locale', 'en'),
        );
    }

    public static function flushCache(): void
    {
        Cache::forget('languages:active');
        Cache::forget('languages:default');
    }

    /**
     * Resolve a requested locale code against the active language list,
     * falling back to defaultCode() when missing/blank/not an active
     * language. Single source of truth for this resolution — mirrors
     * SetLocale's own logic exactly, and is what any admin editor with a
     * per-locale "which language am I editing" concept (Website Pages,
     * Menus, ...) should use rather than re-deriving it locally, so an
     * invalid/stale ?locale= or hidden field (e.g. a language deactivated
     * mid-edit) can never 500 or silently write into a locale that
     * doesn't exist.
     */
    public static function resolve(?string $code): string
    {
        $default = static::defaultCode();
        if (! $code) {
            return $default;
        }

        return static::activeCached()->contains(fn ($l) => $l->code === $code) ? $code : $default;
    }
}
