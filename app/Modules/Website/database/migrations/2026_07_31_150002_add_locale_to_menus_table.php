<?php

use App\Modules\Language\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for docs/modules/30-multilingual-content-plan.md — a school builds
 * one full menu tree per locale (mirrors MenuService::replaceItems()'s existing
 * full-tree-replace shape; menu_items stay keyed off menu_id, no change needed
 * there). Nullable for the same doctrine/dbal reason as the page_layouts sibling
 * migration — backfilled below, enforced by application code from Phase 3 on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('school_id');
            $table->index(['school_id', 'locale']);
        });

        $defaultLocale = Language::defaultCode();
        DB::table('menus')->whereNull('locale')->update(['locale' => $defaultLocale]);
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'locale']);
            $table->dropColumn('locale');
        });
    }
};
