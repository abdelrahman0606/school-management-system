<?php

namespace Tests\Feature\Public;

use App\Modules\School\Models\School;
use App\Modules\Staff\Models\Staff;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageLayout;
use App\Modules\Website\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public block-based page rendering: templates (full / sidebar), core blocks,
 * homepage wiring, dynamic staff block, and slug 404s.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create([
            'name' => 'Test School', 'is_active' => true, 'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka', 'locale' => 'en', 'academic_year_pattern' => 'jan_dec',
            'address' => '1 School Lane', 'email' => 'hello@test.school',
        ]);
        SiteSetting::create(['school_id' => $this->school->id, 'site_name' => 'Test School']);
    }

    private function publishPage(string $slug, string $title, array $layout, bool $homepage = false): Page
    {
        $page = Page::create([
            'school_id' => $this->school->id, 'slug' => $slug, 'title' => $title,
            'status' => 'published', 'is_homepage' => $homepage,
        ]);
        PageLayout::create([
            'school_id' => $this->school->id, 'page_id' => $page->id,
            'layout_json' => $layout, 'is_published' => true, 'published_at' => now(),
        ]);

        return $page;
    }

    public function test_full_width_page_renders_blocks(): void
    {
        $this->publishPage('history', 'Our History', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'A proud history']],
                ['type' => 'richtext', 'data' => ['heading' => 'Since 1942', 'html' => '<p>Founded in nineteen forty two.</p>']],
            ],
        ]);

        $this->get('/history')
            ->assertOk()
            ->assertSee('A proud history')
            ->assertSee('Since 1942')
            ->assertSee('Founded in nineteen forty two.');
    }

    public function test_sidebar_template_renders_main_and_sidebar(): void
    {
        $this->publishPage('contact', 'Contact', [
            'template' => 'sidebar',
            'blocks' => [
                ['type' => 'contact', 'data' => ['heading' => 'Reach out', 'phone' => '01700000000']],
            ],
            'sidebar' => [
                ['type' => 'quick_links', 'data' => ['heading' => 'Links', 'links' => [['label' => 'Notices', 'url' => '/notices']]]],
                ['type' => 'contact_info', 'data' => ['heading' => 'Find us']],
            ],
        ]);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Reach out')
            ->assertSee('01700000000')
            ->assertSee('Links')
            ->assertSee('Find us')
            ->assertSee('1 School Lane'); // contact_info pulls the school address
    }

    public function test_staff_block_renders_live_staff(): void
    {
        Staff::create([
            'school_id' => $this->school->id, 'employee_id' => 'EMP-1', 'name' => 'Professor Plum',
            'gender' => 'male', 'status' => 'active', 'joining_date' => now()->subYear(),
        ]);

        $this->publishPage('teachers', 'Teachers', [
            'template' => 'full',
            'blocks' => [['type' => 'staff', 'data' => ['heading' => 'Our teachers']]],
        ]);

        $this->get('/teachers')->assertOk()->assertSee('Our teachers')->assertSee('Professor Plum');
    }

    public function test_homepage_layout_drives_root(): void
    {
        $this->publishPage('welcome', 'Welcome', [
            'template' => 'full',
            'blocks' => [['type' => 'hero', 'data' => ['title' => 'Welcome to our campus', 'subtitle' => 'Learn and grow']]],
        ], homepage: true);

        $this->get('/')->assertOk()->assertSee('Welcome to our campus')->assertSee('Learn and grow');
    }

    public function test_unknown_and_unpublished_slugs_404(): void
    {
        // Draft page — not published, must not be publicly visible.
        $page = Page::create([
            'school_id' => $this->school->id, 'slug' => 'secret', 'title' => 'Secret', 'status' => 'draft',
        ]);
        PageLayout::create([
            'school_id' => $this->school->id, 'page_id' => $page->id,
            'layout_json' => ['template' => 'full', 'blocks' => []], 'is_published' => false,
        ]);

        $this->get('/secret')->assertNotFound();
        $this->get('/does-not-exist')->assertNotFound();
    }

    public function test_empty_image_and_video_blocks_show_a_placeholder_instead_of_a_broken_element(): void
    {
        // §7y in docs/modules/28-elementor-block-editor-plan.md — a bare
        // <img src=""> (or <video> with no source) rendered visibly broken;
        // an empty media field now shows a neutral "No X selected" box.
        $this->publishPage('empty-media', 'Empty Media', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'image', 'data' => []],
                ['type' => 'image_text', 'data' => ['heading' => 'No Photo Yet']],
                ['type' => 'video', 'data' => ['source' => 'youtube']],
                ['type' => 'video', 'data' => ['source' => 'self_hosted']],
            ],
        ]);

        $response = $this->get('/empty-media');
        $response->assertOk();
        $response->assertDontSee('<img src=""', false);
        $response->assertSee('No image selected');
        $response->assertSee('No video URL selected');
        $response->assertSee('No video file selected');
    }

    public function test_image_block_with_a_url_renders_the_real_img_tag_not_the_placeholder(): void
    {
        $this->publishPage('real-image', 'Real Image', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'image', 'data' => ['url' => 'https://example.com/photo.jpg', 'caption' => 'A photo']],
            ],
        ]);

        $response = $this->get('/real-image');
        $response->assertOk();
        $response->assertSee('src="https://example.com/photo.jpg"', false);
        $response->assertDontSee('No image selected');
    }

    public function test_announcement_bar_renders_message_link_and_dismiss_control(): void
    {
        $this->publishPage('with-banner', 'With Banner', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'announcement_bar', 'data' => [
                    'text' => 'Admissions open for 2026-27',
                    'link_url' => '/online-admission', 'link_text' => 'Apply now',
                    'dismissible' => true,
                ]],
            ],
        ]);

        $response = $this->get('/with-banner');
        $response->assertOk()
            ->assertSee('Admissions open for 2026-27')
            ->assertSee('Apply now')
            ->assertSee('href="/online-admission"', false)
            ->assertSee('data-announcement-bar="', false)
            ->assertSee('announcement-bar-dismiss js-announcement-dismiss', false);
    }

    public function test_announcement_bar_without_dismissible_has_no_dismiss_control(): void
    {
        $this->publishPage('no-dismiss', 'No Dismiss', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'announcement_bar', 'data' => ['text' => 'Just a message']],
            ],
        ]);

        // Both checks match the real rendered markup only, not the page-wide
        // dismiss script (public/layout.blade.php), which always contains the
        // literal strings '[data-announcement-bar]' and '.js-announcement-dismiss'
        // regardless of whether any block on this particular page uses them —
        // a bare assertDontSee('js-announcement-dismiss') would match that
        // script text and false-fail here even with no dismiss button
        // rendered. 'announcement-bar-dismiss js-announcement-dismiss' (no
        // leading dot, exactly as it appears in the button's class="...")
        // only appears when the button itself is actually rendered.
        $this->get('/no-dismiss')
            ->assertOk()
            ->assertSee('Just a message')
            ->assertDontSee('data-announcement-bar="', false)
            ->assertDontSee('announcement-bar-dismiss js-announcement-dismiss', false);
    }

    public function test_empty_announcement_bar_shows_a_placeholder(): void
    {
        $this->publishPage('empty-banner', 'Empty Banner', [
            'template' => 'full',
            'blocks' => [['type' => 'announcement_bar', 'data' => []]],
        ]);

        $this->get('/empty-banner')->assertOk()->assertSee('No announcement text set');
    }

    public function test_faq_block_renders_accordion_with_first_item_expanded(): void
    {
        $this->publishPage('faq-page', 'FAQ', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'faq', 'data' => ['heading' => 'Common questions', 'faq_items' => [
                    ['question' => 'What are the school hours?', 'answer' => '8am to 3pm.'],
                    ['question' => 'Is transport provided?', 'answer' => 'Yes, on select routes.'],
                ]]],
            ],
        ]);

        $response = $this->get('/faq-page');
        $response->assertOk()
            ->assertSee('Common questions')
            ->assertSee('What are the school hours?')
            ->assertSee('8am to 3pm.')
            ->assertSee('Is transport provided?')
            ->assertSee('accordion', false)
            // First item starts expanded, later ones collapsed.
            ->assertSeeInOrder(['What are the school hours?', 'collapsed'], false);
    }

    public function test_faq_block_with_no_items_shows_a_placeholder(): void
    {
        $this->publishPage('faq-empty', 'FAQ Empty', [
            'template' => 'full',
            'blocks' => [['type' => 'faq', 'data' => ['heading' => 'Questions']]],
        ]);

        $this->get('/faq-empty')->assertOk()->assertSee('No FAQs Added Yet.');
    }

    public function test_unknown_block_type_is_ignored(): void
    {
        $this->publishPage('mixed', 'Mixed', [
            'template' => 'full',
            'blocks' => [
                ['type' => 'evil_script', 'data' => []],
                ['type' => 'heading', 'data' => ['text' => 'Still here']],
            ],
        ]);

        $this->get('/mixed')->assertOk()->assertSee('Still here');
    }
}
