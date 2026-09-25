<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentStreak;
use Carbon\CarbonImmutable;

/**
 * Review streak measured in the academy's local days (see {@see ReviewSessionService::academyTimezone()}).
 */
final class StreakService
{
    public function getOrCreate(Student $student): StudentStreak
    {
        return StudentStreak::query()->firstOrCreate(
            ['student_id' => $student->id],
            [
                'current_streak' => 0,
                'last_review_at' => null,
            ],
        );
    }

    public function recordCompletion(Student $student): StudentStreak
    {
        $streak = $this->getOrCreate($student);
        $today = ReviewSessionService::localDate(now());

        if ($streak->last_review_at !== null && ReviewSessionService::localDate($streak->last_review_at) === $today) {
            return $streak;
        }

        $streak->update([
            'current_streak' => $this->calculateNextStreak($streak),
            'last_review_at' => now(),
        ]);

        return $streak->fresh();
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

        $completedDates = ReviewSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $rangeStart)
            ->pluck('completed_at')
            ->map(fn ($completedAt): string => ReviewSessionService::localDate(CarbonImmutable::parse($completedAt)))
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
