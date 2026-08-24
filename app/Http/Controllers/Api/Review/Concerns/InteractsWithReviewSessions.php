<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review\Concerns;

use App\Exceptions\Review\ReviewSessionExpiredException;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Services\Review\ReviewSessionService;
use Illuminate\Http\Request;

trait InteractsWithReviewSessions
{
    protected function authenticatedStudent(Request $request): Student
    {
        $user = $request->user();

        if (! $user instanceof Student) {
            abort(403);
        }

        return $user;
    }

    protected function authorizeReviewSession(Student $student, ReviewSession $session): void
    {
        if ($session->student_id !== $student->id) {
            abort(403);
        }
    }

    protected function ensureSessionIsActive(ReviewSession $session): void
    {
        if (app(ReviewSessionService::class)->hasExpired($session)) {
            throw ReviewSessionExpiredException::forSession();
        }
    }
}
