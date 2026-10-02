<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Review\ActiveEnrollmentNotFoundException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use Illuminate\Support\Collection;

final class StudentLevelResolver
{
    public function resolveActiveEnrollment(Student $student): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('course', fn ($query) => $query->where('is_active', true))
            ->with(['course.level'])
            ->orderByDesc('enrolled_at')
            ->first();

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
        $previousLevels = Level::query()
            ->where('dance_type_id', $currentLevel->dance_type_id)
            ->where('sort_order', '<', $currentLevel->sort_order)
            ->orderByDesc('sort_order')
            ->get();

        return collect([$currentLevel])->concat($previousLevels)->values();
    }
}
