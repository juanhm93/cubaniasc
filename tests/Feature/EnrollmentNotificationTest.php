<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PreRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\EnrollmentWelcomeNotification;
use App\Notifications\NewEnrollmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class EnrollmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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

    public function test_enrolling_a_student_alerts_students_staff_and_the_course_teacher(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin_staff');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $otherAdminStaff = $this->userWithRole('admin_staff');
        $courseTeacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $staff = $this->userWithRole('staff');

        $course = Course::factory()->create(['company_id' => $actor->company_id, 'user_id' => $courseTeacher->id]);
        $student = Student::factory()->create();

        $this->actingAs($actor)
            ->post(route('admin.students.enroll.store'), [
                'course_id' => $course->id,
                'student_id' => $student->id,
            ])
            ->assertRedirect(route('admin.students.index'));

        foreach ([$owner, $admin, $otherAdminStaff, $courseTeacher] as $recipient) {
            Notification::assertSentTo($recipient, NewEnrollmentNotification::class);
        }

        foreach ([$actor, $otherTeacher, $staff] as $nonRecipient) {
            Notification::assertNotSentTo($nonRecipient, NewEnrollmentNotification::class);
        }

        Notification::assertSentTo($student, EnrollmentWelcomeNotification::class);
    }

    public function test_the_payload_and_emails_describe_the_enrollment(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin', ['name' => 'Ana Admin']);
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $course = Course::factory()->create(['company_id' => $actor->company_id]);
        $student = Student::factory()->create(['name' => 'Carlos Pérez']);

        $this->actingAs($actor)->post(route('admin.students.enroll.store'), [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        Notification::assertSentTo($owner, NewEnrollmentNotification::class, function (NewEnrollmentNotification $notification) use ($owner, $student, $course): bool {
            $payload = $notification->toDatabase($owner);
            $mail = $notification->toMail($owner)->render();

            return $payload['student_id'] === $student->id
                && $payload['course_id'] === $course->id
                && $payload['student_name'] === 'Carlos Pérez'
                && $payload['actor_name'] === 'Ana Admin'
                && ! array_key_exists('url', $payload)
                && str_contains($mail, 'Carlos Pérez')
                && str_contains($mail, e(route('admin.students.show', $student)));
        });

        Notification::assertSentTo($student, EnrollmentWelcomeNotification::class, function (EnrollmentWelcomeNotification $notification) use ($student): bool {
            $mail = $notification->toMail($student)->render();

            return $notification->via($student) === ['mail']
                && str_contains($mail, 'Bienvenido a Cubania')
                && str_contains($mail, e(route('review-panel')));
        });
    }

    public function test_converting_a_pre_registration_with_a_course_notifies(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $course = Course::factory()->create(['company_id' => $actor->company_id]);
        $preRegistration = PreRegistration::factory()->create(['email' => 'nueva@example.com']);

        $this->actingAs($actor)->post(route('admin.pre-registrations.enroll.store', $preRegistration), [
            'name' => 'Nueva Alumna',
            'email' => 'nueva@example.com',
            'course_id' => $course->id,
        ])->assertRedirect();

        $student = Student::query()->where('email', 'nueva@example.com')->firstOrFail();

        Notification::assertSentTo($owner, NewEnrollmentNotification::class);
        Notification::assertSentTo($student, EnrollmentWelcomeNotification::class);
    }

    public function test_creating_a_student_without_a_course_does_not_notify(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin');
        $this->userWithRole('staff', ['is_owner' => 1]);

        $this->actingAs($actor)->post(route('admin.payments.enroll.store'), [
            'name' => 'Sin Curso',
            'email' => 'sincurso@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('students', ['email' => 'sincurso@example.com']);
        Notification::assertNothingSent();
    }

    public function test_walk_in_student_with_a_course_notifies(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $course = Course::factory()->create(['company_id' => $actor->company_id]);

        $this->actingAs($actor)->post(route('admin.payments.enroll.store'), [
            'name' => 'Con Curso',
            'email' => 'concurso@example.com',
            'course_id' => $course->id,
        ])->assertRedirect();

        Notification::assertSentTo($owner, NewEnrollmentNotification::class);
    }

    public function test_a_mail_failure_keeps_the_enrollment_and_the_bell(): void
    {
        config(['mail.default' => 'failing']);
        config(['mail.mailers.failing' => ['transport' => 'failing-transport']]);
        $this->app['mail.manager']->extend('failing-transport', fn () => throw new RuntimeException('smtp down'));

        $actor = $this->userWithRole('admin');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $course = Course::factory()->create(['company_id' => $actor->company_id]);
        $student = Student::factory()->create();

        $this->actingAs($actor)->post(route('admin.students.enroll.store'), [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ])->assertRedirect(route('admin.students.index'));

        $this->assertTrue(Enrollment::query()->where('student_id', $student->id)->exists());
        $this->assertSame(1, $owner->unreadNotifications()->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
