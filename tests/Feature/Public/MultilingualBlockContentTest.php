<?php

namespace Tests\Feature\Public;

use App\Modules\Announcement\Models\Announcement;
use App\Modules\School\Models\School;
use App\Modules\Staff\Models\Designation;
use App\Modules\Staff\Models\Staff;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageLayout;
use App\Modules\Website\Models\SiteSetting;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "notices" and "staff" blocks are live-resolved from Announcement/Staff
 * on every render (PageRenderService::resolveBlockData() -> PublicPortalService),
 * not baked into a page's stored layout_json like a block's own 'heading'
 * text — so BlockTranslator (which only ever walks layout_json) never touches
 * them. Regression guard for the locale actually being threaded all the way
 * down that separate, live-data path to Announcement::trans()/Staff::trans().
 */
class MultilingualBlockContentTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LanguageSeeder::class); // en (default) + bn, both active

        $this->school = School::create([
            'name' => 'Test School', 'is_active' => true, 'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka', 'locale' => 'en', 'academic_year_pattern' => 'jan_dec',
        ]);
        SiteSetting::create(['school_id' => $this->school->id, 'site_name' => 'Test School']);
    }

    /** @param  array<string, mixed>  $layout */
    private function publishPage(string $slug, array $layout): Page
    {
        $page = Page::create([
            'school_id' => $this->school->id, 'slug' => $slug, 'title' => ucfirst($slug),
            'status' => 'published',
        ]);
        PageLayout::create([
            'school_id' => $this->school->id, 'page_id' => $page->id, 'locale' => 'en',
            'layout_json' => $layout, 'is_published' => true, 'published_at' => now(),
        ]);

        return $page;
    }

    public function test_notices_block_shows_the_bengali_translation_when_visiting_in_bengali(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false, 'publish_at' => now()->subDay(),
        ]);
        $announcement->setTranslation('title', 'bn', 'পরীক্ষার সময়সূচী');
        $announcement->setTranslation('body', 'bn', 'পরীক্ষা সোমবার শুরু হবে।');

        $this->publishPage('notices-block', [
            'template' => 'full',
            'blocks' => [['type' => 'notices', 'data' => []]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/notices-block')
            ->assertOk()
            ->assertSee('পরীক্ষার সময়সূচী')
            ->assertDontSee('Exam Schedule');
    }

    public function test_notices_block_falls_back_to_the_default_locale_when_untranslated(): void
    {
        Announcement::create([
            'school_id' => $this->school->id, 'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false, 'publish_at' => now()->subDay(),
        ]);

        $this->publishPage('notices-untranslated', [
            'template' => 'full',
            'blocks' => [['type' => 'notices', 'data' => []]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/notices-untranslated')
            ->assertOk()
            ->assertSee('Exam Schedule');
    }

    public function test_staff_block_shows_the_bengali_translation_of_name_and_designation(): void
    {
        $designation = Designation::create(['school_id' => $this->school->id, 'name' => 'Principal']);
        $designation->setTranslation('name', 'bn', 'অধ্যক্ষ');

        Staff::create([
            'school_id' => $this->school->id, 'employee_id' => 'EMP-1', 'name' => 'John Doe',
            'gender' => 'male', 'status' => 'active', 'joining_date' => now()->subYear(),
            'designation_id' => $designation->id,
        ])->setTranslation('name', 'bn', 'জন ডো');

        $this->publishPage('teachers', [
            'template' => 'full',
            'blocks' => [['type' => 'staff', 'data' => []]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/teachers')
            ->assertOk()
            ->assertSee('জন ডো')
            ->assertSee('অধ্যক্ষ')
            ->assertDontSee('John Doe');
    }

    /**
     * The notice ticker in the public header (AppServiceProvider's
     * public.partials.header composer) is a second, separate call site for
     * PublicPortalService::notices() — easy to fix the block but miss this
     * one, since it's wired via a View::composer rather than a controller.
     */
    public function test_header_notice_ticker_shows_the_bengali_translation(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false, 'publish_at' => now()->subDay(),
        ]);
        $announcement->setTranslation('title', 'bn', 'পরীক্ষার সময়সূচী');

        $this->publishPage('home-with-ticker', [
            'template' => 'full',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Hi']]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/home-with-ticker')
            ->assertOk()
            ->assertSee('পরীক্ষার সময়সূচী');
    }
}
