<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\ReviewDailyLimitException;
use App\Models\ReviewSession;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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
        [$startOfDay, $endOfDay] = self::localDayBounds();

        return ReviewSession::query()
            ->where('student_id', $student->id)
            ->whereBetween('started_at', [$startOfDay, $endOfDay])
            ->exists();
    }

    public static function academyTimezone(): string
    {
        return (string) config('cubania.timezone', config('app.timezone'));
    }

    /**
     * Start and end of the academy's local day that contains the given moment, in the app timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function localDayBounds(?CarbonInterface $moment = null): array
    {
        $localMoment = CarbonImmutable::instance($moment ?? now())->setTimezone(self::academyTimezone());
        $appTimezone = (string) config('app.timezone');

        return [
            $localMoment->startOfDay()->setTimezone($appTimezone),
            $localMoment->endOfDay()->setTimezone($appTimezone),
        ];
    }

    /**
     * Local calendar date (Y-m-d) in the academy timezone.
     */
    public static function localDate(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::academyTimezone())->toDateString();
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

    /**
     * Marks the session as finished by the student. Idempotent: a session that already
     * has `completed_at` keeps its original value.
     */
    public function markCompleted(ReviewSession $session): ReviewSession
    {
        if ($session->completed_at !== null) {
            return $session;
        }

        $session->update([
            'completed' => true,
            'completed_at' => now(),
        ]);

        return $session->fresh() ?? $session;
    }
}
