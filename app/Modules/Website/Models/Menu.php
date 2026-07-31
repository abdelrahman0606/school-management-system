<?php

namespace App\Modules\Website\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    // locale: docs/modules/30-multilingual-content-plan.md Phase 1 — each Menu
    // is one full tree for one locale. Not yet consumed by MenuController/
    // MenuService (Phase 3); every existing lookup still finds the single
    // school-wide menu it always has.
    protected $fillable = ['school_id', 'name', 'locale'];

    /** @return HasMany<MenuItem> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<MenuItem> */
    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /** @param Builder<Menu> $query */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }
}
