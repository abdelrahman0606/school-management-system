<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Language\Models\ContentTranslation;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\Menu;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageLayout;
use App\Modules\Website\Models\SiteSetting;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * docs/modules/30-multilingual-content-plan.md Phase 5 — "Suggest
 * translation (AI)" across School settings, the page builder, and the menu
 * editor, backed by the free MyMemory API (mocked here, never a real
 * network call) via queued jobs that run inline under this app's default
 * QUEUE_CONNECTION=sync.
 */
class AiTranslationSuggestTest extends TestCase
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
            'name' => 'Green Valley Model School', 'address' => '12 Lake Road, Dhaka',
            'is_active' => true, 'currency' => 'USD', 'timezone' => 'UTC',
            'locale' => 'en', 'academic_year_pattern' => 'jan_dec',
        ]);
        SiteSetting::create(['school_id' => $this->school->id, 'meta_description' => 'A traditional institution.']);
        $this->admin = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
        $this->admin->assignRole('admin');
    }

    private function fakeMyMemory(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'অনুবাদিত'],
                'responseStatus' => 200,
            ], 200),
        ]);
    }

    // ── School ───────────────────────────────────────────────────────────

    public function test_admin_can_request_ai_suggested_translations_for_school_settings(): void
    {
        $this->fakeMyMemory();

        $this->actingAs($this->admin)->post('/admin/school/translations/suggest', ['locale' => 'bn'])
            ->assertRedirect();

        $this->school->refresh();
        $this->assertSame('অনুবাদিত', $this->school->trans('name', 'bn'));
        $this->assertSame('অনুবাদিত', $this->school->trans('address', 'bn'));
        $settings = SiteSetting::forSchool($this->school->id);
        $this->assertSame('অনুবাদিত', $settings->trans('meta_description', 'bn'));
    }

    public function test_ai_suggestion_never_overwrites_a_field_the_admin_already_translated(): void
    {
        $this->school->setTranslation('name', 'bn', 'হাতে অনুবাদ করা নাম');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)->post('/admin/school/translations/suggest', ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame('হাতে অনুবাদ করা নাম', $this->school->trans('name', 'bn'));
    }

    public function test_default_locale_is_a_no_op(): void
    {
        Http::fake();

        $this->actingAs($this->admin)->post('/admin/school/translations/suggest', ['locale' => 'en'])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame(0, ContentTranslation::query()->count());
    }

    // ── Pages ────────────────────────────────────────────────────────────

    public function test_admin_can_request_an_ai_translated_draft_for_a_page(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'About', 'template' => 'full']);
        $page = Page::first();

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'About Us', 'slug' => 'about', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'English Heading']]],
        ])->assertRedirect();

        $this->fakeMyMemory();

        $this->post("/admin/pages/{$page->id}/suggest-translation", ['locale' => 'bn'])->assertRedirect();

        $bnLayout = PageLayout::where('page_id', $page->id)->where('locale', 'bn')->latest('id')->first();
        $this->assertNotNull($bnLayout);
        $this->assertSame('অনুবাদিত', $bnLayout->title);
        $this->assertSame('অনুবাদিত', $bnLayout->layout_json['blocks'][0]['data']['text']);
        // A suggestion is a draft, never auto-published.
        $this->assertFalse((bool) $bnLayout->is_published);

        // The English page is completely untouched.
        $page->refresh();
        $this->assertSame('About Us', $page->title);
    }

    public function test_running_suggest_again_adds_another_draft_without_touching_the_first(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'About', 'template' => 'full']);
        $page = Page::first();
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'About Us', 'slug' => 'about', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'English Heading']]],
        ]);

        $this->fakeMyMemory();
        $this->post("/admin/pages/{$page->id}/suggest-translation", ['locale' => 'bn']);
        $this->post("/admin/pages/{$page->id}/suggest-translation", ['locale' => 'bn']);

        $this->assertSame(2, PageLayout::where('page_id', $page->id)->where('locale', 'bn')->count());
    }

    // ── Menus ────────────────────────────────────────────────────────────

    public function test_admin_can_build_a_menu_for_an_untranslated_locale_from_ai_translated_labels(): void
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

        $this->fakeMyMemory();

        $this->post('/admin/menus/suggest-translation', ['locale' => 'bn'])->assertRedirect();

        $bn = Menu::forSchool($this->school->id)->where('locale', 'bn')->with('items.children')->first();
        $this->assertNotNull($bn);
        $this->assertCount(2, $bn->items);
        $this->assertSame('অনুবাদিত', $bn->items->first()->label);
        $about = $bn->items->firstWhere('type', 'dropdown');
        $this->assertSame('অনুবাদিত', $about->label);
        $this->assertCount(1, $about->children);
        $this->assertSame('অনুবাদিত', $about->children->first()->label);
        // Structure (url/target) carries over unchanged.
        $this->assertSame('/history', $about->children->first()->url);
        $this->assertSame('_blank', $about->children->first()->target);
    }

    public function test_refuses_to_overwrite_a_locale_that_already_has_menu_items(): void
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

        Http::fake(); // must never even be called -- the job's own safety check must short-circuit first

        $this->post('/admin/menus/suggest-translation', ['locale' => 'bn'])->assertRedirect();

        Http::assertNothingSent();
        $bn = Menu::forSchool($this->school->id)->where('locale', 'bn')->with('items')->first();
        $this->assertSame('হোম হাতে', $bn->items->first()->label);
        $this->assertCount(1, $bn->items);
    }
}
