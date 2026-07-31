<?php

namespace Tests\Feature\Public;

use App\Modules\School\Models\School;
use App\Modules\Website\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public layout's "Advanced Theme" wiring — docs/modules/29-frontend-modernization-proposal.md
 * Phase 1. Every SiteSetting column exercised here previously existed in the
 * schema but was never read by public/layout.blade.php; these assert it now
 * is, AND that an unconfigured school still renders with the original
 * hardcoded defaults (no regression for the common case).
 */
class PublicThemingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create([
            'name' => 'Theme Test School', 'is_active' => true, 'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka', 'locale' => 'en', 'academic_year_pattern' => 'jan_dec',
        ]);
    }

    public function test_unconfigured_school_renders_original_hardcoded_defaults(): void
    {
        SiteSetting::create(['school_id' => $this->school->id]);

        $this->get('/')
            ->assertOk()
            ->assertSee('--ink:', false)
            ->assertSee('#1f2937', false) // default body text color, unchanged
            ->assertSee('background: #0f172a', false) // default footer background, unchanged
            ->assertDontSee('fonts.googleapis.com', false); // no font picked -> no external request
    }

    public function test_configured_school_renders_its_advanced_theme_values(): void
    {
        SiteSetting::create([
            'school_id' => $this->school->id,
            'secondary_color' => '#123123',
            'text_color' => '#abcabc',
            'surface_color' => '#fedcba',
            'border_color' => '#111222',
            'font_heading' => 'Poppins',
            'font_body' => 'Inter',
            'base_font_size' => 18,
            'container_width' => 1200,
            'btn_radius' => 3,
            'btn_filled_json' => ['bg' => '#ff00ff', 'text' => '#00ff00'],
        ]);

        $response = $this->get('/')->assertOk();
        $response
            ->assertSee('#abcabc', false)                       // text_color -> --ink
            ->assertSee('background: #123123', false)            // secondary_color -> footer background
            ->assertSee('#fedcba', false)                        // surface_color -> --surface
            ->assertSee('#111222', false)                        // border_color -> --border
            ->assertSee('family=Poppins', false)                  // Google Fonts request built
            ->assertSee('family=Inter', false)
            ->assertSee('font-size: 18px', false)
            ->assertSee('max-width: 1200px', false)
            ->assertSee('border-radius: 3px', false)
            ->assertSee('background: #ff00ff', false)             // btn_filled_json -> .btn-brand
            ->assertSee('color: #00ff00', false);
    }

    public function test_font_outside_allow_list_is_ignored_even_if_stored_directly(): void
    {
        // Simulates a row written before the allow-list existed, or a direct
        // DB edit bypassing the admin form's validation entirely — the
        // render path re-validates independently rather than trusting the
        // column (see layout.blade.php's $fontHeading computation).
        SiteSetting::create([
            'school_id' => $this->school->id,
            'font_heading' => '"; } body { background: red; } .x {',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('background: red', false)
            ->assertDontSee('fonts.googleapis.com', false);
    }
}
