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

    /**
     * Reported: saving a menu that mixes a dropdown (with children) among
     * top-level items left some items behind after a save that should have
     * fully replaced them — a second save on top piled a full duplicate
     * tree on top of a partial leftover of the first, rather than cleanly
     * replacing it. Root cause: MenuService::replaceItems() used to delete
     * via $menu->allItems()->delete(), which carries orderBy('sort_order')
     * — and a child's own sort_order is independently 0-based per parent,
     * so it routinely ties with unrelated top-level items' sort_order. That
     * ordering could cause MySQL to delete a dropdown parent (cascading its
     * children away) before the same DELETE statement's own scan reached
     * those children, silently skipping rows the cascade never raced.
     * Repeated saves (mirroring "Build from default language (AI)"
     * immediately followed by "Save Menu") must always leave EXACTLY the
     * submitted tree — never more, never fewer.
     */
    public function test_repeated_saves_of_a_tree_with_a_dropdown_never_leave_leftover_or_duplicate_items(): void
    {
        $home = Page::create(['school_id' => $this->school->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published', 'is_homepage' => true]);

        $tree = [
            ['label' => 'Home', 'type' => 'page', 'page_id' => $home->id, 'target' => '_self'],
            ['label' => 'About', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                ['label' => 'History', 'type' => 'external', 'url' => '/history', 'target' => '_self'],
                ['label' => 'Mission', 'type' => 'external', 'url' => '/mission', 'target' => '_self'],
            ]],
            ['label' => 'Faculty', 'type' => 'external', 'url' => '/faculty', 'target' => '_self'],
            ['label' => 'Teachers', 'type' => 'external', 'url' => '/teachers', 'target' => '_self'],
            ['label' => 'Gallery', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                ['label' => 'Photos', 'type' => 'external', 'url' => '/photos', 'target' => '_self'],
                ['label' => 'Videos', 'type' => 'external', 'url' => '/videos', 'target' => '_self'],
            ]],
            ['label' => 'Contact', 'type' => 'external', 'url' => '/contact', 'target' => '_self'],
        ];

        // Save it twice in a row — same shape as "AI-suggest built the tree,
        // then Save Menu was clicked" (or simply saving twice), against the
        // SAME menu both times.
        $this->actingAs($this->admin)->put('/admin/menus', ['locale' => 'bn', 'items' => json_encode($tree)])->assertRedirect();
        $this->actingAs($this->admin)->put('/admin/menus', ['locale' => 'bn', 'items' => json_encode($tree)])->assertRedirect();

        $bn = Menu::forSchool($this->school->id)->where('locale', 'bn')->with('items.children')->first();

        // 6 top-level items; About and Gallery each have 2 children = 10 total.
        $this->assertCount(6, $bn->items);
        $this->assertSame(10, $bn->allItems()->count());
        $this->assertCount(2, $bn->items->firstWhere('label', 'About')->children);
        $this->assertCount(2, $bn->items->firstWhere('label', 'Gallery')->children);
        // No leftover rows from the first save's IDs, no duplicate labels.
        $this->assertSame(['Home', 'About', 'Faculty', 'Teachers', 'Gallery', 'Contact'], $bn->items->pluck('label')->all());
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
}
