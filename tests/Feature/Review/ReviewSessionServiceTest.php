<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use App\Services\Review\ReviewSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReviewSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReviewSessionService::class);
    }

    public function test_creates_session_with_level_review_duration(): void
    {
        $level = Level::factory()->create(['review_duration_seconds' => 420]);
        $student = $this->enrollStudentInLevel($level);

        $session = $this->service->create($student);

        $this->assertDatabaseHas('review_sessions', [
            'id' => $session->id,
            'student_id' => $student->id,
            'level_id' => $level->id,
            'completed' => false,
        ]);

        $this->assertTrue($session->started_at->equalTo($session->expires_at->copy()->subSeconds(420)));
        $this->assertGreaterThanOrEqual(419, $this->service->remainingSeconds($session));
        $this->assertLessThanOrEqual(420, $this->service->remainingSeconds($session));
    }

    public function test_marks_session_as_expired_after_duration(): void
    {
        $level = Level::factory()->create(['review_duration_seconds' => 60]);
        $student = $this->enrollStudentInLevel($level);

        $session = $this->service->create($student);
        $session->update(['expires_at' => now()->subSecond()]);

        $this->assertTrue($this->service->hasExpired($session->fresh()));
        $this->assertSame(0, $this->service->remainingSeconds($session->fresh()));
    }

    public function test_marks_session_as_completed(): void
    {
        $student = $this->enrollStudentInLevel(Level::factory()->create());
        $session = $this->service->create($student);

        $completed = $this->service->markCompleted($session);

        $this->assertTrue($completed->completed);
    }

    private function enrollStudentInLevel(Level $level): Student
    {
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create([
                'level_id' => $level->id,
                'is_active' => true,
            ])->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }
}
