<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Models\Student;
use App\Repositories\EnrollmentRepository;
use App\Repositories\StudentRepository;
use Illuminate\Support\Str;

final class StudentIdentificationService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly EnrollmentRepository $enrollments,
    ) {}

    public function identify(?string $email = null, ?string $dni = null): Student
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedDni = $this->normalizeDni($dni);

        if ($normalizedEmail === null && $normalizedDni === null) {
            throw StudentNotIdentifiableException::missingIdentifier();
        }

        $student = $normalizedEmail !== null
            ? $this->students->findByEmail($normalizedEmail)
            : $this->students->findByDni($normalizedDni);

        if ($student === null) {
            throw StudentNotIdentifiableException::notFound();
        }

        if (! $this->enrollments->hasActiveEnrollment($student)) {
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
}
