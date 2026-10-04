<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentStreak;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use App\Notifications\StudentReviewCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $slug)->first() ?? Role::factory()->create(['slug' => $slug]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }

    /**
     * @return array{0: Student, 1: ReviewSession, 2: Course}
     */
    private function sessionReadyToComplete(User $teacher): array
    {
        $level = Level::factory()->create(['name' => 'Nivel 2']);
        $course = Course::factory()->create(['level_id' => $level->id, 'user_id' => $teacher->id, 'is_active' => true]);
        $student = Student::factory()->create(['name' => 'Lucía']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        $figures = LevelContent::factory()->count(2)->for($level)->create();
        $session->figures()->attach(
            $figures->mapWithKeys(fn (LevelContent $figure): array => [$figure->id => ['selected_by_student' => true]])->all(),
        );

        return [$student, $session, $course];
    }

    public function test_finishing_the_review_cheers_the_student_and_tells_the_staff(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $adminStaff = $this->userWithRole('admin_staff');
        [$student, $session, $course] = $this->sessionReadyToComplete($teacher);

        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.complete', $session))->assertOk();

        Notification::assertSentTo($student, ReviewCompletedNotification::class, function (ReviewCompletedNotification $notification) use ($student): bool {
            $mail = $notification->toMail($student)->render();

            return $notification->streak === 1
                && str_contains($mail, 'en la práctica está la mejora')
                && str_contains($mail, 'Lucía');
        });

        foreach ([$teacher, $owner, $admin] as $recipient) {
            Notification::assertSentTo($recipient, StudentReviewCompletedNotification::class, function (StudentReviewCompletedNotification $notification) use ($recipient, $student, $course): bool {
                $payload = $notification->toDatabase($recipient);

                return $notification->via($recipient) === ['database']
                    && $payload['student_id'] === $student->id
                    && $payload['course_id'] === $course->id
                    && $payload['level_name'] === 'Nivel 2'
                    && $payload['streak'] === 1;
            });
        }

        Notification::assertNotSentTo($otherTeacher, StudentReviewCompletedNotification::class);
        Notification::assertNotSentTo($adminStaff, StudentReviewCompletedNotification::class);
    }

    public function test_completing_again_does_not_repeat_the_notifications(): void
    {
        $teacher = $this->userWithRole('teacher');
        [$student, $session] = $this->sessionReadyToComplete($teacher);

        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.complete', $session))->assertOk();
        $this->postJson(route('review.sessions.complete', $session))->assertOk();

        Notification::assertSentToTimes($student, ReviewCompletedNotification::class, 1);
        Notification::assertSentToTimes($teacher, StudentReviewCompletedNotification::class, 1);
    }

    public function test_a_long_streak_gets_a_milestone_message(): void
    {
        $teacher = $this->userWithRole('teacher');
        [$student, $session] = $this->sessionReadyToComplete($teacher);

        StudentStreak::query()->create([
            'student_id' => $student->id,
            'current_streak' => 6,
            'last_review_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.complete', $session))->assertOk();

        Notification::assertSentTo($student, ReviewCompletedNotification::class, function (ReviewCompletedNotification $notification) use ($student): bool {
            return $notification->streak === 7
                && str_contains($notification->toMail($student)->subject, 'semana');
        });
    }
}
