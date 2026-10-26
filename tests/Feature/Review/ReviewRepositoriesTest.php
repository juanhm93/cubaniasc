<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Repositories\EnrollmentRepository;
use App\Repositories\LevelRepository;
use App\Repositories\Review\QuizRepository;
use App\Repositories\Review\ReviewSessionRepository;
use App\Repositories\StudentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The review services are covered through their own tests; these pin down the query
 * rules that live only in the repositories.
 */
class ReviewRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_are_found_by_email_ignoring_case_and_by_dni(): void
    {
        $student = Student::factory()->create(['email' => 'Ana.Lopez@Example.com', 'dni' => 'V-1234']);
        $repository = app(StudentRepository::class);

        $this->assertTrue($repository->findByEmail('ana.lopez@example.com')?->is($student));
        $this->assertTrue($repository->findByDni('V-1234')?->is($student));
        $this->assertNull($repository->findByDni('V-9999'));
    }

    public function test_the_latest_active_enrollment_ignores_inactive_courses_and_other_statuses(): void
    {
        $student = Student::factory()->create();
        $latestActive = $this->enroll($student, now()->subWeek());
        $this->enroll($student, now()->subMonth());
        $this->enroll($student, now(), courseIsActive: false);
        $this->enroll($student, now(), status: EnrollmentStatus::Withdrawn);
        $repository = app(EnrollmentRepository::class);

        $found = $repository->latestActiveEnrollment($student);

        $this->assertTrue($found?->is($latestActive));
        $this->assertTrue($found->relationLoaded('course'));
        $this->assertTrue($repository->hasActiveEnrollment($student));
        $this->assertFalse($repository->hasActiveEnrollment(Student::factory()->create()));
    }

    public function test_previous_levels_are_the_earlier_ones_of_the_same_dance_type_closest_first(): void
    {
        $basic1 = Level::factory()->create(['sort_order' => 1]);
        $basic2 = Level::factory()->create(['sort_order' => 2, 'dance_type_id' => $basic1->dance_type_id]);
        $basic3 = Level::factory()->create(['sort_order' => 3, 'dance_type_id' => $basic1->dance_type_id]);
        Level::factory()->create(['sort_order' => 4, 'dance_type_id' => $basic1->dance_type_id]);
        Level::factory()->create(['sort_order' => 0]);

        $previous = app(LevelRepository::class)->previousLevelsOf($basic3);

        $this->assertSame([$basic2->id, $basic1->id], $previous->pluck('id')->all());
    }

    public function test_the_active_session_is_open_and_not_expired(): void
    {
        $student = Student::factory()->create();
        $repository = app(ReviewSessionRepository::class);
        ReviewSession::factory()->for($student)->expired()->create();
        ReviewSession::factory()->for($student)->completed()->create([
            'started_at' => now()->subMinute(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertNull($repository->findActiveForStudent($student, now()));

        $active = ReviewSession::factory()->for($student)->create([
            'started_at' => now()->subMinute(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertTrue($repository->findActiveForStudent($student, now())?->is($active));
    }

    public function test_closing_expired_sessions_only_touches_that_students_expired_open_sessions(): void
    {
        $student = Student::factory()->create();
        $expired = ReviewSession::factory()->for($student)->expired()->create();
        $running = ReviewSession::factory()->for($student)->create(['expires_at' => now()->addMinutes(5)]);
        $someoneElses = ReviewSession::factory()->expired()->create();

        $closed = app(ReviewSessionRepository::class)->closeExpiredForStudent($student, now());

        $this->assertSame(1, $closed);
        $this->assertTrue($expired->fresh()->completed);
        $this->assertNull($expired->fresh()->completed_at);
        $this->assertFalse($running->fresh()->completed);
        $this->assertFalse($someoneElses->fresh()->completed);
    }

    public function test_quiz_options_are_only_found_through_their_own_item(): void
    {
        $item = QuizItem::factory()->create();
        $option = QuizOption::factory()->for($item)->create();
        $foreignOption = QuizOption::factory()->create();
        $repository = app(QuizRepository::class);

        $this->assertTrue($repository->findOptionOfItem($item, $option->id)?->is($option));
        $this->assertNull($repository->findOptionOfItem($item, $foreignOption->id));
    }

    public function test_the_quiz_pool_holds_active_items_of_the_review_levels_and_general_items(): void
    {
        $level = Level::factory()->create();
        $repository = app(QuizRepository::class);

        $ofLevel = QuizItem::factory()->for($level)->create();
        $general = QuizItem::factory()->global()->create();
        $inactive = QuizItem::factory()->for($level)->inactive()->create();
        $otherLevel = QuizItem::factory()->create();

        $this->assertTrue($repository->isInPool($ofLevel, [$level->id]));
        $this->assertTrue($repository->isInPool($general, [$level->id]));
        $this->assertFalse($repository->isInPool($inactive, [$level->id]));
        $this->assertFalse($repository->isInPool($otherLevel, [$level->id]));
    }

    private function enroll(
        Student $student,
        mixed $enrolledAt,
        bool $courseIsActive = true,
        EnrollmentStatus $status = EnrollmentStatus::Active,
    ): Enrollment {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create(['is_active' => $courseIsActive])->id,
            'status' => $status,
            'enrolled_at' => $enrolledAt,
        ]);
    }
}
