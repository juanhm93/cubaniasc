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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class FigureSelectionService
{
    public const OPTIONS_COUNT = 4;

    public const SELECTION_COUNT = 2;

    /**
     * Minimum options taken from the student's current level (when it has enough figures).
     */
    public const MIN_CURRENT_LEVEL_OPTIONS = 2;

    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    /**
     * Options offered in a session. They are persisted the first time so a reload shows
     * the same figures and the selection is validated against what the student saw.
     *
     * @return Collection<int, LevelContent>
     */
    public function optionsForSession(ReviewSession $session, Student $student): Collection
    {
        $offered = $session->figures()->with('level')->orderBy('review_session_figures.id')->get();

        if ($offered->isNotEmpty()) {
            return $offered;
        }

        $session->loadMissing('level');
        $options = $this->getSelectableFigures($student, $session->level);

        $session->figures()->syncWithoutDetaching(
            $options->mapWithKeys(fn (LevelContent $figure): array => [
                $figure->id => ['selected_by_student' => false],
            ])->all(),
        );

        return $options;
    }

    /**
     * @return Collection<int, LevelContent>
     */
    public function selectedFigures(ReviewSession $session): Collection
    {
        return $session->figures()
            ->wherePivot('selected_by_student', true)
            ->with('level')
            ->orderBy('review_session_figures.id')
            ->get();
    }

    public function hasSelectedFigures(ReviewSession $session): bool
    {
        return $session->figures()->wherePivot('selected_by_student', true)->exists();
    }

    /**
     * Recent views first, then the course progress, then random catalog figures. At least
     * {@see self::MIN_CURRENT_LEVEL_OPTIONS} options belong to the current level when possible;
     * the rest can come from earlier levels of the same dance type.
     *
     * @return Collection<int, LevelContent>
     */
    public function getSelectableFigures(Student $student, Level $level, int $limit = self::OPTIONS_COUNT): Collection
    {
        $reviewLevelIds = $this->levelResolver->reviewLevelsFor($level)->pluck('id')->all();

        $candidates = $this->figuresFromRecentViews($student, $reviewLevelIds, $limit);

        $candidates = $candidates->concat(
            $this->figuresFromCourseProgress($student, $level, $limit, $candidates->pluck('id')->all())
        );

        $candidates = $candidates->concat(
            $this->figuresFromLevelCatalog([$level->id], $limit, $candidates->pluck('id')->all())
        )->unique('id')->values();

        $currentLevelFigures = $candidates
            ->where('level_id', $level->id)
            ->take(self::MIN_CURRENT_LEVEL_OPTIONS);

        $figures = $currentLevelFigures
            ->concat($candidates->reject(fn (LevelContent $figure): bool => $currentLevelFigures->contains('id', $figure->id)))
            ->take($limit);

        if ($figures->count() < $limit) {
            $figures = $figures->concat($this->figuresFromLevelCatalog(
                array_values(array_diff($reviewLevelIds, [$level->id])),
                $limit - $figures->count(),
                $figures->pluck('id')->all(),
            ));
        }

        $priority = $candidates->pluck('id')->flip();

        $sorted = $figures
            ->sortBy(fn (LevelContent $figure): int => $priority->get($figure->id, PHP_INT_MAX))
            ->values()
            ->all();

        return (new EloquentCollection($sorted))->loadMissing('level');
    }

    /**
     * @param  list<int>  $levelContentIds
     * @param  list<int>|null  $allowedLevelContentIds  Defaults to the options offered in the session.
     */
    public function storeSelectedFigures(
        ReviewSession $session,
        array $levelContentIds,
        ?array $allowedLevelContentIds = null,
    ): void {
        $levelContentIds = array_values(array_unique($levelContentIds));

        if ($this->hasSelectedFigures($session)) {
            throw InvalidFigureSelectionException::alreadySelected();
        }

        $allowedLevelContentIds ??= $session->figures()->pluck('level_contents.id')->all();
        $expectedCount = $this->requiredSelectionCount(count($allowedLevelContentIds));

        if (count($levelContentIds) !== $expectedCount) {
            throw InvalidFigureSelectionException::invalidCount($expectedCount);
        }

        if ($allowedLevelContentIds !== [] && array_diff($levelContentIds, $allowedLevelContentIds) !== []) {
            throw InvalidFigureSelectionException::notAllowed();
        }

        $session->loadMissing('level');
        $reviewLevelIds = $this->levelResolver->reviewLevelsFor($session->level)->pluck('id')->all();

        $validCount = LevelContent::query()
            ->whereIn('level_id', $reviewLevelIds)
            ->whereIn('id', $levelContentIds)
            ->count();

        if ($validCount !== $expectedCount) {
            throw InvalidFigureSelectionException::notAllowed();
        }

        $payload = [];

        foreach ($levelContentIds as $levelContentId) {
            $payload[$levelContentId] = ['selected_by_student' => true];
        }

        $session->figures()->syncWithoutDetaching($payload);
    }

    /**
     * Two figures, or fewer when the catalog offered fewer options (a level that is still
     * being loaded). With no known options the full count is required.
     */
    public function requiredSelectionCount(int $offeredCount): int
    {
        return $offeredCount === 0 ? self::SELECTION_COUNT : min(self::SELECTION_COUNT, $offeredCount);
    }

    /**
     * A session can be finished once the student chose figures, or when the catalog had
     * none to offer.
     */
    public function canComplete(ReviewSession $session): bool
    {
        return $this->hasSelectedFigures($session) || $session->figures()->doesntExist();
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
     * @param  list<int>  $levelIds
     * @return Collection<int, LevelContent>
     */
    private function figuresFromRecentViews(Student $student, array $levelIds, int $limit): Collection
    {
        return StudentFigureView::query()
            ->where('student_id', $student->id)
            ->whereHas('levelContent', fn ($query) => $query->whereIn('level_id', $levelIds))
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
     * @param  list<int>  $levelIds  Ordered by priority; earlier levels are used first.
     * @param  list<int>  $excludeIds
     * @return Collection<int, LevelContent>
     */
    private function figuresFromLevelCatalog(array $levelIds, int $limit, array $excludeIds): Collection
    {
        $figures = collect();

        foreach ($levelIds as $levelId) {
            if ($figures->count() >= $limit) {
                break;
            }

            $figures = $figures->concat(
                LevelContent::query()
                    ->where('level_id', $levelId)
                    ->whereNotIn('id', [...$excludeIds, ...$figures->pluck('id')->all()])
                    ->inRandomOrder()
                    ->limit($limit - $figures->count())
                    ->get()
            );
        }

        return $figures->values();
    }
}
