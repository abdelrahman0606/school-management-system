<?php

namespace App\Modules\Website\Services;

use App\Modules\Academic\Models\ClassRoutine;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Repositories\AnnouncementRepository;
use App\Modules\Examination\Models\Exam;
use App\Modules\Language\Models\Language;
use App\Modules\Mark\Models\ExamResult;
use App\Modules\Staff\Models\Staff;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentAcademic;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageRedirect;
use App\Modules\Website\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation for the public website's "dynamic blocks" — same
 * pattern Report already established: reads other modules' existing
 * data/repositories directly, never modifies them. Nothing here is cached
 * yet (the DevPlan suggests a long TTL — worth adding once this is proven
 * out, not required for a correct first pass).
 */
class PublicPortalService
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
        private readonly SiteLayoutService $siteLayouts,
    ) {}

    public function pageBySlug(int $schoolId, string $slug): ?Page
    {
        // No eager-loaded 'publishedLayout' here (there used to be one) — a
        // page can have one published row PER LOCALE now (docs/modules/
        // 30-multilingual-content-plan.md Phase 2), so "the" published
        // layout isn't a single relation to preload anymore.
        // PageRenderService::publishedLayoutFor() runs its own locale-scoped
        // query instead.
        return Page::forSchool($schoolId)
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    /** @return array{header: mixed, footer: mixed, settings: SiteSetting} */
    public function siteChrome(int $schoolId): array
    {
        return [
            'header' => $this->siteLayouts->published($schoolId, 'header'),
            'footer' => $this->siteLayouts->published($schoolId, 'footer'),
            'settings' => SiteSetting::forSchool($schoolId),
        ];
    }

    /** Follows a short redirect chain (old_slug -> new_slug -> new_slug -> ...), capped to avoid loops. */
    public function resolveRedirect(int $schoolId, string $slug): ?string
    {
        $current = $slug;
        $resolved = null;

        for ($hop = 0; $hop < 5; $hop++) {
            $redirect = PageRedirect::forSchool($schoolId)->where('old_slug', $current)->latest('created_at')->first();
            if (! $redirect) {
                break;
            }
            $resolved = $redirect->new_slug;
            $current = $redirect->new_slug;
        }

        return $resolved;
    }

    /**
     * docs/modules/30-multilingual-content-plan.md Phase 4/5 extension —
     * Announcement gained per-field translation (title/body) after the
     * public website's own multilingual plan was written; this is the one
     * place that content actually reaches a visitor, so it has to apply the
     * locale, not just the admin editor.
     *
     * @return Collection<int, Announcement>
     */
    public function notices(int $schoolId, string $locale): Collection
    {
        $items = $this->announcements->listVisible($schoolId, ['all']);

        if ($locale === Language::defaultCode()) {
            return $items;
        }

        // listVisible() is cache-aside (BaseRepository::remember()) — on the
        // array cache driver (tests, and any store that doesn't round-trip
        // through serialization) get() returns the SAME object instances
        // that are cached. Overwriting ->title/->body in place would leak
        // this locale's translated text into every other locale's cache hit
        // for the rest of the cache entry's TTL. Clone first, mutate the
        // clone.
        return $items->map(function (Announcement $a) use ($locale) {
            $translated = clone $a;
            $translated->title = $a->transOr('title', $locale);
            $translated->body = $a->transOr('body', $locale);

            return $translated;
        });
    }

    /**
     * Same locale-application reasoning as notices() above, for Staff::name
     * and its designation's name (the only two fields the "staff" block's
     * Blade partial actually prints — see public/blocks/render.blade.php).
     * This query isn't cached, but the clone-before-mutate discipline is
     * kept anyway for consistency and so a future cache-aside wrap doesn't
     * silently reintroduce the leak notices() guards against.
     *
     * @param  array{designation_id?: int, department_id?: int}  $filters
     * @return Collection<int, Staff>
     */
    public function staffList(int $schoolId, array $filters, string $locale): Collection
    {
        $items = Staff::where('school_id', $schoolId)
            ->active()
            ->when($filters['designation_id'] ?? null, fn ($q, $id) => $q->where('designation_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->with(['designation', 'department'])
            ->orderBy('name')
            ->get();

        if ($locale === Language::defaultCode()) {
            return $items;
        }

        return $items->map(function (Staff $s) use ($locale) {
            $translated = clone $s;
            $translated->name = $s->transOr('name', $locale);
            if ($s->designation) {
                $designation = clone $s->designation;
                $designation->name = $s->designation->transOr('name', $locale);
                $translated->setRelation('designation', $designation);
            }

            return $translated;
        });
    }

    /** @return Collection<int, ClassRoutine> */
    public function classRoutine(int $schoolId, int $classId, int $sectionId): Collection
    {
        return ClassRoutine::where('school_id', $schoolId)
            ->forClass($classId, $sectionId)
            ->with(['subject', 'period', 'room'])
            ->get();
    }

    /** @return array{active_students: int, active_staff: int} */
    public function stats(int $schoolId): array
    {
        return [
            'active_students' => Student::where('school_id', $schoolId)->active()->count(),
            'active_staff' => Staff::where('school_id', $schoolId)->active()->count(),
        ];
    }

    /**
     * Public result lookup — a visitor identifies themselves by roll number
     * within the exam's own class/year (Exam already carries class_id +
     * academic_year_id), not a login. Only LOCKED results are ever exposed
     * (matches Mark's "no recompute-on-read for locked results" rule), and
     * only from a published exam.
     *
     * @return array<string, mixed>|null
     */
    public function checkResult(int $schoolId, int $examId, string $rollNumber): ?array
    {
        $exam = Exam::where('school_id', $schoolId)->where('id', $examId)->published()->first();
        if (! $exam) {
            return null;
        }

        $academic = StudentAcademic::where('school_id', $schoolId)
            ->where('class_id', $exam->class_id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('roll_number', $rollNumber)
            ->first();
        if (! $academic) {
            return null;
        }

        $result = ExamResult::forSchool($schoolId)
            ->where('exam_id', $examId)
            ->where('student_id', $academic->student_id)
            ->where('is_locked', true)
            ->first();
        if (! $result) {
            return null;
        }

        return [
            'total_marks' => $result->total_marks,
            'total_possible' => $result->total_possible,
            'percentage' => $result->percentage,
            'grade' => $result->grade,
            'gpa' => $result->gpa,
            'is_pass' => $result->is_pass,
            'merit_position' => $result->merit_position,
        ];
    }
}
