<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Student;
use App\Models\StudentStreak;
use Carbon\CarbonInterface;

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
        $today = now()->startOfDay();

        if ($streak->last_review_at !== null && $streak->last_review_at->startOfDay()->equalTo($today)) {
            return $streak;
        }

        $newStreak = $this->calculateNextStreak($streak, $today);

        $streak->update([
            'current_streak' => $newStreak,
            'last_review_at' => now(),
        ]);

        return $streak->fresh();
    }

    private function calculateNextStreak(StudentStreak $streak, CarbonInterface $today): int
    {
        if ($streak->last_review_at === null) {
            return 1;
        }

        $lastReviewDay = $streak->last_review_at->startOfDay();

        if ($lastReviewDay->equalTo($today->copy()->subDay())) {
            return $streak->current_streak + 1;
        }

        return 1;
    }
}
