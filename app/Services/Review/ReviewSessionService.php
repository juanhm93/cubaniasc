<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\ReviewSession;
use App\Models\Student;

final class ReviewSessionService
{
    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    public function create(Student $student): ReviewSession
    {
        $level = $this->levelResolver->resolveLevel($student);
        $startedAt = now();

        return ReviewSession::query()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addSeconds($level->review_duration_seconds),
            'completed' => false,
        ]);
    }

    public function hasExpired(ReviewSession $session): bool
    {
        return now()->greaterThanOrEqualTo($session->expires_at);
    }

    public function remainingSeconds(ReviewSession $session): int
    {
        if ($this->hasExpired($session)) {
            return 0;
        }

        return (int) now()->diffInSeconds($session->expires_at);
    }

    public function markCompleted(ReviewSession $session): ReviewSession
    {
        $session->update(['completed' => true]);

        return $session->fresh();
    }
}
