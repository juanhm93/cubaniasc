<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Student;
use App\Models\StudentStreak;
use App\Repositories\Review\ReviewSessionRepository;
use App\Repositories\Review\StudentStreakRepository;
use Carbon\CarbonImmutable;

/**
 * Review streak measured in the academy's local days (see {@see ReviewSessionService::academyTimezone()}).
 */
final class StreakService
{
    public function __construct(
        private readonly StudentStreakRepository $streaks,
        private readonly ReviewSessionRepository $sessions,
    ) {}

    public function getOrCreate(Student $student): StudentStreak
    {
        return $this->streaks->firstOrCreateFor($student);
    }

    public function recordCompletion(Student $student): StudentStreak
    {
        $streak = $this->getOrCreate($student);
        $today = ReviewSessionService::localDate(now());

        if ($streak->last_review_at !== null && ReviewSessionService::localDate($streak->last_review_at) === $today) {
            return $streak;
        }

        return $this->streaks->update($streak, $this->calculateNextStreak($streak), now());
    }

    /**
     * Streak that is still alive today: a streak whose last review was before yesterday is shown as 0.
     */
    public function currentStreak(StudentStreak $streak): int
    {
        if ($streak->last_review_at === null) {
            return 0;
        }

        $lastReviewDay = ReviewSessionService::localDate($streak->last_review_at);
        $yesterday = ReviewSessionService::localDate(now()->subDay());

        return $lastReviewDay >= $yesterday ? $streak->current_streak : 0;
    }

    /**
     * Local dates (Y-m-d) of the last {@see $days} days, oldest first, flagged when a review was completed.
     *
     * @return list<array{date: string, completed: bool}>
     */
    public function recentDays(Student $student, int $days = 7): array
    {
        [$rangeStart] = ReviewSessionService::localDayBounds(now()->subDays($days - 1));

        $completedDates = $this->sessions->completionTimesSince($student, $rangeStart)
            ->map(fn (CarbonImmutable $completedAt): string => ReviewSessionService::localDate($completedAt))
            ->unique()
            ->all();

        $result = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = ReviewSessionService::localDate(now()->subDays($offset));
            $result[] = ['date' => $date, 'completed' => in_array($date, $completedDates, true)];
        }

        return $result;
    }

    private function calculateNextStreak(StudentStreak $streak): int
    {
        if ($streak->last_review_at === null) {
            return 1;
        }

        $yesterday = ReviewSessionService::localDate(now()->subDay());

        if (ReviewSessionService::localDate($streak->last_review_at) === $yesterday) {
            return $streak->current_streak + 1;
        }

        return 1;
    }
}
