<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for docs/modules/30-multilingual-content-plan.md — per-record,
 * per-field translations for scalar columns on singleton-per-school rows
 * (School identity fields, SiteSetting text). Deliberately NOT a duplicate-row-
 * per-locale table like page_layouts/menus: a School has exactly one currency/
 * timezone/country_code, only a handful of its columns are language-dependent,
 * so a polymorphic (record, field, locale) -> value table fits without forcing
 * every unrelated column to exist N times.
 *
 * Same (locale, key) -> value shape as the existing `translations` table
 * (Language module's static UI-string dictionary), just keyed by the owning
 * record instead of by the English source string — two schools both have a
 * "name" field with different values, so the English string can't double as
 * the dictionary key here the way it does for translations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('translatable_type', 191);
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 10);
            $table->string('field', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'content_translations_record_locale_field_unique',
            );
            $table->index(['translatable_type', 'translatable_id'], 'content_translations_record_idx');
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
