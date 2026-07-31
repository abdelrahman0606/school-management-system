<?php

namespace Tests\Feature\Language;

use App\Modules\Language\Models\ContentTranslation;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\SiteSetting;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Foundation for docs/modules/30-multilingual-content-plan.md — schema +
 * HasTranslations trait only. No public-facing behavior changes yet (that's
 * Phases 2-4); this proves the storage/read/write/cache-invalidation
 * mechanics work, generically across two different host models.
 */
class ContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LanguageSeeder::class);

        $this->school = School::create(['name' => 'Green Valley Model School', 'is_active' => true]);
    }

    public function test_phase1_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('page_layouts', 'locale'));
        $this->assertTrue(Schema::hasColumn('page_layouts', 'title'));
        $this->assertTrue(Schema::hasColumn('page_layouts', 'meta_title'));
        $this->assertTrue(Schema::hasColumn('page_layouts', 'meta_desc'));
        $this->assertTrue(Schema::hasColumn('page_layouts', 'og_image'));
        $this->assertTrue(Schema::hasColumn('menus', 'locale'));
        $this->assertTrue(Schema::hasTable('content_translations'));
    }

    public function test_untranslated_field_returns_null_and_transor_falls_back_to_the_original_column(): void
    {
        $this->assertNull($this->school->trans('name', 'bn'));
        $this->assertSame('Green Valley Model School', $this->school->transOr('name', 'bn'));
    }

    public function test_set_translation_then_read_it_back(): void
    {
        $this->school->setTranslation('name', 'bn', 'গ্রীন ভ্যালি মডেল স্কুল');

        $this->assertSame('গ্রীন ভ্যালি মডেল স্কুল', $this->school->trans('name', 'bn'));
        $this->assertSame('গ্রীন ভ্যালি মডেল স্কুল', $this->school->transOr('name', 'bn'));
        // English (or any other untranslated locale) is untouched.
        $this->assertNull($this->school->trans('name', 'en'));
    }

    public function test_setting_a_translation_twice_updates_in_place_and_the_cache_reflects_the_new_value(): void
    {
        $this->school->setTranslation('address', 'bn', 'প্রথম মান');
        $this->assertSame('প্রথম মান', $this->school->trans('address', 'bn'));

        $this->school->setTranslation('address', 'bn', 'দ্বিতীয় মান');

        $this->assertSame('দ্বিতীয় মান', $this->school->trans('address', 'bn'));
        $this->assertSame(1, ContentTranslation::query()
            ->where('translatable_type', School::class)
            ->where('translatable_id', $this->school->id)
            ->where('locale', 'bn')
            ->where('field', 'address')
            ->count());
    }

    public function test_trait_works_generically_on_a_second_host_model(): void
    {
        $settings = SiteSetting::forSchool($this->school->id);
        $settings->update(['meta_description' => 'A traditional institution.']);

        $this->assertNull($settings->trans('meta_description', 'bn'));

        $settings->setTranslation('meta_description', 'bn', 'একটি ঐতিহ্যবাহী প্রতিষ্ঠান।');

        $this->assertSame('একটি ঐতিহ্যবাহী প্রতিষ্ঠান।', $settings->transOr('meta_description', 'bn'));
        // Confirms translatable_id namespacing: School #1 and SiteSetting #1
        // (same numeric id) never collide because translatable_type differs.
        $this->assertNull($this->school->trans('meta_description', 'bn'));
    }
}
