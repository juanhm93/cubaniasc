<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\InvalidFigureSelectionException;
use App\Models\Course;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use App\Repositories\Review\ReviewFigureRepository;
use App\Support\ReviewSettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class FigureSelectionService
{
    /**
     * Minimum options taken from the student's current level (when it has enough figures),
     * capped by the number of options offered.
     */
    public const MIN_CURRENT_LEVEL_OPTIONS = 2;

    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
        private readonly ReviewFigureRepository $figures,
    ) {}

    /**
     * Options offered in a session. They are persisted the first time so a reload shows
     * the same figures and the selection is validated against what the student saw.
     *
     * @return Collection<int, LevelContent>
     */
    public function optionsForSession(ReviewSession $session, Student $student): Collection
    {
        $offered = $this->figures->offeredInSession($session);

        if ($offered->isNotEmpty()) {
            return $offered;
        }

        $session->loadMissing(['level', 'course']);
        $options = $this->getSelectableFigures(
            $student,
            $session->level,
            $session->course,
            ReviewSettings::forSession($session),
        );

        $this->figures->offerInSession($session, $this->idsOf($options));

        return $options;
    }

    /**
     * Saves the student's choice among the options offered in the session (offering them
     * first if that did not happen yet) and returns the chosen figures.
     *
     * @param  list<int>  $levelContentIds
     * @return Collection<int, LevelContent>
     */
    public function selectForSession(ReviewSession $session, Student $student, array $levelContentIds): Collection
    {
        $this->optionsForSession($session, $student);
        $this->storeSelectedFigures($session, $levelContentIds);

        return $this->selectedFigures($session);
    }

    /**
     * @return Collection<int, LevelContent>
     */
    public function selectedFigures(ReviewSession $session): Collection
    {
        return $this->figures->selectedInSession($session);
    }

    public function hasSelectedFigures(ReviewSession $session): bool
    {
        return $this->figures->sessionHasSelectedFigures($session);
    }

    public function isSelected(ReviewSession $session, LevelContent $figure): bool
    {
        return $this->figures->isSelectedInSession($session, $figure);
    }

    /**
     * Recent views first, then the course progress, then random catalog figures. As many
     * options as the settings offer; at least {@see self::MIN_CURRENT_LEVEL_OPTIONS} belong to
     * the current level when possible, the rest can come from the earlier levels of the same
     * dance type that the settings include. When the settings offer figures by level, each
     * review level gets its own options instead ({@see self::selectableFiguresByLevel()}).
     * The course and its settings default to the student's active course.
     *
     * @return Collection<int, LevelContent>
     */
    public function getSelectableFigures(
        Student $student,
        Level $level,
        ?Course $course = null,
        ?ReviewSettings $settings = null,
    ): Collection {
        $course ??= $this->levelResolver->resolveActiveCourse($student);
        $settings ??= ReviewSettings::forCourse($course, $level);
        $limit = $settings->figureOptionsCount;

        if ($settings->figuresByLevel) {
            return $this->selectableFiguresByLevel(
                $student,
                $course,
                $this->levelResolver->reviewLevelsFor($level, $settings->previousLevelsCount),
                $limit,
            );
        }

        $reviewLevelIds = $this->levelResolver->reviewLevelIdsFor($level, $settings->previousLevelsCount);

        $candidates = $this->figures->recentlyViewedBy($student, $reviewLevelIds, $limit);

        $candidates = $candidates->concat(
            $this->figures->coveredInCourse(
                $course,
                $level,
                $limit,
                $this->idsOf($candidates),
            )
        );

        $candidates = $candidates->concat(
            $this->figuresFromLevelCatalog([$level->id], $limit, $this->idsOf($candidates))
        )->unique('id')->values();

        $currentLevelFigures = $candidates
            ->where('level_id', $level->id)
            ->take(min(self::MIN_CURRENT_LEVEL_OPTIONS, $limit));

        $figures = $currentLevelFigures
            ->concat($candidates->reject(fn (LevelContent $figure): bool => $currentLevelFigures->contains('id', $figure->id)))
            ->take($limit);

        if ($figures->count() < $limit) {
            $figures = $figures->concat($this->figuresFromLevelCatalog(
                array_values(array_diff($reviewLevelIds, [$level->id])),
                $limit - $figures->count(),
                $this->idsOf($figures),
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

        $allowedLevelContentIds ??= $this->figures->offeredIdsInSession($session);
        $expectedCount = $this->requiredSelectionCount(
            ReviewSettings::forSession($session)->figureSelectionCount,
            count($allowedLevelContentIds),
        );

        if (count($levelContentIds) !== $expectedCount) {
            throw InvalidFigureSelectionException::invalidCount($expectedCount);
        }

        if ($allowedLevelContentIds !== [] && array_diff($levelContentIds, $allowedLevelContentIds) !== []) {
            throw InvalidFigureSelectionException::notAllowed();
        }

        $reviewLevelIds = $this->levelResolver->reviewLevelIdsForSession($session);

        if ($this->figures->countInLevels($reviewLevelIds, $levelContentIds) !== $expectedCount) {
            throw InvalidFigureSelectionException::notAllowed();
        }

        $this->figures->selectInSession($session, $levelContentIds);
    }

    /**
     * The figures the settings ask for, or fewer when the catalog offered fewer options
     * (a level that is still being loaded). With no known options the full count is required.
     */
    public function requiredSelectionCount(int $selectionCount, int $offeredCount): int
    {
        return $offeredCount === 0 ? $selectionCount : min($selectionCount, $offeredCount);
    }

    /**
     * A session can be finished once the student chose figures, or when the catalog had
     * none to offer.
     */
    public function canComplete(ReviewSession $session): bool
    {
        return $this->hasSelectedFigures($session) || ! $this->figures->sessionHasOfferedFigures($session);
    }

    public function recordView(Student $student, LevelContent $levelContent): StudentFigureView
    {
        return $this->figures->recordView($student, $levelContent, now());
    }

    /**
     * Up to `$limit` figures of every review level, grouped by level in review order (current
     * level first). Within a level: recent views, then the course progress, then the catalog.
     *
     * @param  Collection<int, Level>  $levels
     * @return Collection<int, LevelContent>
     */
    private function selectableFiguresByLevel(Student $student, Course $course, Collection $levels, int $limit): Collection
    {
        $figures = collect();

        foreach ($levels as $reviewLevel) {
            $levelFigures = $this->figures->recentlyViewedBy($student, [$reviewLevel->id], $limit);

            $levelFigures = $levelFigures->concat($this->figures->coveredInCourse(
                $course,
                $reviewLevel,
                $limit - $levelFigures->count(),
                $this->idsOf($levelFigures),
            ));

            $levelFigures = $levelFigures->concat($this->figures->randomFromLevel(
                $reviewLevel->id,
                $limit - $levelFigures->count(),
                $this->idsOf($levelFigures),
            ));

            $figures = $figures->concat($levelFigures->unique('id')->take($limit));
        }

        return (new EloquentCollection($figures->values()->all()))->loadMissing('level');
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

            $figures = $figures->concat($this->figures->randomFromLevel(
                $levelId,
                $limit - $figures->count(),
                [...$excludeIds, ...$this->idsOf($figures)],
            ));
        }

        return $figures->values();
    }

    /**
     * @param  Collection<int, LevelContent>  $figures
     * @return list<int>
     */
    private function idsOf(Collection $figures): array
    {
        return $figures->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
