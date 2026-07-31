<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\Menu;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\SiteSetting;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/modules/30-multilingual-content-plan.md Phase 3 — a school builds one
 * full nav tree PER LANGUAGE: each locale owns its own independent Menu row,
 * the public header renders whichever locale the visitor has chosen, and an
 * untranslated locale falls back to the default language's menu instead of
 * showing an empty/hardcoded nav.
 */
class MultilingualMenuTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LanguageSeeder::class); // en (default) + bn, both active

        $this->school = School::create(['name' => 'Test School', 'is_active' => true]);
        SiteSetting::create(['school_id' => $this->school->id, 'site_name' => 'Test School']);
        $this->admin = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
        $this->admin->assignRole('admin');
    }

    public function test_menu_editor_shows_a_language_switcher_marking_untranslated_locales(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/menus')
            ->assertOk()
            ->assertSee('untranslated');
    }

    public function test_saving_one_locales_menu_does_not_touch_another(): void
    {
        $home = Page::create(['school_id' => $this->school->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published', 'is_homepage' => true]);

        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'en',
            'items' => json_encode([
                ['label' => 'Home', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self'],
            ]),
        ])->assertRedirect();

        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'bn',
            'items' => json_encode([
                ['label' => 'হোম', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self'],
                ['label' => 'যোগাযোগ', 'type' => 'external', 'url' => '/contact', 'target' => '_self'],
            ]),
        ])->assertRedirect();

        $en = Menu::forSchool($this->school->id)->where('locale', 'en')->with('items')->first();
        $bn = Menu::forSchool($this->school->id)->where('locale', 'bn')->with('items')->first();

        $this->assertNotNull($en);
        $this->assertNotNull($bn);
        $this->assertCount(1, $en->items);
        $this->assertSame('Home', $en->items->first()->label);
        $this->assertCount(2, $bn->items);
        $this->assertSame('হোম', $bn->items->first()->label);
    }

    public function test_public_header_renders_the_visitors_chosen_locale(): void
    {
        $home = Page::create(['school_id' => $this->school->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published', 'is_homepage' => true]);

        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'en',
            'items' => json_encode([['label' => 'About Us', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self']]),
        ]);
        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'bn',
            'items' => json_encode([['label' => 'আমাদের সম্পর্কে', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self']]),
        ]);

        // Public routes don't gate on auth state, so staying "logged in" as
        // the admin here is harmless — this checks what the page renders,
        // not who's allowed to see it.
        $this->get('/')->assertOk()->assertSee('About Us')->assertDontSee('আমাদের সম্পর্কে');

        $this->withSession(['app_locale' => 'bn'])->get('/')->assertOk()
            ->assertSee('আমাদের সম্পর্কে')->assertDontSee('About Us');
    }

    public function test_untranslated_locale_falls_back_to_the_default_languages_menu(): void
    {
        $home = Page::create(['school_id' => $this->school->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published', 'is_homepage' => true]);

        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'en',
            'items' => json_encode([['label' => 'Contact Us', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self']]),
        ]);

        // No Bangla menu has ever been built — a Bangla visitor still sees
        // the English nav instead of an empty/hardcoded fallback.
        $this->withSession(['app_locale' => 'bn'])->get('/')->assertOk()->assertSee('Contact Us');
    }

    // ── Copy from default language (plain, non-AI) ─────────────────────────

    public function test_admin_can_copy_the_default_languages_menu_into_an_untranslated_locale(): void
    {
        $home = Page::create(['school_id' => $this->school->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published', 'is_homepage' => true]);

        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'en',
            'items' => json_encode([
                ['label' => 'Home', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self'],
                ['label' => 'About', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                    ['label' => 'History', 'type' => 'external', 'url' => '/history', 'target' => '_blank'],
                ]],
            ]),
        ])->assertRedirect();

        $this->get('/admin/menus?locale=bn')->assertOk()->assertSee('Copy from default language to start translating');

        $this->post('/admin/menus/copy-locale', ['from_locale' => 'en', 'to_locale' => 'bn'])->assertRedirect();

        $bn = Menu::forSchool($this->school->id)->where('locale', 'bn')->with('items.children')->first();
        $this->assertNotNull($bn);
        $this->assertCount(2, $bn->items);
        // Labels are copied AS-IS — this is the plain copy, not the AI one.
        $this->assertSame('Home', $bn->items->first()->label);
        $about = $bn->items->firstWhere('type', 'dropdown');
        $this->assertSame('About', $about->label);
        $this->assertCount(1, $about->children);
        $this->assertSame('History', $about->children->first()->label);
        $this->assertSame('/history', $about->children->first()->url);
        $this->assertSame('_blank', $about->children->first()->target);

        // The English menu is completely untouched.
        $en = Menu::forSchool($this->school->id)->where('locale', 'en')->with('items')->first();
        $this->assertCount(2, $en->items);
    }

    public function test_copy_refuses_to_overwrite_a_locale_that_already_has_menu_items(): void
    {
        $home = Page::create(['school_id' => $this->school->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published', 'is_homepage' => true]);

        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'en',
            'items' => json_encode([['label' => 'Home', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self']]),
        ]);
        $this->actingAs($this->admin)->put('/admin/menus', [
            'locale' => 'bn',
            'items' => json_encode([['label' => 'হোম হাতে', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self']]),
        ]);

        $this->post('/admin/menus/copy-locale', ['from_locale' => 'en', 'to_locale' => 'bn'])->assertRedirect();

        $bn = Menu::forSchool($this->school->id)->where('locale', 'bn')->with('items')->first();
        $this->assertSame('হোম হাতে', $bn->items->first()->label);
        $this->assertCount(1, $bn->items);
    }

    public function test_copy_is_a_no_op_when_the_source_language_has_no_menu_yet(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/menus/copy-locale', ['from_locale' => 'en', 'to_locale' => 'bn'])
            ->assertRedirect();

        $this->assertNull(Menu::forSchool($this->school->id)->where('locale', 'bn')->first());
    }
}
