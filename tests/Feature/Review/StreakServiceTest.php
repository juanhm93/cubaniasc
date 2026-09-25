<?php

namespace Tests\Feature\Review;

use App\Models\Student;
use App\Models\StudentStreak;
use App\Services\Review\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    private StreakService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StreakService::class);
    }

    public function test_starts_streak_on_first_completion(): void
    {
        $student = Student::factory()->create();

        $streak = $this->service->recordCompletion($student);

        $this->assertSame(1, $streak->current_streak);
        $this->assertNotNull($streak->last_review_at);
    }

    public function test_increments_streak_when_last_review_was_yesterday(): void
    {
        $student = Student::factory()->create();
        StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 2,
            'last_review_at' => now()->subDay(),
        ]);

        $streak = $this->service->recordCompletion($student);

        $this->assertSame(3, $streak->current_streak);
    }

    public function test_resets_streak_when_gap_is_greater_than_one_day(): void
    {
        $student = Student::factory()->create();
        StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 5,
            'last_review_at' => now()->subDays(3),
        ]);

        $streak = $this->service->recordCompletion($student);

        $this->assertSame(1, $streak->current_streak);
    }

    public function test_does_not_increment_streak_twice_on_same_day(): void
    {
        $student = Student::factory()->create();
        StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 4,
            'last_review_at' => now(),
        ]);

        $streak = $this->service->recordCompletion($student);

        $this->assertSame(4, $streak->current_streak);
    }
}
