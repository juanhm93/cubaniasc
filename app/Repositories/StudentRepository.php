<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Student;

/**
 * Queries on `students` used to identify a student without a password.
 */
final class StudentRepository
{
    /**
     * Case-insensitive match; the email must already be trimmed and lowercased.
     */
    public function findByEmail(string $normalizedEmail): ?Student
    {
        return Student::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
    }

    public function findByDni(string $dni): ?Student
    {
        return Student::query()
            ->where('dni', $dni)
            ->first();
    }
}
