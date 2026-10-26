<?php

declare(strict_types=1);

namespace App\Repositories\Review;

use App\Models\Student;
use App\Models\StudentStreak;
use Carbon\CarbonInterface;

/**
 * Queries and writes on `student_streaks` (one row per student).
 */
final class StudentStreakRepository
{
    public function firstOrCreateFor(Student $student): StudentStreak
    {
        return StudentStreak::query()->firstOrCreate(
            ['student_id' => $student->id],
            [
                'current_streak' => 0,
                'last_review_at' => null,
            ],
        );
    }

    public function update(StudentStreak $streak, int $currentStreak, CarbonInterface $lastReviewAt): StudentStreak
    {
        $streak->update([
            'current_streak' => $currentStreak,
            'last_review_at' => $lastReviewAt,
        ]);

        return $streak->fresh() ?? $streak;
    }
}
