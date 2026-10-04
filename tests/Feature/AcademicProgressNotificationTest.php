<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\DanceType;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CourseContentCoveredNotification;
use App\Notifications\CourseLevelAdvancedNotification;
use App\Notifications\CourseLevelUpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AcademicProgressNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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

    public function test_marking_a_figure_as_covered_alerts_owner_and_admins(): void
    {
        $teacher = $this->userWithRole('teacher');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $adminStaff = $this->userWithRole('admin_staff');

        $course = Course::factory()->create(['company_id' => $teacher->company_id, 'user_id' => $teacher->id]);
        $figure = LevelContent::factory()->create(['level_id' => $course->level_id, 'name' => 'Dile que no']);
        LevelContent::factory()->create(['level_id' => $course->level_id]);

        $this->actingAs($teacher)
            ->post(route('admin.courses.level-content-toggle', $course), ['level_content_id' => $figure->id])
            ->assertRedirect(route('admin.courses.show', $course));

        Notification::assertSentTo($owner, CourseContentCoveredNotification::class, function (CourseContentCoveredNotification $notification) use ($owner, $course): bool {
            $payload = $notification->toDatabase($owner);

            return $payload['course_id'] === $course->id
                && $payload['figure_name'] === 'Dile que no'
                && $payload['covered_count'] === 1
                && $payload['total_count'] === 2
                && str_contains($notification->toMail($owner)->render(), '1 de 2 figuras');
        });
        Notification::assertSentTo($admin, CourseContentCoveredNotification::class);
        Notification::assertNotSentTo($teacher, CourseContentCoveredNotification::class);
        Notification::assertNotSentTo($adminStaff, CourseContentCoveredNotification::class);
    }

    public function test_unmarking_a_figure_does_not_notify(): void
    {
        $owner = $this->userWithRole('admin', ['is_owner' => 1]);
        $teacher = $this->userWithRole('teacher');
        $course = Course::factory()->create(['company_id' => $teacher->company_id, 'user_id' => $teacher->id]);
        $figure = LevelContent::factory()->create(['level_id' => $course->level_id]);

        $this->actingAs($teacher)->post(route('admin.courses.level-content-toggle', $course), ['level_content_id' => $figure->id]);
        $this->actingAs($teacher)->post(route('admin.courses.level-content-toggle', $course), ['level_content_id' => $figure->id]);

        Notification::assertSentToTimes($owner, CourseContentCoveredNotification::class, 1);
    }

    public function test_advancing_a_level_alerts_staff_and_celebrates_with_active_students(): void
    {
        $actor = $this->userWithRole('admin');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $teacher = $this->userWithRole('teacher');
        $adminStaff = $this->userWithRole('admin_staff');

        $danceType = DanceType::factory()->create();
        $levelOne = Level::factory()->create(['dance_type_id' => $danceType->id, 'sort_order' => 1, 'name' => 'Nivel 1']);
        $levelTwo = Level::factory()->create(['dance_type_id' => $danceType->id, 'sort_order' => 2, 'name' => 'Nivel 2']);
        $course = Course::factory()->create([
            'company_id' => $actor->company_id,
            'user_id' => $teacher->id,
            'level_id' => $levelOne->id,
        ]);

        $active = Student::factory()->create();
        $withdrawn = Student::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id, 'student_id' => $active->id, 'status' => EnrollmentStatus::Active]);
        Enrollment::factory()->create(['course_id' => $course->id, 'student_id' => $withdrawn->id, 'status' => EnrollmentStatus::Withdrawn]);

        $this->actingAs($actor)
            ->post(route('admin.courses.advance-level', $course))
            ->assertRedirect(route('admin.courses.show', $course));

        $this->assertSame($levelTwo->id, $course->fresh()->level_id);

        Notification::assertSentTo($owner, CourseLevelAdvancedNotification::class, function (CourseLevelAdvancedNotification $notification) use ($owner): bool {
            $payload = $notification->toDatabase($owner);

            return $payload['previous_level_name'] === 'Nivel 1'
                && $payload['new_level_name'] === 'Nivel 2'
                && $payload['student_count'] === 1;
        });
        Notification::assertSentTo($teacher, CourseLevelAdvancedNotification::class);
        Notification::assertNotSentTo($actor, CourseLevelAdvancedNotification::class);
        Notification::assertNotSentTo($adminStaff, CourseLevelAdvancedNotification::class);

        Notification::assertSentTo($active, CourseLevelUpNotification::class, function (CourseLevelUpNotification $notification) use ($active): bool {
            $mail = $notification->toMail($active)->render();

            return str_contains($mail, 'Nivel 2') && str_contains($mail, e(route('review-panel')));
        });
        Notification::assertNotSentTo($withdrawn, CourseLevelUpNotification::class);
    }

    public function test_no_next_level_means_no_notification(): void
    {
        $actor = $this->userWithRole('admin');
        $this->userWithRole('staff', ['is_owner' => 1]);
        $course = Course::factory()->create(['company_id' => $actor->company_id]);

        $this->actingAs($actor)->post(route('admin.courses.advance-level', $course))->assertSessionHasErrors('level');

        Notification::assertNothingSent();
    }
}
