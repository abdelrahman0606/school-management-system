<?php

use App\Modules\Language\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for docs/modules/30-multilingual-content-plan.md — each PageLayout
 * revision belongs to one locale (a full duplicate row per language, mirroring
 * the table's existing versioned/append-only shape). Also moves SEO meta onto
 * the revision itself so it can vary per locale too; `pages.title/meta_title/
 * meta_desc/og_image` remain the default-locale seed and the admin list label.
 *
 * `locale` is deliberately left nullable at the DB level (no doctrine/dbal in
 * this project, so no later ->change() to add a NOT NULL constraint) — every
 * row written by application code from Phase 2 onward always sets a real
 * locale explicitly, this column just can't enforce that itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_layouts', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('page_id');
            $table->string('title')->nullable()->after('locale');
            $table->string('meta_title')->nullable()->after('title');
            $table->text('meta_desc')->nullable()->after('meta_title');
            $table->string('og_image')->nullable()->after('meta_desc');

            $table->index(['page_id', 'locale', 'is_published']);
        });

        // Backfill every existing revision to the install's default language so
        // current data keeps resolving under the locale-aware queries Phase 2
        // introduces — this migration alone changes no rendered output.
        $defaultLocale = Language::defaultCode();
        DB::table('page_layouts')->whereNull('locale')->update(['locale' => $defaultLocale]);
    }

    public function down(): void
    {
        Schema::table('page_layouts', function (Blueprint $table): void {
            $table->dropIndex(['page_id', 'locale', 'is_published']);
            $table->dropColumn(['locale', 'title', 'meta_title', 'meta_desc', 'og_image']);
        });
    }
};
