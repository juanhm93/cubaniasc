<?php

namespace Tests\Feature\Admin;

use App\Actions\Enrollments\EnrollStudentInCourse;
use App\Mail\EnrollmentWelcomeMail;
use App\Models\Course;
use App\Models\CourseScheduleSlot;
use App\Models\Enrollment;
use App\Models\PreRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EnrollmentWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolling_an_existing_student_sends_one_welcome_mail(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $course = Course::factory()->create(['company_id' => $admin->company_id]);
        $student = Student::factory()->create(['email' => 'ana@test.invalid']);

        $this->actingAs($admin)
            ->post(route('admin.students.enroll.store'), [
                'course_id' => $course->id,
                'student_id' => $student->id,
            ])
            ->assertRedirect(route('admin.students.index'));

        Mail::assertSent(EnrollmentWelcomeMail::class, 1);
        Mail::assertSent(
            EnrollmentWelcomeMail::class,
            fn (EnrollmentWelcomeMail $mail): bool => $mail->hasTo('ana@test.invalid')
                && $mail->enrollment->course_id === $course->id,
        );
    }

    public function test_converting_a_pre_registration_with_a_course_sends_one_welcome_mail(): void
    {
        Mail::fake();
        $course = Course::factory()->create();
        $preRegistration = PreRegistration::factory()->create();

        $this->actingAs($this->admin())
            ->post(
                route('admin.pre-registrations.enroll.store', ['preRegistration' => $preRegistration->id]),
                $this->studentPayload('maria@test.invalid', $course->id),
            )
            ->assertSessionHasNoErrors();

        Mail::assertSent(EnrollmentWelcomeMail::class, 1);
        Mail::assertSent(EnrollmentWelcomeMail::class, fn (EnrollmentWelcomeMail $mail): bool => $mail->hasTo('maria@test.invalid'));
    }

    public function test_converting_a_pre_registration_without_a_course_sends_no_welcome_mail(): void
    {
        Mail::fake();
        $preRegistration = PreRegistration::factory()->create();

        $this->actingAs($this->admin())
            ->post(
                route('admin.pre-registrations.enroll.store', ['preRegistration' => $preRegistration->id]),
                $this->studentPayload('maria@test.invalid', null),
            )
            ->assertSessionHasNoErrors();

        Mail::assertNotSent(EnrollmentWelcomeMail::class);
    }

    public function test_enrolling_from_payments_sends_one_welcome_mail(): void
    {
        Mail::fake();
        $course = Course::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.payments.enroll.store'), $this->studentPayload('carlos@test.invalid', $course->id))
            ->assertSessionHasNoErrors();

        Mail::assertSent(EnrollmentWelcomeMail::class, 1);
        Mail::assertSent(EnrollmentWelcomeMail::class, fn (EnrollmentWelcomeMail $mail): bool => $mail->hasTo('carlos@test.invalid'));
    }

    public function test_welcome_mail_shows_the_course_schedule_place_teacher_and_review_link(): void
    {
        $course = Course::factory()->create();
        $course->level->danceType->update(['name' => 'Salsa casino']);
        $course->level->update(['name' => 'Básico 1']);
        $course->place->update(['name' => 'Sala Habana', 'address' => 'Av. Principal 12']);
        $course->teacher->update(['name' => 'Yaniel Rodríguez']);
        $course->scheduleSlots()->delete();
        CourseScheduleSlot::factory()->for($course)->create(['weekday' => 1, 'starts_at' => '18:00:00', 'ends_at' => '19:30:00', 'sort_order' => 0]);
        CourseScheduleSlot::factory()->for($course)->create(['weekday' => 3, 'starts_at' => '19:30:00', 'ends_at' => '21:00:00', 'sort_order' => 1]);
        $enrollment = Enrollment::factory()->for($course)->create([
            'student_id' => Student::factory()->create(['name' => 'Ana Gómez'])->id,
        ]);

        $mail = new EnrollmentWelcomeMail($enrollment);

        $mail->assertHasSubject('¡Felicidades, ya estás inscrito en Cubanía!');
        $mail->assertSeeInHtml('Ana Gómez');
        $mail->assertSeeInHtml('Salsa casino');
        $mail->assertSeeInHtml('Básico 1');
        $mail->assertSeeInOrderInHtml(['Lunes de 18:00 a 19:30', 'Miércoles de 19:30 a 21:00']);
        $mail->assertSeeInHtml('Sala Habana');
        $mail->assertSeeInHtml('Av. Principal 12');
        $mail->assertSeeInHtml('Yaniel Rodríguez');
        $mail->assertSeeInHtml(url('/repaso'));
        $this->assertStringStartsWith('http', route('review-panel'));
    }

    public function test_student_without_email_is_enrolled_without_a_welcome_mail(): void
    {
        Mail::fake();
        $course = Course::factory()->create();
        $student = Student::factory()->create(['email' => '']);

        app(EnrollStudentInCourse::class)->execute($student->id, $course->id);

        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'course_id' => $course->id]);
        Mail::assertNothingSent();
    }

    public function test_mail_failure_keeps_the_enrollment_and_is_reported(): void
    {
        Exceptions::fake();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));
        $admin = $this->admin();
        $course = Course::factory()->create(['company_id' => $admin->company_id]);
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.students.enroll.store'), [
                'course_id' => $course->id,
                'student_id' => $student->id,
            ])
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'course_id' => $course->id]);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'SMTP caído');
    }

    public function test_welcome_mail_is_not_sent_when_the_transaction_rolls_back(): void
    {
        Mail::fake();
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        try {
            DB::transaction(function () use ($course, $student): void {
                app(EnrollStudentInCourse::class)->execute($student->id, $course->id);

                Mail::assertNothingSent();

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // The rollback is the point of this test.
        }

        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id]);
        Mail::assertNothingSent();
    }

    private function admin(): User
    {
        $role = Role::factory()->create(['slug' => 'admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @return array<string, int|string>
     */
    private function studentPayload(string $email, ?int $courseId): array
    {
        return [
            'name' => 'Alumno Nuevo',
            'email' => $email,
            'phone' => '',
            'dni' => '',
            'birthday' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'course_id' => $courseId ?? '',
        ];
    }
}
