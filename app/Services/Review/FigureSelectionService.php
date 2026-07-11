<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\InvalidFigureSelectionException;
use App\Models\CourseLevelContentProgress;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use Illuminate\Support\Collection;

final class FigureSelectionService
{
    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    /**
     * @return Collection<int, LevelContent>
     */
    public function getSelectableFigures(Student $student, Level $level, int $limit = 4): Collection
    {
        $figures = $this->figuresFromRecentViews($student, $level, $limit);

        if ($figures->count() < $limit) {
            $figures = $figures->merge(
                $this->figuresFromCourseProgress($student, $level, $limit - $figures->count(), $figures->pluck('id')->all())
            );
        }

        if ($figures->count() < $limit) {
            $figures = $figures->merge(
                $this->figuresFromLevelCatalog($level, $limit - $figures->count(), $figures->pluck('id')->all())
            );
        }

        return $figures->unique('id')->take($limit)->values();
    }

    /**
     * @param  list<int>  $levelContentIds
     * @param  list<int>|null  $allowedLevelContentIds
     */
    public function storeSelectedFigures(
        ReviewSession $session,
        array $levelContentIds,
        ?array $allowedLevelContentIds = null,
    ): void {
        $levelContentIds = array_values(array_unique($levelContentIds));

        if (count($levelContentIds) !== 2) {
            throw InvalidFigureSelectionException::invalidCount(2);
        }

        if ($allowedLevelContentIds !== null && array_diff($levelContentIds, $allowedLevelContentIds) !== []) {
            throw InvalidFigureSelectionException::notAllowed();
        }

        $validCount = LevelContent::query()
            ->where('level_id', $session->level_id)
            ->whereIn('id', $levelContentIds)
            ->count();

        if ($validCount !== 2) {
            throw InvalidFigureSelectionException::notAllowed();
        }

        $payload = [];

        foreach ($levelContentIds as $levelContentId) {
            $payload[$levelContentId] = ['selected_by_student' => true];
        }

        $session->figures()->syncWithoutDetaching($payload);
    }

    public function recordView(Student $student, LevelContent $levelContent): StudentFigureView
    {
        return StudentFigureView::query()->create([
            'student_id' => $student->id,
            'level_content_id' => $levelContent->id,
            'viewed_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, LevelContent>
     */
    private function figuresFromRecentViews(Student $student, Level $level, int $limit): Collection
    {
        return StudentFigureView::query()
            ->where('student_id', $student->id)
            ->whereHas('levelContent', fn ($query) => $query->where('level_id', $level->id))
            ->with('levelContent')
            ->orderByDesc('viewed_at')
            ->get()
            ->unique('level_content_id')
            ->take($limit)
            ->pluck('levelContent')
            ->filter()
            ->values();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, LevelContent>
     */
    private function figuresFromCourseProgress(
        Student $student,
        Level $level,
        int $limit,
        array $excludeIds,
    ): Collection {
        if ($limit <= 0) {
            return collect();
        }

        $course = $this->levelResolver->resolveActiveCourse($student);

        return CourseLevelContentProgress::query()
            ->where('course_id', $course->id)
            ->whereHas('levelContent', fn ($query) => $query->where('level_id', $level->id))
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('level_content_id', $excludeIds))
            ->with('levelContent')
            ->orderByDesc('completed_at')
            ->get()
            ->unique('level_content_id')
            ->take($limit)
            ->pluck('levelContent')
            ->filter()
            ->values();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, LevelContent>
     */
    private function figuresFromLevelCatalog(Level $level, int $limit, array $excludeIds): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return LevelContent::query()
            ->where('level_id', $level->id)
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}
