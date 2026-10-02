<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Str;

final class StudentIdentificationService
{
    public function identify(?string $email = null, ?string $dni = null): Student
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedDni = $this->normalizeDni($dni);

        if ($normalizedEmail === null && $normalizedDni === null) {
            throw StudentNotIdentifiableException::missingIdentifier();
        }

        $student = Student::query()
            ->when(
                $normalizedEmail !== null,
                fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalizedEmail]),
            )
            ->when($normalizedEmail === null, fn ($query) => $query->where('dni', $normalizedDni))
            ->first();

        if ($student === null) {
            throw StudentNotIdentifiableException::notFound();
        }

        if (! $this->hasActiveEnrollment($student)) {
            throw StudentNotIdentifiableException::notEnrolled();
        }

        return $student;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDni(?string $dni): ?string
    {
        if ($dni === null) {
            return null;
        }

        $normalized = trim($dni);

        return $normalized === '' ? null : $normalized;
    }

    private function hasActiveEnrollment(Student $student): bool
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('course', fn ($query) => $query->where('is_active', true))
            ->exists();
    }
}
