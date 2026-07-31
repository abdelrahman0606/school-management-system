<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\School\Models\School;
use App\Modules\School\Models\SchoolPhone;
use App\Modules\Staff\Models\Designation;
use App\Modules\Staff\Models\Staff;
use App\Modules\Student\Models\Student;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageLayout;
use App\Modules\Website\Models\SiteSetting;
use App\Support\LocalizedDate;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LanguageSeeder::class); // en (default) + bn, both active

        $this->school = School::create([
            'name' => 'Test School', 'is_active' => true, 'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka', 'locale' => 'en', 'academic_year_pattern' => 'jan_dec',
        ]);
        SiteSetting::create(['school_id' => $this->school->id, 'site_name' => 'Test School']);
        // announcements.created_by is NOT NULL -- every Announcement::create()
        // in this file needs a real user id, not just a role/permission (none
        // of these tests act as this user, it's purely the FK).
        $this->author = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
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
            'school_id' => $this->school->id, 'created_by' => $this->author->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
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
            'school_id' => $this->school->id, 'created_by' => $this->author->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
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
            'school_id' => $this->school->id, 'created_by' => $this->author->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
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

    /**
     * App\Support\LocalizedDate — native (offline, no translation API) month
     * name + digit localization. The notices block's date line
     * (optional($n->publish_at)->format('d M Y') before this) is the most
     * visible place this shows up.
     */
    public function test_notices_block_date_shows_bengali_month_name_and_native_digits(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 31, 10));

        Announcement::create([
            'school_id' => $this->school->id, 'created_by' => $this->author->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false, 'publish_at' => now()->subDay(), // 2026-07-30
        ]);

        $this->publishPage('notices-date', [
            'template' => 'full',
            'blocks' => [['type' => 'notices', 'data' => []]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/notices-date')
            ->assertOk()
            ->assertSee('৩০ জুলাই ২০২৬');

        Carbon::setTestNow();
    }

    /**
     * The footer copyright year (public/layout.blade.php) uses PHP's raw
     * date('Y'), which Carbon::setTestNow() doesn't affect — asserts against
     * whatever "now" actually is via the same LocalizedDate::digits() helper
     * the view itself calls, rather than hardcoding a year that would go
     * stale.
     */
    public function test_footer_copyright_year_uses_native_bengali_digits(): void
    {
        $this->publishPage('footer-year', [
            'template' => 'full',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Hi']]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/footer-year')
            ->assertOk()
            ->assertSee(LocalizedDate::digits(date('Y'), 'bn'));
    }

    /**
     * Regression guard: the header's "today" date (public/partials/header.blade.php)
     * used to pin to $school->locale (the school's configured home-language column,
     * 'en' by default) instead of the visitor's browsing locale — the one date on the
     * public site that silently ignored the language switcher. It must follow
     * app()->getLocale() like every other date on the site (footer year, notices).
     */
    public function test_header_today_date_follows_the_visitors_locale_not_the_schools_own_locale(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 31, 10, 0, 0, 'Asia/Dhaka'));
        $this->school->update(['locale' => 'en']); // school's own locale stays English

        $this->publishPage('header-date', [
            'template' => 'full',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Hi']]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/header-date')
            ->assertOk()
            ->assertSee('৩১ জুলাই ২০২৬');

        Carbon::setTestNow();
    }

    /**
     * Reported: "stats block number is in English even in bn". number_format()
     * only ever produces ASCII 0-9 -- LocalizedDate::digits() has to be applied
     * explicitly on top of it, same as the footer copyright year already does.
     */
    public function test_stats_block_numbers_use_native_bengali_digits(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Student::create([
                'school_id' => $this->school->id, 'admission_number' => "ADM-{$i}",
                'name' => "Student {$i}", 'gender' => 'male', 'status' => 'active',
            ]);
        }

        $this->publishPage('stats-digits', [
            'template' => 'full',
            'blocks' => [['type' => 'stats', 'data' => ['heading' => 'At a glance']]],
        ]);

        // Not asserting assertDontSee('15') here -- too generic a substring
        // (asset version strings, hidden tokens, etc.) to safely rule out
        // elsewhere on the page; the Bengali digit assertion alone proves the
        // fix.
        $this->withSession(['app_locale' => 'bn'])->get('/stats-digits')
            ->assertOk()
            ->assertSee('১৫');
    }

    /**
     * Reported: "contact block isn't receiving translated content". The contact
     * block's fallback address (used whenever the block itself doesn't set its
     * own $d['address'] override, i.e. the seeded demo contact page) came from a
     * plain School::find() in PageRenderService::resolveBlockData() and was read
     * off the model directly ($school->address) instead of transOr('address') --
     * the one address-shaped output on the public site that ignored the
     * language switcher even though School::address is a HasTranslations field.
     */
    public function test_contact_block_address_follows_the_visitors_locale(): void
    {
        $this->school->update(['address' => 'Natipota, Damurhuda, Chuadanga']);
        $this->school->setTranslation('address', 'bn', 'নাটুদহ, দামুড়হুদা, চুয়াডাঙ্গা');

        $this->publishPage('contact-address', [
            'template' => 'full',
            'blocks' => [['type' => 'contact', 'data' => ['heading' => 'Get in touch']]],
        ]);

        $this->withSession(['app_locale' => 'bn'])->get('/contact-address')
            ->assertOk()
            ->assertSee('নাটুদহ, দামুড়হুদা, চুয়াডাঙ্গা')
            ->assertDontSee('Natipota, Damurhuda, Chuadanga');
    }

    /**
     * Reported: "header mobile number link will be in English but the shown
     * content should be in translation" — the header's tel: link text used
     * raw ASCII digits like every other number on the site used to. Fixed by
     * localizing the visible text with LocalizedDate::digits() while leaving
     * the href alone -- a tel: link has to stay dialable, so native-digit
     * glyphs there would break click-to-call on most devices.
     */
    public function test_header_phone_link_text_uses_native_digits_but_href_stays_dialable(): void
    {
        SchoolPhone::create([
            'school_id' => $this->school->id, 'phone' => '01712345678',
            'is_primary' => true, 'show_in_header' => true,
        ]);

        $this->publishPage('header-phone', [
            'template' => 'full',
            'blocks' => [['type' => 'heading', 'data' => ['text' => 'Hi']]],
        ]);

        $response = $this->withSession(['app_locale' => 'bn'])->get('/header-phone')->assertOk();
        $response->assertSee('href="tel:01712345678"', false);
        $response->assertSee(LocalizedDate::digits('01712345678', 'bn'));
    }
}
