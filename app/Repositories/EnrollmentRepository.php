<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queries on `enrollments`. An enrollment counts as active when its status is active
 * and its course is still active.
 */
final class EnrollmentRepository
{
    public function hasActiveEnrollment(Student $student): bool
    {
        return $this->activeEnrollmentsOf($student)->exists();
    }

    /**
     * Most recent active enrollment, with its course and the course's level loaded.
     */
    public function latestActiveEnrollment(Student $student): ?Enrollment
    {
        return $this->activeEnrollmentsOf($student)
            ->with(['course.level'])
            ->orderByDesc('enrolled_at')
            ->first();
    }

    /**
     * @return Builder<Enrollment>
     */
    private function activeEnrollmentsOf(Student $student): Builder
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('course', fn (Builder $query) => $query->where('is_active', true));
    }
}
