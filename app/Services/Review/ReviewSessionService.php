<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\ReviewTodayStatus;
use App\Exceptions\Review\ReviewDailyLimitException;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Repositories\Review\ReviewSessionRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ReviewSessionService
{
    public const MAX_QUIZ_QUESTIONS = 5;

    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
        private readonly ReviewSessionRepository $sessions,
    ) {}

    public function create(Student $student): ReviewSession
    {
        $level = $this->levelResolver->resolveLevel($student);
        $startedAt = now();

        return $this->sessions->start(
            $student,
            $level,
            $startedAt,
            $startedAt->copy()->addSeconds($level->review_duration_seconds),
        );
    }

    public function findActiveSession(Student $student): ?ReviewSession
    {
        return $this->sessions->findActiveForStudent($student, now());
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

    /**
     * Today's review for the student: the session in progress, or whether a new one
     * can still be started today. Closes sessions whose timer already ran out.
     *
     * @return array{status: ReviewTodayStatus, session: ReviewSession|null}
     */
    public function todayFor(Student $student): array
    {
        $active = $this->findActiveSession($student);

        if ($active !== null) {
            return ['status' => ReviewTodayStatus::InProgress, 'session' => $active];
        }

        $this->closeExpiredIncompleteSessions($student);

        return [
            'status' => $this->hasStartedToday($student) ? ReviewTodayStatus::Locked : ReviewTodayStatus::Available,
            'session' => null,
        ];
    }

    public function hasStartedToday(Student $student): bool
    {
        [$startOfDay, $endOfDay] = self::localDayBounds();

        return $this->sessions->existsStartedBetween($student, $startOfDay, $endOfDay);
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

    /**
     * Moment the next local day starts, when a new review can be started.
     */
    public static function nextDayStartsAt(): CarbonImmutable
    {
        return self::localDayBounds()[1]->addSecond()->startOfSecond();
    }

    public function closeExpiredIncompleteSessions(Student $student): void
    {
        $this->sessions->closeExpiredForStudent($student, now());
    }

    public function markClosed(ReviewSession $session): ReviewSession
    {
        if ($session->completed) {
            return $session;
        }

        return $this->sessions->close($session);
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

        return $this->sessions->markCompleted($session, now());
    }

    /**
     * Loads the level, the chosen figures and the quiz answer count of the session.
     */
    public function withProgress(ReviewSession $session): ReviewSession
    {
        return $this->sessions->loadProgress($session);
    }
}
