<?php

declare(strict_types=1);

namespace App\Repositories\Review;

use App\Models\Course;
use App\Models\CourseLevelContentProgress;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Queries on the figures (`level_contents`) used by the review: the candidate sources
 * (recent views, course progress, catalog), the figures offered and chosen in a session
 * (`review_session_figures`) and the view history (`student_figure_views`).
 */
final class ReviewFigureRepository
{
    /**
     * Figures the student viewed most recently within the given levels, newest first.
     *
     * @param  list<int>  $levelIds
     * @return Collection<int, LevelContent>
     */
    public function recentlyViewedBy(Student $student, array $levelIds, int $limit): Collection
    {
        return StudentFigureView::query()
            ->where('student_id', $student->id)
            ->whereHas('levelContent', fn (Builder $query) => $query->whereIn('level_id', $levelIds))
            ->with('levelContent')
            ->orderByDesc('viewed_at')
            ->limit($limit * 10)
            ->get()
            ->unique('level_content_id')
            ->take($limit)
            ->pluck('levelContent')
            ->filter()
            ->values();
    }

    /**
     * Figures of the level that the teacher marked as covered in the course, latest first.
     *
     * @param  list<int>  $excludeIds
     * @return Collection<int, LevelContent>
     */
    public function coveredInCourse(Course $course, Level $level, int $limit, array $excludeIds = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return CourseLevelContentProgress::query()
            ->where('course_id', $course->id)
            ->whereHas('levelContent', fn (Builder $query) => $query->where('level_id', $level->id))
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('level_content_id', $excludeIds))
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
     * @return EloquentCollection<int, LevelContent>
     */
    public function randomFromLevel(int $levelId, int $limit, array $excludeIds = []): EloquentCollection
    {
        if ($limit <= 0) {
            return new EloquentCollection;
        }

        return LevelContent::query()
            ->where('level_id', $levelId)
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludeIds))
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * How many of the given figures belong to the given levels.
     *
     * @param  list<int>  $levelIds
     * @param  list<int>  $levelContentIds
     */
    public function countInLevels(array $levelIds, array $levelContentIds): int
    {
        return LevelContent::query()
            ->whereIn('level_id', $levelIds)
            ->whereIn('id', $levelContentIds)
            ->count();
    }

    public function recordView(Student $student, LevelContent $figure, CarbonInterface $viewedAt): StudentFigureView
    {
        return StudentFigureView::query()->create([
            'student_id' => $student->id,
            'level_content_id' => $figure->id,
            'viewed_at' => $viewedAt,
        ]);
    }

    /**
     * Deletes the views recorded in the range, for one student or for everyone.
     *
     * @return int Number of views deleted.
     */
    public function deleteViewsBetween(CarbonInterface $from, CarbonInterface $to, ?int $studentId = null): int
    {
        return StudentFigureView::query()
            ->when($studentId !== null, fn (Builder $query): Builder => $query->where('student_id', $studentId))
            ->whereBetween('viewed_at', [$from, $to])
            ->delete();
    }

    /**
     * Figures offered in the session, in the order they were offered.
     *
     * @return EloquentCollection<int, LevelContent>
     */
    public function offeredInSession(ReviewSession $session): EloquentCollection
    {
        return $this->orderedSessionFigures($session->figures())->get();
    }

    /**
     * @return EloquentCollection<int, LevelContent>
     */
    public function selectedInSession(ReviewSession $session): EloquentCollection
    {
        return $this->orderedSessionFigures($session->selectedFigures())->get();
    }

    /**
     * @return list<int>
     */
    public function offeredIdsInSession(ReviewSession $session): array
    {
        return $session->figures()
            ->pluck('level_contents.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function sessionHasOfferedFigures(ReviewSession $session): bool
    {
        return $session->figures()->exists();
    }

    public function sessionHasSelectedFigures(ReviewSession $session): bool
    {
        return $session->selectedFigures()->exists();
    }

    public function isSelectedInSession(ReviewSession $session, LevelContent $figure): bool
    {
        return $session->selectedFigures()->whereKey($figure->id)->exists();
    }

    /**
     * Stores the figures shown to the student as options (not chosen yet).
     *
     * @param  list<int>  $levelContentIds
     */
    public function offerInSession(ReviewSession $session, array $levelContentIds): void
    {
        $this->syncSessionFigures($session, $levelContentIds, selectedByStudent: false);
    }

    /**
     * @param  list<int>  $levelContentIds
     */
    public function selectInSession(ReviewSession $session, array $levelContentIds): void
    {
        $this->syncSessionFigures($session, $levelContentIds, selectedByStudent: true);
    }

    /**
     * @param  list<int>  $levelContentIds
     */
    private function syncSessionFigures(ReviewSession $session, array $levelContentIds, bool $selectedByStudent): void
    {
        if ($levelContentIds === []) {
            return;
        }

        $payload = [];

        foreach ($levelContentIds as $levelContentId) {
            $payload[$levelContentId] = ['selected_by_student' => $selectedByStudent];
        }

        $session->figures()->syncWithoutDetaching($payload);
    }

    /**
     * @param  BelongsToMany<LevelContent, ReviewSession>  $relation
     * @return BelongsToMany<LevelContent, ReviewSession>
     */
    private function orderedSessionFigures(BelongsToMany $relation): BelongsToMany
    {
        return $relation->with('level')->orderBy('review_session_figures.id');
    }
}
