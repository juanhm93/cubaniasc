<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\ReviewDailyLimitException;
use App\Models\ReviewSession;
use App\Models\Student;

final class ReviewSessionService
{
    public const MAX_QUIZ_QUESTIONS = 5;

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

    public function findActiveSession(Student $student): ?ReviewSession
    {
        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->where('completed', false)
            ->where('expires_at', '>', now())
            ->latest('started_at')
            ->first();
    }

    public function createOrResume(Student $student): ReviewSession
    {
        $active = $this->findActiveSession($student);

        if ($active !== null) {
            return $active->loadMissing('level');
        }

        $this->closeExpiredIncompleteSessions($student);

        if ($this->hasStartedToday($student)) {
            throw ReviewDailyLimitException::alreadyUsedToday();
        }

        return $this->create($student)->loadMissing('level');
    }

    public function hasStartedToday(Student $student): bool
    {
        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->whereDate('started_at', now()->toDateString())
            ->exists();
    }

    public function closeExpiredIncompleteSessions(Student $student): void
    {
        ReviewSession::query()
            ->where('student_id', $student->id)
            ->where('completed', false)
            ->where('expires_at', '<=', now())
            ->update(['completed' => true]);
    }

    public function markClosed(ReviewSession $session): ReviewSession
    {
        if ($session->completed) {
            return $session;
        }

        $session->update(['completed' => true]);

        return $session->fresh() ?? $session;
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

        return $session->fresh() ?? $session;
    }
}
