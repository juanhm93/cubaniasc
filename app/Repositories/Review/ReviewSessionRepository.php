<?php

declare(strict_types=1);

namespace App\Repositories\Review;

use App\Models\Level;
use App\Models\ReviewSession;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Queries and writes on `review_sessions`.
 *
 * `completed` means the session is closed (finished or expired); `completed_at` is only
 * filled when the student actually finished it.
 */
final class ReviewSessionRepository
{
    public function start(
        Student $student,
        Level $level,
        CarbonInterface $startedAt,
        CarbonInterface $expiresAt,
    ): ReviewSession {
        return ReviewSession::query()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'completed' => false,
        ]);
    }

    /**
     * Latest open session whose timer has not run out yet.
     */
    public function findActiveForStudent(Student $student, CarbonInterface $now): ?ReviewSession
    {
        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->where('completed', false)
            ->where('expires_at', '>', $now)
            ->latest('started_at')
            ->first();
    }

    public function existsStartedBetween(Student $student, CarbonInterface $from, CarbonInterface $to): bool
    {
        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->whereBetween('started_at', [$from, $to])
            ->exists();
    }

    /**
     * Closes the student's open sessions whose timer already ran out.
     *
     * @return int Number of sessions closed.
     */
    public function closeExpiredForStudent(Student $student, CarbonInterface $now): int
    {
        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->where('completed', false)
            ->where('expires_at', '<=', $now)
            ->update(['completed' => true]);
    }

    public function close(ReviewSession $session): ReviewSession
    {
        $session->update(['completed' => true]);

        return $session->fresh() ?? $session;
    }

    public function markCompleted(ReviewSession $session, CarbonInterface $completedAt): ReviewSession
    {
        $session->update([
            'completed' => true,
            'completed_at' => $completedAt,
        ]);

        return $session->fresh() ?? $session;
    }

    /**
     * Loads what a client needs to resume a session at the right step: the level,
     * the figures the student chose and how many quiz questions were answered.
     */
    public function loadProgress(ReviewSession $session): ReviewSession
    {
        return $session
            ->loadMissing(['level', 'selectedFigures.level'])
            ->loadCount('quizResponses');
    }

    /**
     * Moments the student finished a review since the given time.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function completionTimesSince(Student $student, CarbonInterface $since): Collection
    {
        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->pluck('completed_at')
            ->map(fn (mixed $completedAt): CarbonImmutable => CarbonImmutable::parse($completedAt))
            ->values();
    }
}
