<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Review\ActiveEnrollmentNotFoundException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;

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
}
