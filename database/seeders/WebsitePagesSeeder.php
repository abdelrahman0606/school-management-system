<?php

namespace Database\Seeders;

use App\Modules\Language\Models\Language;
use App\Modules\School\Models\School;
use App\Modules\Website\Models\Menu;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageLayout;
use App\Modules\Website\Models\SiteSetting;
use App\Modules\Website\Services\MenuService;
use Illuminate\Database\Seeder;

/**
 * Publishes the standard public pages referenced by the site navigation
 * (About/History/Mission/Administration, Staff/Teachers, Online admission,
 * Gallery/Video, Contact, Notices) with real block content, so the whole public
 * site is navigable right after install. Staff/notices/stats blocks pull live
 * data seeded by DemoDataSeeder. Also exercises every block type introduced by
 * docs/modules/29-frontend-modernization-proposal.md — the homepage leads with
 * an announcement_bar, and Online Admission ends with a faq block — so the
 * demo site actually shows both in real use, not just their empty states.
 *
 * Every page also gets a Bangla (bn) PageLayout — docs/modules/
 * 30-multilingual-content-plan.md Phase 2 — via pageTranslation(), reusing the
 * SAME Page row (a Page serves every locale; only its PageLayout is
 * duplicated per locale). Bangla block content is hand-written, not run
 * through the AI-suggest gateway — see SchoolSeeder's own comment on why seed
 * data doesn't make a live network call at seed time. The primary navigation
 * Menu gets the same bn treatment in seedMenu() below.
 */
class WebsitePagesSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            return;
        }
        $sid = $school->id;

        $links = [
            ['label' => 'Notices', 'url' => '/notices'],
            ['label' => 'Staff', 'url' => '/staff'],
            ['label' => 'Online admission', 'url' => '/online-admission'],
            ['label' => 'Contact', 'url' => '/contact'],
        ];
        $linksBn = [
            ['label' => 'নোটিশ', 'url' => '/notices'],
            ['label' => 'শিক্ষকমণ্ডলী', 'url' => '/staff'],
            ['label' => 'অনলাইন ভর্তি', 'url' => '/online-admission'],
            ['label' => 'যোগাযোগ', 'url' => '/contact'],
        ];

        // ── Homepage (block-built, set as is_homepage so "/" renders it) ────────
        $this->page($sid, 'home', 'Home', 'full', [
            ['type' => 'announcement_bar', 'data' => [
                'text' => 'Admissions open for the 2026–27 academic year.',
                'link_text' => 'Apply now', 'link_url' => '/online-admission',
                'dismissible' => true,
            ]],
            ['type' => 'hero', 'data' => [
                'title' => $school->name ?: 'Welcome to our school',
                'subtitle' => 'Nurturing curiosity, character, and community.',
                'button_text' => 'Apply for admission',
                'button_url' => '/online-admission',
            ]],
            ['type' => 'stats',   'data' => ['heading' => 'At a glance']],
            ['type' => 'notices', 'data' => ['heading' => 'Latest notices', 'limit' => 6]],
            ['type' => 'staff',   'data' => ['heading' => 'Our faculty']],
            ['type' => 'heading', 'data' => ['text' => 'Admissions are open — join our community today.', 'align' => 'center']],
        ], [], isHomepage: true);

        $this->pageTranslation($sid, 'home', 'bn', 'হোম', 'full', [
            ['type' => 'announcement_bar', 'data' => [
                'text' => 'নতুন শিক্ষাবর্ষ ২০২৬–২৭-এর জন্য ভর্তি চলছে।',
                'link_text' => 'এখনই আবেদন করুন', 'link_url' => '/online-admission',
                'dismissible' => true,
            ]],
            ['type' => 'hero', 'data' => [
                'title' => 'আমাদের বিদ্যালয়ে স্বাগতম',
                'subtitle' => 'কৌতূহল, চরিত্র এবং সম্প্রদায়ের লালন।',
                'button_text' => 'ভর্তির জন্য আবেদন করুন',
                'button_url' => '/online-admission',
            ]],
            ['type' => 'stats',   'data' => ['heading' => 'এক নজরে']],
            ['type' => 'notices', 'data' => ['heading' => 'সাম্প্রতিক নোটিশ', 'limit' => 6]],
            ['type' => 'staff',   'data' => ['heading' => 'আমাদের শিক্ষকমণ্ডলী']],
            ['type' => 'heading', 'data' => ['text' => 'ভর্তি চলছে — আজই আমাদের সম্প্রদায়ে যোগ দিন।', 'align' => 'center']],
        ]);

        // ── About / identity pages ──────────────────────────────────────────
        $this->page($sid, 'history', 'Short History', 'sidebar',
            [
                ['type' => 'heading', 'data' => ['text' => 'A proud history']],
                ['type' => 'richtext', 'data' => ['html' => '<p>Green Valley Model School, founded in 1985 in Natipota, Damurhuda, Chuadanga, is a traditional '
                    .'institution that has played an important role in spreading education for decades. Known for its '
                    .'dedicated teachers, well-planned curriculum, and disciplined learning environment, the school gives '
                    .'equal importance to academic excellence and the moral and physical development of its students.</p>']],
            ],
            [
                ['type' => 'quick_links', 'data' => ['heading' => 'Quick links', 'links' => $links]],
                ['type' => 'contact_info', 'data' => ['heading' => 'Contact']],
            ],
        );
        $this->pageTranslation($sid, 'history', 'bn', 'সংক্ষিপ্ত ইতিহাস', 'sidebar',
            [
                ['type' => 'heading', 'data' => ['text' => 'একটি গৌরবময় ইতিহাস']],
                ['type' => 'richtext', 'data' => ['html' => '<p>১৯৮৫ সালে চুয়াডাঙ্গার দামুড়হুদার নাটুদহে প্রতিষ্ঠিত গ্রীন ভ্যালি মডেল স্কুল একটি ঐতিহ্যবাহী প্রতিষ্ঠান, যা দশকের পর দশক ধরে শিক্ষা বিস্তারে গুরুত্বপূর্ণ ভূমিকা পালন করে আসছে। '
                    .'নিবেদিতপ্রাণ শিক্ষক, সুপরিকল্পিত পাঠ্যক্রম এবং শৃঙ্খলাপূর্ণ শিক্ষা-পরিবেশের জন্য পরিচিত এই বিদ্যালয় শিক্ষার্থীদের একাডেমিক উৎকর্ষতার পাশাপাশি নৈতিক ও শারীরিক বিকাশকেও সমান গুরুত্ব দেয়।</p>']],
            ],
            [
                ['type' => 'quick_links', 'data' => ['heading' => 'দ্রুত লিংক', 'links' => $linksBn]],
                ['type' => 'contact_info', 'data' => ['heading' => 'যোগাযোগ']],
            ],
        );

        $this->page($sid, 'about', 'At a Glance', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'At a glance']],
            ['type' => 'richtext', 'data' => ['html' => '<p>We offer education from class Six to Ten with a focus on academic excellence, discipline, and '
                .'character. Our campus provides a safe, supportive environment where every student can thrive.</p>']],
            ['type' => 'stats', 'data' => ['heading' => 'Our school in numbers']],
        ]);
        $this->pageTranslation($sid, 'about', 'bn', 'এক নজরে', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'এক নজরে']],
            ['type' => 'richtext', 'data' => ['html' => '<p>আমরা ষষ্ঠ থেকে দশম শ্রেণি পর্যন্ত শিক্ষা প্রদান করি, যেখানে একাডেমিক উৎকর্ষতা, শৃঙ্খলা এবং চরিত্র গঠনের উপর বিশেষ গুরুত্ব দেওয়া হয়। '
                .'আমাদের ক্যাম্পাস একটি নিরাপদ ও সহায়ক পরিবেশ প্রদান করে যেখানে প্রতিটি শিক্ষার্থী বিকশিত হতে পারে।</p>']],
            ['type' => 'stats', 'data' => ['heading' => 'সংখ্যায় আমাদের বিদ্যালয়']],
        ]);

        $this->page($sid, 'mission', 'Mission & Vision', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'Mission & vision']],
            ['type' => 'richtext', 'data' => ['html' => '<h5>Our mission</h5><p>To nurture curious minds and build a community of lifelong learners through '
                .'quality education and strong values.</p><h5>Our vision</h5><p>To be a leading institution recognised '
                .'for academic excellence, integrity, and service to the community.</p>']],
        ]);
        $this->pageTranslation($sid, 'mission', 'bn', 'লক্ষ্য ও উদ্দেশ্য', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'লক্ষ্য ও উদ্দেশ্য']],
            ['type' => 'richtext', 'data' => ['html' => '<h5>আমাদের লক্ষ্য</h5><p>মানসম্পন্ন শিক্ষা ও দৃঢ় মূল্যবোধের মাধ্যমে কৌতূহলী মন গড়ে তোলা এবং আজীবন শিক্ষার্থীদের একটি সম্প্রদায় গড়ে তোলা।</p>'
                .'<h5>আমাদের উদ্দেশ্য</h5><p>একাডেমিক উৎকর্ষতা, সততা এবং সমাজসেবার জন্য স্বীকৃত একটি শীর্ষস্থানীয় প্রতিষ্ঠান হয়ে ওঠা।</p>']],
        ]);

        $this->page($sid, 'administration', 'Administration', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'Administration']],
            ['type' => 'richtext', 'data' => ['html' => '<p>Our administrative team leads the school with dedication and care.</p>']],
            ['type' => 'staff', 'data' => ['heading' => 'Administrative team']],
        ]);
        $this->pageTranslation($sid, 'administration', 'bn', 'প্রশাসন', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'প্রশাসন']],
            ['type' => 'richtext', 'data' => ['html' => '<p>আমাদের প্রশাসনিক দল নিষ্ঠা ও যত্নের সঙ্গে বিদ্যালয় পরিচালনা করে।</p>']],
            ['type' => 'staff', 'data' => ['heading' => 'প্রশাসনিক দল']],
        ]);

        // ── Staff pages ─────────────────────────────────────────────────────
        // Public listing lives at /faculty — /staff is the authenticated staff portal.
        $this->page($sid, 'faculty', 'Faculty & Staff', 'full', [
            ['type' => 'staff', 'data' => ['heading' => 'Our faculty & staff']],
        ]);
        $this->pageTranslation($sid, 'faculty', 'bn', 'শিক্ষকমণ্ডলী ও কর্মচারী', 'full', [
            ['type' => 'staff', 'data' => ['heading' => 'আমাদের শিক্ষকমণ্ডলী ও কর্মচারী']],
        ]);

        $this->page($sid, 'teachers', 'Teachers', 'full', [
            ['type' => 'staff', 'data' => ['heading' => 'Our teachers']],
        ]);
        $this->pageTranslation($sid, 'teachers', 'bn', 'শিক্ষকবৃন্দ', 'full', [
            ['type' => 'staff', 'data' => ['heading' => 'আমাদের শিক্ষকবৃন্দ']],
        ]);

        // ── Online admission ────────────────────────────────────────────────
        $this->page($sid, 'online-admission', 'Online Admission', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'Online admission']],
            ['type' => 'richtext', 'data' => ['html' => '<p>Admission for the new academic year is now open for classes Six to Ten. '
                .'Please apply online or visit the school office during working hours.</p>']],
            ['type' => 'admission_form', 'data' => ['heading' => 'Apply for admission', 'intro' => 'Start your online application below.']],
            ['type' => 'faq', 'data' => ['heading' => 'Frequently asked questions', 'faq_items' => [
                ['question' => 'What is the age requirement for admission?', 'answer' => 'Students must meet the minimum age requirement for their class as of January 1st of the academic year. Contact the school office for specific class-wise age criteria.'],
                ['question' => 'What documents are required to apply?', 'answer' => "A copy of the student's birth certificate, the previous school's transfer certificate (if applicable), and recent passport-size photographs."],
                ['question' => 'Is there an admission test?', 'answer' => 'Yes — students applying for Class Six and above sit a short admission test covering Bangla, English, and Mathematics.'],
                ['question' => 'When does the academic year start?', 'answer' => 'The academic year runs January to December, with classes typically starting in the first week of January.'],
            ]]],
        ]);
        $this->pageTranslation($sid, 'online-admission', 'bn', 'অনলাইন ভর্তি', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'অনলাইন ভর্তি']],
            ['type' => 'richtext', 'data' => ['html' => '<p>নতুন শিক্ষাবর্ষের জন্য ষষ্ঠ থেকে দশম শ্রেণিতে ভর্তি চলছে। '
                .'অনুগ্রহ করে অনলাইনে আবেদন করুন অথবা কর্মদিবসে বিদ্যালয় কার্যালয়ে যোগাযোগ করুন।</p>']],
            ['type' => 'admission_form', 'data' => ['heading' => 'ভর্তির জন্য আবেদন করুন', 'intro' => 'নিচে আপনার অনলাইন আবেদন শুরু করুন।']],
            ['type' => 'faq', 'data' => ['heading' => 'সচরাচর জিজ্ঞাসিত প্রশ্ন', 'faq_items' => [
                ['question' => 'ভর্তির জন্য বয়সের শর্ত কী?', 'answer' => 'শিক্ষাবর্ষের ১ জানুয়ারি অনুযায়ী শিক্ষার্থীদের সংশ্লিষ্ট শ্রেণির জন্য নির্ধারিত ন্যূনতম বয়স পূরণ করতে হবে। শ্রেণিভিত্তিক নির্দিষ্ট বয়সসীমার জন্য বিদ্যালয় কার্যালয়ে যোগাযোগ করুন।'],
                ['question' => 'আবেদনের জন্য কী কী কাগজপত্র প্রয়োজন?', 'answer' => 'শিক্ষার্থীর জন্ম নিবন্ধন সনদের একটি কপি, পূর্ববর্তী বিদ্যালয়ের ছাড়পত্র (প্রযোজ্য ক্ষেত্রে), এবং সাম্প্রতিক পাসপোর্ট-সাইজ ছবি।'],
                ['question' => 'ভর্তি পরীক্ষা কি আছে?', 'answer' => 'হ্যাঁ — ষষ্ঠ শ্রেণি ও তদূর্ধ্বে আবেদনকারী শিক্ষার্থীদের বাংলা, ইংরেজি ও গণিত বিষয়ে একটি সংক্ষিপ্ত ভর্তি পরীক্ষা দিতে হয়।'],
                ['question' => 'শিক্ষাবর্ষ কখন শুরু হয়?', 'answer' => 'শিক্ষাবর্ষ জানুয়ারি থেকে ডিসেম্বর পর্যন্ত চলে এবং সাধারণত জানুয়ারির প্রথম সপ্তাহে ক্লাস শুরু হয়।'],
            ]]],
        ]);

        // ── Galleries ───────────────────────────────────────────────────────
        $this->page($sid, 'gallery', 'Photo Gallery', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'Photo gallery']],
            ['type' => 'gallery_photo', 'data' => ['images' => array_map(
                fn ($i) => "https://picsum.photos/seed/greenvalley{$i}/600/400", range(1, 8),
            )]],
        ]);
        $this->pageTranslation($sid, 'gallery', 'bn', 'ফটো গ্যালারি', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'ফটো গ্যালারি']],
            ['type' => 'gallery_photo', 'data' => ['images' => array_map(
                fn ($i) => "https://picsum.photos/seed/greenvalley{$i}/600/400", range(1, 8),
            )]],
        ]);

        $this->page($sid, 'video', 'Video Gallery', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'Video gallery']],
            ['type' => 'gallery_video', 'data' => ['videos' => [
                'https://www.youtube.com/embed/aqz-KE-bpKQ',
                'https://www.youtube.com/embed/ScMzIvxBSi4',
            ]]],
        ]);
        $this->pageTranslation($sid, 'video', 'bn', 'ভিডিও গ্যালারি', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'ভিডিও গ্যালারি']],
            ['type' => 'gallery_video', 'data' => ['videos' => [
                'https://www.youtube.com/embed/aqz-KE-bpKQ',
                'https://www.youtube.com/embed/ScMzIvxBSi4',
            ]]],
        ]);

        // ── Contact ─────────────────────────────────────────────────────────
        $this->page($sid, 'contact', 'Contact', 'sidebar',
            [
                ['type' => 'contact', 'data' => ['heading' => 'Get in touch']],
            ],
            [
                ['type' => 'contact_info', 'data' => ['heading' => 'Contact details']],
                ['type' => 'office_hours', 'data' => ['heading' => 'Office hours', 'lines' => [
                    ['label' => 'Sunday – Thursday', 'value' => '8:00 AM – 4:00 PM'],
                    ['label' => 'Friday – Saturday', 'value' => 'Closed'],
                ]]],
            ],
        );
        $this->pageTranslation($sid, 'contact', 'bn', 'যোগাযোগ', 'sidebar',
            [
                ['type' => 'contact', 'data' => ['heading' => 'যোগাযোগ করুন']],
            ],
            [
                ['type' => 'contact_info', 'data' => ['heading' => 'যোগাযোগের বিবরণ']],
                ['type' => 'office_hours', 'data' => ['heading' => 'কার্যালয়ের সময়সূচি', 'lines' => [
                    ['label' => 'রবিবার – বৃহস্পতিবার', 'value' => 'সকাল ৮:০০ – বিকাল ৪:০০'],
                    ['label' => 'শুক্রবার – শনিবার', 'value' => 'বন্ধ'],
                ]]],
            ],
        );

        // ── Notices ─────────────────────────────────────────────────────────
        $this->page($sid, 'notices', 'Notices', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'Notices']],
            ['type' => 'notices', 'data' => ['heading' => 'All notices', 'limit' => 20]],
        ]);
        $this->pageTranslation($sid, 'notices', 'bn', 'নোটিশ', 'full', [
            ['type' => 'heading', 'data' => ['text' => 'নোটিশ']],
            ['type' => 'notices', 'data' => ['heading' => 'সকল নোটিশ', 'limit' => 20]],
        ]);

        $this->seedMenu($sid);
    }

    /** Seed the primary navigation menu (mirrors the site IA) from the pages above, for every active language. */
    private function seedMenu(int $sid): void
    {
        $ids = Page::forSchool($sid)->pluck('id', 'slug');
        $page = fn (string $slug, string $label): array => [
            'label' => $label, 'type' => 'page', 'page_id' => $ids[$slug] ?? null, 'target' => '_self',
        ];

        // locale: docs/modules/30-multilingual-content-plan.md Phase 3 — Menu is
        // now per-locale (mirrors PageLayout below); an unscoped firstOrCreate()
        // here would seed a locale=null row invisible to Menu::published()'s
        // where('locale', $locale) lookup, exactly like the PageLayout bug this
        // seeder already had to fix for Phase 2.
        $menu = Menu::forSchool($sid)->firstOrCreate(
            ['school_id' => $sid, 'locale' => Language::defaultCode()],
            ['name' => 'Main menu'],
        );

        app(MenuService::class)->replaceItems($menu, [
            $page('home', 'Home'),
            ['label' => 'About', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                $page('history', 'Short history'),
                $page('about', 'At a glance'),
                $page('mission', 'Mission & vision'),
                $page('administration', 'Administration'),
            ]],
            $page('faculty', 'Faculty'),
            $page('teachers', 'Teachers'),
            $page('online-admission', 'Online admission'),
            ['label' => 'Gallery', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                $page('gallery', 'Photo gallery'),
                $page('video', 'Video gallery'),
            ]],
            $page('notices', 'Notices'),
            $page('contact', 'Contact'),
        ]);

        // Bangla menu — same Page ids, translated labels. Naming convention
        // ('Main menu (bn)') follows MenuController's own non-default-locale
        // naming so this doesn't collide with the English menu's name in any
        // admin listing.
        $menuBn = Menu::forSchool($sid)->firstOrCreate(
            ['school_id' => $sid, 'locale' => 'bn'],
            ['name' => 'Main menu (bn)'],
        );

        app(MenuService::class)->replaceItems($menuBn, [
            $page('home', 'হোম'),
            ['label' => 'পরিচিতি', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                $page('history', 'সংক্ষিপ্ত ইতিহাস'),
                $page('about', 'এক নজরে'),
                $page('mission', 'লক্ষ্য ও উদ্দেশ্য'),
                $page('administration', 'প্রশাসন'),
            ]],
            $page('faculty', 'শিক্ষকমণ্ডলী'),
            $page('teachers', 'শিক্ষকবৃন্দ'),
            $page('online-admission', 'অনলাইন ভর্তি'),
            ['label' => 'গ্যালারি', 'type' => 'dropdown', 'target' => '_self', 'children' => [
                $page('gallery', 'ফটো গ্যালারি'),
                $page('video', 'ভিডিও গ্যালারি'),
            ]],
            $page('notices', 'নোটিশ'),
            $page('contact', 'যোগাযোগ'),
        ]);
    }

    /**
     * Create (or refresh) a published page with a single default-locale layout revision.
     *
     * @param  array<int, array{type: string, data: array}>  $blocks
     * @param  array<int, array{type: string, data: array}>  $sidebar
     */
    private function page(int $sid, string $slug, string $title, string $template, array $blocks, array $sidebar = [], bool $isHomepage = false): void
    {
        $page = Page::updateOrCreate(
            ['school_id' => $sid, 'slug' => $slug],
            ['title' => $title, 'status' => 'published', 'is_homepage' => $isHomepage],
        );

        if ($isHomepage) {
            SiteSetting::where('school_id', $sid)->update(['homepage_page_id' => $page->id]);
        }

        // Scoped to the default locale only -- an unscoped delete here would
        // also wipe any bn PageLayout already seeded by pageTranslation() on
        // a prior run (this method always runs before pageTranslation() for
        // the same slug within run(), so a fresh reseed would otherwise lose
        // the Bangla layout for one pass every time this seeder re-runs).
        PageLayout::where('page_id', $page->id)->where('locale', Language::defaultCode())->delete();
        PageLayout::create([
            'school_id' => $sid,
            'page_id' => $page->id,
            // docs/modules/30-multilingual-content-plan.md Phase 2 — every
            // PageLayout row needs a real locale; the public render path
            // queries by locale explicitly, so a null-locale row here would
            // silently stop matching and the seeded page would render empty.
            'locale' => Language::defaultCode(),
            'title' => $title,
            'layout_json' => ['template' => $template, 'blocks' => $blocks, 'sidebar' => $sidebar],
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Create (or refresh) a non-default-locale layout revision for an EXISTING
     * page (looked up by slug — never creates a new Page row; the same Page
     * serves every locale per docs/modules/30-multilingual-content-plan.md).
     * No-ops if the page doesn't exist yet, so call order relative to page()
     * doesn't matter beyond "after the matching page() call in this file."
     *
     * @param  array<int, array{type: string, data: array}>  $blocks
     * @param  array<int, array{type: string, data: array}>  $sidebar
     */
    private function pageTranslation(int $sid, string $slug, string $locale, string $title, string $template, array $blocks, array $sidebar = []): void
    {
        $page = Page::forSchool($sid)->where('slug', $slug)->first();
        if (! $page) {
            return;
        }

        PageLayout::where('page_id', $page->id)->where('locale', $locale)->delete();
        PageLayout::create([
            'school_id' => $sid,
            'page_id' => $page->id,
            'locale' => $locale,
            'title' => $title,
            'layout_json' => ['template' => $template, 'blocks' => $blocks, 'sidebar' => $sidebar],
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
