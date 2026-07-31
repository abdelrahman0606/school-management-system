<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\School\Models\School;
use App\Modules\Staff\Models\Department;
use App\Modules\Staff\Models\Designation;
use App\Modules\Staff\Models\Staff;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Per-field translation for Announcement (title + body) and Staff/
 * Designation/Department (name) — same HasTranslations/content_translations
 * pattern as School/SiteSetting (docs/modules/30-multilingual-content-plan.md
 * Phases 4 + 5), just for per-row models with a modal-per-row admin UI
 * instead of a singleton settings page.
 */
class MultilingualAnnouncementAndStaffContentTest extends TestCase
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

    // ── Announcement ─────────────────────────────────────────────────────

    public function test_admin_can_save_a_bengali_translation_for_an_announcement(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'created_by' => $this->admin->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false,
        ]);

        $this->actingAs($this->admin)->put("/admin/announcements/{$announcement->id}", [
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'translations' => ['bn' => ['title' => 'পরীক্ষার সময়সূচী', 'body' => 'পরীক্ষা সোমবার শুরু হবে।']],
        ])->assertRedirect();

        $announcement->refresh();
        $this->assertSame('পরীক্ষার সময়সূচী', $announcement->trans('title', 'bn'));
        $this->assertSame('পরীক্ষা সোমবার শুরু হবে।', $announcement->trans('body', 'bn'));
        // Default-locale columns are untouched.
        $this->assertSame('Exam Schedule', $announcement->title);
    }

    public function test_admin_can_request_ai_suggested_translation_for_an_announcement(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'created_by' => $this->admin->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false,
        ]);
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post("/admin/announcements/{$announcement->id}/translations/suggest", ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame('অনুবাদিত', $announcement->trans('title', 'bn'));
        $this->assertSame('অনুবাদিত', $announcement->trans('body', 'bn'));
    }

    public function test_ai_suggestion_never_overwrites_an_announcement_field_already_translated(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'created_by' => $this->admin->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false,
        ]);
        $announcement->setTranslation('title', 'bn', 'হাতে অনুবাদ করা শিরোনাম');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post("/admin/announcements/{$announcement->id}/translations/suggest", ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame('হাতে অনুবাদ করা শিরোনাম', $announcement->trans('title', 'bn'));
        // The untouched field still gets filled.
        $this->assertSame('অনুবাদিত', $announcement->trans('body', 'bn'));
    }

    /**
     * Announcement is edited in a modal, not a dedicated page — a plain
     * form POST+redirect would close it, forcing the admin to reopen Edit
     * just to see what the AI filled in. The button's JS instead fetch()es
     * with X-Requested-With so the field can be filled in place with no
     * navigation at all (see admin/partials/translation-suggest-script.blade.php).
     */
    public function test_ai_suggestion_returns_json_instead_of_redirecting_for_an_ajax_request(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'created_by' => $this->admin->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false,
        ]);
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post(
                "/admin/announcements/{$announcement->id}/translations/suggest",
                ['locale' => 'bn'],
                ['X-Requested-With' => 'XMLHttpRequest'],
            )
            ->assertOk()
            ->assertJson(['fields' => ['title' => 'অনুবাদিত', 'body' => 'অনুবাদিত']]);
    }

    // ── Staff ────────────────────────────────────────────────────────────

    public function test_admin_can_save_a_bengali_translation_for_a_staff_members_name(): void
    {
        $staff = Staff::create([
            'school_id' => $this->school->id, 'employee_id' => 'EMP1',
            'name' => 'John Doe', 'gender' => 'male', 'status' => 'active', 'is_trash' => false,
        ]);

        $this->actingAs($this->admin)->put("/admin/staff/{$staff->id}", [
            'name' => 'John Doe',
            'translations' => ['bn' => ['name' => 'জন ডো']],
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame('জন ডো', $staff->trans('name', 'bn'));
        $this->assertSame('John Doe', $staff->name);
    }

    public function test_admin_can_request_ai_suggested_translation_for_a_staff_members_name(): void
    {
        $staff = Staff::create([
            'school_id' => $this->school->id, 'employee_id' => 'EMP1',
            'name' => 'John Doe', 'gender' => 'male', 'status' => 'active', 'is_trash' => false,
        ]);
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post("/admin/staff/{$staff->id}/translations/suggest", ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame('অনুবাদিত', $staff->trans('name', 'bn'));
    }

    public function test_staff_ai_suggestion_returns_json_instead_of_redirecting_for_an_ajax_request(): void
    {
        $staff = Staff::create([
            'school_id' => $this->school->id, 'employee_id' => 'EMP1',
            'name' => 'John Doe', 'gender' => 'male', 'status' => 'active', 'is_trash' => false,
        ]);
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post(
                "/admin/staff/{$staff->id}/translations/suggest",
                ['locale' => 'bn'],
                ['X-Requested-With' => 'XMLHttpRequest'],
            )
            ->assertOk()
            ->assertJson(['fields' => ['name' => 'অনুবাদিত']]);
    }

    // ── Designation / Department ────────────────────────────────────────

    public function test_admin_can_save_a_bengali_translation_for_a_designation(): void
    {
        $designation = Designation::create(['school_id' => $this->school->id, 'name' => 'Principal']);

        $this->actingAs($this->admin)->put("/admin/designations/{$designation->id}", [
            'name' => 'Principal',
            'translations' => ['bn' => ['name' => 'অধ্যক্ষ']],
        ])->assertRedirect();

        $designation->refresh();
        $this->assertSame('অধ্যক্ষ', $designation->trans('name', 'bn'));
        $this->assertSame('Principal', $designation->name);
    }

    public function test_admin_can_request_ai_suggested_translation_for_a_department(): void
    {
        $department = Department::create(['school_id' => $this->school->id, 'name' => 'Science']);
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post("/admin/departments/{$department->id}/translations/suggest", ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame('অনুবাদিত', $department->trans('name', 'bn'));
    }

    public function test_designation_ai_suggestion_returns_json_instead_of_redirecting_for_an_ajax_request(): void
    {
        $designation = Designation::create(['school_id' => $this->school->id, 'name' => 'Principal']);
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post(
                "/admin/designations/{$designation->id}/translations/suggest",
                ['locale' => 'bn'],
                ['X-Requested-With' => 'XMLHttpRequest'],
            )
            ->assertOk()
            ->assertJson(['fields' => ['name' => 'অনুবাদিত']]);
    }

    public function test_ai_suggestion_never_overwrites_a_designation_already_translated(): void
    {
        $designation = Designation::create(['school_id' => $this->school->id, 'name' => 'Principal']);
        $designation->setTranslation('name', 'bn', 'হাতে অনুবাদ');
        $this->fakeMyMemory();

        $this->actingAs($this->admin)
            ->post("/admin/designations/{$designation->id}/translations/suggest", ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame('হাতে অনুবাদ', $designation->trans('name', 'bn'));
    }

    public function test_default_locale_is_a_no_op_for_announcement_suggest(): void
    {
        $announcement = Announcement::create([
            'school_id' => $this->school->id, 'created_by' => $this->admin->id,
            'title' => 'Exam Schedule', 'body' => 'Exams start Monday.',
            'type' => 'exam', 'audience' => 'all', 'priority' => 'normal',
            'is_pinned' => false, 'is_trash' => false,
        ]);
        Http::fake();

        $this->actingAs($this->admin)
            ->post("/admin/announcements/{$announcement->id}/translations/suggest", ['locale' => 'en'])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertNull($announcement->trans('title', 'en'));
    }
}
