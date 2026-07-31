<?php

namespace Database\Seeders;

use App\Modules\School\Models\School;
use App\Modules\School\Models\SchoolOpeningHour;
use App\Modules\School\Models\SchoolPhone;
use App\Modules\Website\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $profile = [
            'name' => 'Green Valley Model School',
            'established' => '1985-01-01',
            // Three configurable code fields (label + value)
            'institution_code_label' => 'EIIN',
            'institution_code' => '115394',
            'school_code_label' => 'Technical Branch Code',
            'school_code' => '0000',
            'technical_branch_code_label' => 'School Code',
            'technical_branch_code' => '5556',
            'address' => 'Natipota, Damurhuda, Chuadanga',
            'country_code' => 'BD',
            'email' => 'info@greenvalley.edu.bd',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'locale' => 'en',
            'academic_year_pattern' => 'jan_dec',
            'is_active' => true,
        ];

        // Single-tenant: update the sole school if it exists, else create it.
        $school = School::first();
        $school ? $school->update($profile) : $school = School::create($profile);

        // Public-site appearance defaults. The "Advanced Theme" fields below
        // (secondary/surface colors, fonts, button styling) are deliberately
        // set here too — docs/modules/29-frontend-modernization-proposal.md
        // Phase 1 wired these up, but every one of them is optional and
        // invisible until a school actually sets a value; the demo site is
        // the one place that should actually show them in use, not just
        // exercise their defaults like every other seeded school would.
        SiteSetting::updateOrCreate(
            ['school_id' => $school->id],
            [
                'primary_color' => '#0a6b2f',
                'accent_color' => '#f59e0b',
                'heading_color' => '#0f172a',
                'topbar_text_color' => '#ffffff',
                'ticker_position' => 'below_nav',
                'meta_title' => 'Green Valley Model School',
                'meta_description' => 'A traditional institution nurturing curious minds since 1985.',
                // Advanced theme (Phase 1)
                'secondary_color' => '#0b3d24', // footer background — a deep forest green pairing with primary
                'surface_color' => '#f8faf8',   // subtle warm-green tint behind cards, instead of plain white
                'font_heading' => 'Poppins',
                'font_body' => 'Inter',
                'btn_radius' => 10,
                'btn_font_weight' => '600',
            ],
        );

        // Contact numbers — both shown (clickable) in the site header.
        SchoolPhone::where('school_id', $school->id)->delete();
        foreach ([
            ['phone' => '01309115394', 'is_primary' => true,  'show_in_header' => true],
            ['phone' => '01710866871', 'is_primary' => false, 'show_in_header' => true],
        ] as $phone) {
            SchoolPhone::create($phone + ['school_id' => $school->id]);
        }

        // Bangladesh template: weekend = Friday + Saturday; Sunday–Thursday are school days.
        $defaults = [
            0 => ['is_open' => true,  'open_time' => '08:00', 'close_time' => '16:00'], // Sunday
            1 => ['is_open' => true,  'open_time' => '08:00', 'close_time' => '16:00'], // Monday
            2 => ['is_open' => true,  'open_time' => '08:00', 'close_time' => '16:00'], // Tuesday
            3 => ['is_open' => true,  'open_time' => '08:00', 'close_time' => '16:00'], // Wednesday
            4 => ['is_open' => true,  'open_time' => '08:00', 'close_time' => '16:00'], // Thursday
            5 => ['is_open' => false, 'open_time' => null,    'close_time' => null],    // Friday
            6 => ['is_open' => false, 'open_time' => null,    'close_time' => null],    // Saturday
        ];

        foreach ($defaults as $day => $hours) {
            SchoolOpeningHour::updateOrCreate(
                ['school_id' => $school->id, 'day_of_week' => $day],
                $hours,
            );
        }

        $this->seedBengaliTranslations($school);
    }

    /**
     * Bangla (bn) translations for every HasTranslations field the public
     * site + admin editor actually surface for School/SiteSetting — see
     * docs/modules/30-multilingual-content-plan.md Phase 4. Hand-written,
     * not run through the AI-suggest gateway: seed/demo data should be
     * correct and stable, not a live network call at seed time. Numeric
     * codes (institution_code/school_code/technical_branch_code) are left
     * untranslated on purpose — transOr() already falls back to the raw
     * value with no translation row needed, and translating a digit string
     * is meaningless.
     */
    private function seedBengaliTranslations(School $school): void
    {
        $school->setTranslation('name', 'bn', 'গ্রীন ভ্যালি মডেল স্কুল');
        $school->setTranslation('institution_code_label', 'bn', 'EIIN');
        $school->setTranslation('school_code_label', 'bn', 'কারিগরি শাখা কোড');
        $school->setTranslation('technical_branch_code_label', 'bn', 'বিদ্যালয় কোড');
        $school->setTranslation('address', 'bn', 'নাটুদহ, দামুড়হুদা, চুয়াডাঙ্গা');

        $settings = SiteSetting::forSchool($school->id);
        $settings->setTranslation('meta_title', 'bn', 'গ্রীন ভ্যালি মডেল স্কুল');
        $settings->setTranslation('meta_description', 'bn', '১৯৮৫ সাল থেকে কৌতূহলী মনের লালনকারী একটি ঐতিহ্যবাহী প্রতিষ্ঠান।');
    }
}
