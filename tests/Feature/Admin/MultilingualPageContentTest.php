<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageLayout;
use App\Modules\Website\Models\SiteSetting;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/modules/30-multilingual-content-plan.md Phase 2 — a page's block
 * content, title, and SEO meta can now vary per language: each locale owns
 * its own independently-saved/published PageLayout row, `pages.*` stays the
 * default-locale seed, and an untranslated locale falls back to it.
 */
class MultilingualPageContentTest extends TestCase
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
            'name' => 'Test School', 'is_active' => true, 'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka', 'locale' => 'en', 'academic_year_pattern' => 'jan_dec',
        ]);
        SiteSetting::create(['school_id' => $this->school->id, 'site_name' => 'Test School']);
        $this->admin = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
        $this->admin->assignRole('admin');
    }

    public function test_editor_shows_a_language_switcher_marking_untranslated_locales(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'About', 'template' => 'full']);
        $page = Page::first();

        $this->get("/admin/pages/{$page->id}/edit")->assertOk()->assertSee('untranslated');
    }

    /**
     * Regression guard for the editor's own JS: the Copy-from-default-
     * language / Suggest-translation(AI) actions run over fetch() and splice
     * the response's DOM fragments straight into the live page (see
     * edit.blade.php's own trailing script) instead of doing a full
     * navigation — that script locates everything it needs purely by
     * element id. PHPUnit can't execute that JS, but it can at least catch
     * one of those ids ever being accidentally renamed/removed from the
     * markup, which would silently break the in-place update client-side
     * with no server-side error to surface it.
     */
    public function test_editor_markup_carries_the_element_ids_the_ajax_translation_script_depends_on(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'About', 'template' => 'full']);
        $page = Page::first();

        $response = $this->get("/admin/pages/{$page->id}/edit?locale=bn")->assertOk();

        foreach (['translation-banner', 'translation-progress', 'known-layout-id', 'lang-switcher', 'history-list', 'blocks-list', 'sidebar-list'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        $response->assertSee('data-needs-publish=', false);
    }

    /**
     * Pages admin list — one column per active non-default language, ticked
     * only when a currently-PUBLISHED PageLayout exists for that language
     * (Page::hasPublishedTranslation()), not merely "a draft was started".
     */
    public function test_pages_index_shows_a_bn_column_ticked_only_once_bengali_is_published(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'About', 'template' => 'full']);
        $page = Page::first();
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'About Us', 'slug' => 'about', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'English Heading']]],
        ])->assertRedirect();

        // Not yet translated -- the BN column should show a cross.
        $before = $this->get('/admin/pages')->assertOk();
        $before->assertSee('BN');
        $before->assertSeeInOrder(['About', 'bi-x-lg']);

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'আমাদের সম্পর্কে', 'slug' => 'about', 'status' => 'published', 'template' => 'full', 'locale' => 'bn',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'বাংলা শিরোনাম']]],
        ])->assertRedirect();

        // Now published for bn -- the column should show a tick instead.
        $after = $this->get('/admin/pages')->assertOk();
        $after->assertSeeInOrder(['About', 'bi-check-lg']);
    }

    public function test_saving_a_non_default_locale_does_not_touch_the_default_locales_content(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'About', 'template' => 'full']);
        $page = Page::first();

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'About Us', 'slug' => 'about', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'English Heading']]],
        ])->assertRedirect();

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'আমাদের সম্পর্কে', 'slug' => 'about', 'status' => 'published', 'template' => 'full', 'locale' => 'bn',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'বাংলা শিরোনাম']]],
        ])->assertRedirect();

        // The shared `pages` row (default-locale seed) still holds the
        // English title — the Bangla save never touched it.
        $page->refresh();
        $this->assertSame('About Us', $page->title);

        // Each locale has its OWN independently published layout.
        $this->assertDatabaseHas('page_layouts', ['page_id' => $page->id, 'locale' => 'en', 'is_published' => true]);
        $this->assertDatabaseHas('page_layouts', ['page_id' => $page->id, 'locale' => 'bn', 'is_published' => true]);

        // An English visitor sees the English content only.
        $this->get('/about')->assertOk()
            ->assertSee('English Heading')->assertDontSee('বাংলা শিরোনাম');

        // A Bangla visitor (session-chosen locale, same mechanism the public
        // language switcher uses) sees the Bangla content instead.
        $this->withSession(['app_locale' => 'bn'])->get('/about')->assertOk()
            ->assertSee('বাংলা শিরোনাম')->assertDontSee('English Heading');
    }

    public function test_publishing_one_locale_does_not_unpublish_another(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'Mission', 'template' => 'full']);
        $page = Page::first();

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'Mission', 'slug' => 'mission', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Our Mission']]],
        ])->assertRedirect();

        // Save (and publish) a Bangla revision afterward.
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'লক্ষ্য', 'slug' => 'mission', 'status' => 'published', 'template' => 'full', 'locale' => 'bn',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'আমাদের লক্ষ্য']]],
        ])->assertRedirect();

        // English is still published — publishing Bangla didn't unpublish it.
        $this->assertDatabaseHas('page_layouts', ['page_id' => $page->id, 'locale' => 'en', 'is_published' => true]);
        $this->get('/mission')->assertOk()->assertSee('Our Mission');
    }

    public function test_untranslated_locale_falls_back_to_the_default_language(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'Contact', 'template' => 'full']);
        $page = Page::first();

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'Contact Us', 'slug' => 'contact', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Reach Us']]],
        ])->assertRedirect();

        // No Bangla translation exists yet — a Bangla visitor still sees the
        // English content instead of a blank page.
        $this->withSession(['app_locale' => 'bn'])->get('/contact')->assertOk()->assertSee('Reach Us');
    }

    public function test_copy_from_default_language_seeds_an_unpublished_draft(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'FAQ', 'template' => 'full']);
        $page = Page::first();

        $this->put("/admin/pages/{$page->id}", [
            'title' => 'FAQ', 'slug' => 'faq', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Frequently Asked']]],
        ])->assertRedirect();

        $this->get("/admin/pages/{$page->id}/edit?locale=bn")
            ->assertOk()->assertSee('This page has no content in this language yet');

        $this->post("/admin/pages/{$page->id}/copy-locale", ['from_locale' => 'en', 'to_locale' => 'bn'])
            ->assertRedirect();

        $bnLayout = PageLayout::where('page_id', $page->id)->where('locale', 'bn')->first();
        $this->assertNotNull($bnLayout);
        $this->assertSame('Frequently Asked', $bnLayout->layout_json['blocks'][0]['data']['text']);
        // A copy is a draft, not auto-published — it never leaks onto the
        // public site as "the Bangla version" until an admin reviews it and
        // Saves with status=published.
        $this->assertFalse((bool) $bnLayout->is_published);
        $this->assertDatabaseMissing('page_layouts', ['id' => $bnLayout->id, 'is_published' => true]);

        // Regression: the page is already published overall, and this
        // fresh copy pre-fills the editor exactly as loaded — the Update
        // button's form-diff check alone can't tell there's anything to
        // publish, so PageController::edit() has to flag it explicitly (see
        // 'needsPublish', consumed by edit.blade.php's
        // updateSaveButtonState()) or the admin has no way to publish the
        // copy without making a throwaway edit first.
        $this->get("/admin/pages/{$page->id}/edit?locale=bn")->assertViewHas('needsPublish', true);
    }

    public function test_optimistic_concurrency_check_is_scoped_per_locale(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pages', ['title' => 'Notices', 'template' => 'full']);
        $page = Page::first();
        $enLayoutId = $page->layoutsForLocale('en')->first()->id;

        // Someone else fully saves+publishes a Bangla translation in between
        // — this must never affect the English optimistic-concurrency check.
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'বিজ্ঞপ্তি', 'slug' => 'notices', 'status' => 'published', 'template' => 'full', 'locale' => 'bn',
            'blocks' => [], 'known_layout_id' => '',
        ])->assertRedirect();

        // The original admin now saves English using the layout id from
        // BEFORE the Bangla save — should NOT be reported as a conflict,
        // since no one else touched the English revision.
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'Notices', 'slug' => 'notices', 'status' => 'published', 'template' => 'full', 'locale' => 'en',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'All Notices']]],
            'known_layout_id' => $enLayoutId,
        ])->assertRedirect()->assertSessionHas('status')->assertSessionMissing('warning');
    }
}
