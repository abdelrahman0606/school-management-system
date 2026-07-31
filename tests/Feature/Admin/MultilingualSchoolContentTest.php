<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Language\Models\ContentTranslation;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\SiteSetting;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/modules/30-multilingual-content-plan.md Phase 4 — School identity
 * (name/address/institution codes) and SiteSetting SEO text (meta_title/
 * meta_description) are singleton-per-school rows, so unlike Pages/Menus
 * (Phases 2-3, a full row/tree per locale) they use the generic
 * ContentTranslation/HasTranslations mechanism from Phase 1: one admin form
 * submit saves every active language's overrides for these ~10 fields at
 * once, and the public site (header/footer/home) reads them back via
 * transOr(), falling back to the default-locale column when a locale has no
 * override yet.
 */
class MultilingualSchoolContentTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LanguageSeeder::class); // en (default) + bn, both active

        $this->school = School::create([
            'name' => 'Green Valley Model School',
            'address' => '12 Lake Road, Dhaka',
            'institution_code_label' => 'EIIN',
            'institution_code' => '123456',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'locale' => 'en',
            'academic_year_pattern' => 'jan_dec',
            'is_active' => true,
        ]);
        SiteSetting::create([
            'school_id' => $this->school->id,
            'meta_description' => 'A traditional institution nurturing curious minds.',
        ]);
        $this->admin = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
        $this->admin->assignRole('admin');
    }

    private function baseFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->school->name,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'locale' => 'en',
            'academic_year_pattern' => 'jan_dec',
        ], $overrides);
    }

    public function test_school_settings_screen_shows_a_translations_panel_per_active_language(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/school')
            ->assertOk()
            ->assertSee('Translations')
            ->assertSee('বাংলা'); // bn's native_name, not en (the default is excluded)
    }

    public function test_admin_can_save_school_and_site_setting_translations_for_a_locale(): void
    {
        $this->actingAs($this->admin)->put('/admin/school', $this->baseFormData([
            'translations' => [
                'bn' => [
                    'name' => 'গ্রীন ভ্যালি মডেল স্কুল',
                    'address' => 'লেক রোড ১২, ঢাকা',
                    'meta_description' => 'একটি ঐতিহ্যবাহী প্রতিষ্ঠান।',
                ],
            ],
        ]))->assertRedirect();

        $this->school->refresh();
        $settings = SiteSetting::forSchool($this->school->id);

        $this->assertSame('গ্রীন ভ্যালি মডেল স্কুল', $this->school->trans('name', 'bn'));
        $this->assertSame('লেক রোড ১২, ঢাকা', $this->school->trans('address', 'bn'));
        $this->assertSame('একটি ঐতিহ্যবাহী প্রতিষ্ঠান।', $settings->trans('meta_description', 'bn'));
        // The default-locale columns are untouched by the translations block.
        $this->assertSame('Green Valley Model School', $this->school->name);
    }

    public function test_an_unknown_or_deactivated_locale_key_in_the_payload_is_silently_ignored(): void
    {
        $this->actingAs($this->admin)->put('/admin/school', $this->baseFormData([
            'translations' => [
                'fr' => ['name' => 'École Verte'], // 'fr' was never seeded/activated
            ],
        ]))->assertRedirect();

        $this->assertSame(0, ContentTranslation::query()->where('locale', 'fr')->count());
    }

    public function test_public_site_renders_the_visitors_locale_translation_in_header_and_footer(): void
    {
        $this->school->setTranslation('name', 'bn', 'গ্রীন ভ্যালি মডেল স্কুল');
        $this->school->setTranslation('address', 'bn', 'লেক রোড ১২, ঢাকা');

        $this->get('/')->assertOk()
            ->assertSee('Green Valley Model School')->assertDontSee('গ্রীন ভ্যালি মডেল স্কুল');

        $this->withSession(['app_locale' => 'bn'])->get('/')->assertOk()
            ->assertSee('গ্রীন ভ্যালি মডেল স্কুল')->assertSee('লেক রোড ১২, ঢাকা')
            ->assertDontSee('Green Valley Model School');
    }

    public function test_untranslated_locale_falls_back_to_the_default_languages_content(): void
    {
        // No Bangla translation has ever been set for this school.
        $this->withSession(['app_locale' => 'bn'])->get('/')->assertOk()
            ->assertSee('Green Valley Model School')
            ->assertSee('12 Lake Road, Dhaka');
    }

    public function test_saving_a_blank_translation_field_clears_it_back_to_the_fallback(): void
    {
        $this->school->setTranslation('name', 'bn', 'গ্রীন ভ্যালি মডেল স্কুল');
        $this->assertSame('গ্রীন ভ্যালি মডেল স্কুল', $this->school->trans('name', 'bn'));

        $this->actingAs($this->admin)->put('/admin/school', $this->baseFormData([
            'translations' => ['bn' => ['name' => '']],
        ]))->assertRedirect();

        $this->assertNull($this->school->trans('name', 'bn'));
        $this->assertSame('Green Valley Model School', $this->school->transOr('name', 'bn'));
    }
}
