<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\ActiveEnrollmentNotFoundException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Repositories\EnrollmentRepository;
use App\Repositories\LevelRepository;
use App\Support\ReviewSettings;
use Illuminate\Support\Collection;

final class StudentLevelResolver
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly LevelRepository $levels,
    ) {}

    public function resolveActiveEnrollment(Student $student): Enrollment
    {
        $enrollment = $this->enrollments->latestActiveEnrollment($student);

        if ($enrollment === null) {
            throw ActiveEnrollmentNotFoundException::forStudent();
        }

        return $enrollment;
    }

    public function resolveActiveCourse(Student $student): Course
    {
        return $this->resolveActiveEnrollment($student)->course;
    }

    public function resolveLevel(Student $student): Level
    {
        $level = $this->resolveActiveEnrollment($student)->course->level;

        if ($level === null) {
            throw ActiveEnrollmentNotFoundException::forStudent();
        }

        return $level;
    }

    /**
     * Like resolveLevel(), but returns null instead of throwing when the
     * student has no active enrollment.
     */
    public function findLevel(Student $student): ?Level
    {
        try {
            return $this->resolveLevel($student);
        } catch (ActiveEnrollmentNotFoundException) {
            return null;
        }
    }

    /**
     * Review settings of the student's active course.
     */
    public function resolveReviewSettings(Student $student): ReviewSettings
    {
        $course = $this->resolveActiveCourse($student);

        if ($course->level === null) {
            throw ActiveEnrollmentNotFoundException::forStudent();
        }

        return ReviewSettings::forCourse($course);
    }

    /**
     * Current level first, followed by the earlier levels of the same dance type
     * (closest first), so the review mixes new and older material. How many earlier
     * levels enter is set by the student's course.
     *
     * @return Collection<int, Level>
     */
    public function resolveReviewLevels(Student $student): Collection
    {
        return $this->reviewLevelsFor(
            $this->resolveLevel($student),
            $this->resolveReviewSettings($student)->previousLevelsCount,
        );
    }

    /**
     * Review levels of a session, with the settings of the course it was started from.
     *
     * @return Collection<int, Level>
     */
    public function reviewLevelsForSession(ReviewSession $session): Collection
    {
        $session->loadMissing('level');

        return $this->reviewLevelsFor($session->level, ReviewSettings::forSession($session)->previousLevelsCount);
    }

    /**
     * Ids of {@see self::reviewLevelsForSession()}, in the same order.
     *
     * @return list<int>
     */
    public function reviewLevelIdsForSession(ReviewSession $session): array
    {
        return $this->idsOf($this->reviewLevelsForSession($session));
    }

    /**
     * @param  int|null  $previousLevelsCount  Earlier levels to include; null includes all of them.
     * @return Collection<int, Level>
     */
    public function reviewLevelsFor(Level $currentLevel, ?int $previousLevelsCount = null): Collection
    {
        return collect([$currentLevel])
            ->concat($this->levels->previousLevelsOf($currentLevel, $previousLevelsCount))
            ->values();
    }

    /**
     * Ids of {@see self::reviewLevelsFor()}, in the same order.
     *
     * @return list<int>
     */
    public function reviewLevelIdsFor(Level $currentLevel, ?int $previousLevelsCount = null): array
    {
        return $this->idsOf($this->reviewLevelsFor($currentLevel, $previousLevelsCount));
    }

    /**
     * @param  Collection<int, Level>  $levels
     * @return list<int>
     */
    private function idsOf(Collection $levels): array
    {
        return $levels
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
