<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Language\Models\Translation;
use App\Modules\School\Models\School;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Suggest translations (AI)" for the flat UI-string catalog (Settings ->
 * Languages -> Translations editor) — docs/modules/30-multilingual-content-plan.md
 * Phase 5's existing per-model AI-suggest pattern, extended to the general
 * __() catalog. Unlike the per-model jobs (a fixed field list per model),
 * this operates over an explicit list of Translation row ids — exactly the
 * ids the Save Translations form already submits, i.e. one paginated page's
 * worth (<=50), never the whole ~2,200-row catalog in one request.
 */
class UiTranslationSuggestTest extends TestCase
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

    private function row(string $key, ?string $value = null): Translation
    {
        return Translation::create(['locale' => 'bn', 'key' => $key, 'value' => $value]);
    }

    public function test_admin_can_request_ai_suggested_translations_for_the_current_page(): void
    {
        $save = $this->row('Save');
        $cancel = $this->row('Cancel');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->put('/admin/languages/bn/translations/suggest', ['t' => [$save->id => '', $cancel->id => '']])
            ->assertRedirect();

        $this->assertSame('অনুবাদিত', $save->fresh()->value);
        $this->assertSame('অনুবাদিত', $cancel->fresh()->value);
    }

    public function test_never_overwrites_a_row_already_translated(): void
    {
        $save = $this->row('Save', 'সংরক্ষণ');
        $cancel = $this->row('Cancel');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->put('/admin/languages/bn/translations/suggest', ['t' => [$save->id => 'সংরক্ষণ', $cancel->id => '']])
            ->assertRedirect();

        $this->assertSame('সংরক্ষণ', $save->fresh()->value); // untouched
        $this->assertSame('অনুবাদিত', $cancel->fresh()->value); // the empty one still gets filled
    }

    /**
     * "Suggest" is a strict superset of "Save": clicking it must not
     * silently discard an in-progress edit sitting in the form that hasn't
     * been saved yet.
     */
    public function test_manually_typed_values_in_the_same_submission_are_saved_not_discarded(): void
    {
        $save = $this->row('Save');
        $cancel = $this->row('Cancel');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->put('/admin/languages/bn/translations/suggest', [
                't' => [$save->id => 'হাতে টাইপ করা মান', $cancel->id => ''],
            ])
            ->assertRedirect();

        // The hand-typed value was saved as-is, not sent through the gateway.
        $this->assertSame('হাতে টাইপ করা মান', $save->fresh()->value);
        // The empty field still got an AI draft.
        $this->assertSame('অনুবাদিত', $cancel->fresh()->value);
    }

    public function test_only_operates_on_the_submitted_ids_not_the_whole_catalog(): void
    {
        $onPage = $this->row('Save');
        $notOnPage = $this->row('A Very Different String Elsewhere');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->put('/admin/languages/bn/translations/suggest', ['t' => [$onPage->id => '']])
            ->assertRedirect();

        $this->assertSame('অনুবাদিত', $onPage->fresh()->value);
        $this->assertNull($notOnPage->fresh()->value);
    }

    public function test_no_rows_submitted_is_a_graceful_no_op(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->put('/admin/languages/bn/translations/suggest', [])
            ->assertRedirect();

        Http::assertNothingSent();
    }
}
