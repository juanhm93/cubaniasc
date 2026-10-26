<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\ActiveEnrollmentNotFoundException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use App\Repositories\EnrollmentRepository;
use App\Repositories\LevelRepository;
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
     * Current level first, followed by the earlier levels of the same dance type
     * (closest first), so the review mixes new and older material.
     *
     * @return Collection<int, Level>
     */
    public function resolveReviewLevels(Student $student): Collection
    {
        return $this->reviewLevelsFor($this->resolveLevel($student));
    }

    /**
     * @return Collection<int, Level>
     */
    public function reviewLevelsFor(Level $currentLevel): Collection
    {
        return collect([$currentLevel])
            ->concat($this->levels->previousLevelsOf($currentLevel))
            ->values();
    }

    /**
     * Ids of {@see self::reviewLevelsFor()}, in the same order.
     *
     * @return list<int>
     */
    public function reviewLevelIdsFor(Level $currentLevel): array
    {
        return $this->reviewLevelsFor($currentLevel)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
