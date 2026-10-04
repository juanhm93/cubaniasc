<?php

namespace Tests\Feature;

use App\Actions\SendPaymentReminders;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PaymentReminderNotification;
use App\Notifications\PaymentRemindersSentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    private function travelToLocal(int $month, int $day, int $hour = 12): void
    {
        $this->travelTo(CarbonImmutable::create(2026, $month, $day, $hour, 0, 0, 'America/Caracas'));
    }

    private function enrolledStudent(?Course $course = null): Student
    {
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => ($course ?? Course::factory()->create(['is_active' => true]))->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }

    private function sendReminders(int $limit = 50): int
    {
        return app(SendPaymentReminders::class)->execute($limit);
    }

    public function test_nothing_is_sent_before_the_last_five_days(): void
    {
        $student = $this->enrolledStudent();
        $this->travelToLocal(10, 26);

        $this->assertSame(0, $this->sendReminders());

        Notification::assertNotSentTo($student, PaymentReminderNotification::class);
    }

    public function test_window_reminder_goes_out_once_for_next_month(): void
    {
        $student = $this->enrolledStudent();
        $this->travelToLocal(10, 27);

        $this->assertSame(1, $this->sendReminders());
        $this->assertSame(0, $this->sendReminders());

        Notification::assertSentToTimes($student, PaymentReminderNotification::class, 1);
        Notification::assertSentTo($student, PaymentReminderNotification::class, function (PaymentReminderNotification $notification) use ($student): bool {
            $mail = $notification->toMail($student)->render();

            return $notification->kind === 'window'
                && $notification->period->toDateString() === '2026-11-01'
                && str_contains($mail, 'noviembre 2026');
        });

        $this->assertDatabaseHas('payment_reminders', [
            'student_id' => $student->id,
            'period' => '2026-11-01',
            'kind' => 'window',
        ]);
        $this->assertNotNull(DB::table('payment_reminders')->value('sent_at'));
    }

    public function test_last_day_reminder_is_sent_after_the_window_one(): void
    {
        $student = $this->enrolledStudent();

        $this->travelToLocal(10, 28);
        $this->sendReminders();

        $this->travelToLocal(10, 31);
        $this->sendReminders();
        $this->sendReminders();

        Notification::assertSentToTimes($student, PaymentReminderNotification::class, 2);
        Notification::assertSentTo($student, PaymentReminderNotification::class, fn (PaymentReminderNotification $notification): bool => $notification->kind === 'last_day');
    }

    public function test_when_nobody_visited_during_the_window_only_the_last_day_reminder_is_sent(): void
    {
        $student = $this->enrolledStudent();
        $this->travelToLocal(10, 31);

        $this->sendReminders();

        Notification::assertSentToTimes($student, PaymentReminderNotification::class, 1);
        $this->assertDatabaseMissing('payment_reminders', ['kind' => 'window']);
    }

    public function test_students_who_already_paid_next_month_are_skipped(): void
    {
        $course = Course::factory()->create(['is_active' => true]);
        $paid = $this->enrolledStudent($course);
        $cancelled = $this->enrolledStudent($course);

        Payment::factory()->create([
            'student_id' => $paid->id,
            'course_id' => $course->id,
            'period' => '2026-11-01',
            'status' => PaymentStatus::Paid,
        ]);
        Payment::factory()->create([
            'student_id' => $cancelled->id,
            'course_id' => $course->id,
            'period' => '2026-11-01',
            'status' => PaymentStatus::Cancelled,
        ]);

        $this->travelToLocal(10, 27);
        $this->sendReminders();

        Notification::assertNotSentTo($paid, PaymentReminderNotification::class);
        Notification::assertSentTo($cancelled, PaymentReminderNotification::class);
    }

    public function test_inactive_courses_and_withdrawn_enrollments_are_skipped(): void
    {
        $inactiveCourseStudent = $this->enrolledStudent(Course::factory()->create(['is_active' => false]));
        $withdrawn = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $withdrawn->id,
            'course_id' => Course::factory()->create(['is_active' => true])->id,
            'status' => EnrollmentStatus::Withdrawn,
        ]);

        $this->travelToLocal(10, 27);
        $this->sendReminders();

        Notification::assertNothingSentTo($inactiveCourseStudent);
        Notification::assertNothingSentTo($withdrawn);
    }

    public function test_one_email_lists_every_unpaid_course_of_the_student(): void
    {
        $student = $this->enrolledStudent(Course::factory()->create(['is_active' => true, 'price' => 30]));
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create(['is_active' => true, 'price' => 45])->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->travelToLocal(10, 27);
        $this->sendReminders();

        Notification::assertSentToTimes($student, PaymentReminderNotification::class, 1);
        Notification::assertSentTo($student, PaymentReminderNotification::class, function (PaymentReminderNotification $notification) use ($student): bool {
            $mail = $notification->toMail($student)->render();

            return $notification->courses->count() === 2 && str_contains($mail, '75,00');
        });
    }

    public function test_batches_are_limited_and_the_staff_summary_waits_for_the_last_one(): void
    {
        $owner = User::factory()->create([
            'status' => 'active',
            'is_owner' => 1,
            'role_id' => Role::factory()->create(['slug' => 'staff'])->id,
        ]);
        $students = collect(range(1, 3))->map(fn () => $this->enrolledStudent());

        $this->travelToLocal(10, 27);

        $this->assertSame(2, $this->sendReminders(2));
        Notification::assertNotSentTo($owner, PaymentRemindersSentNotification::class);

        $this->assertSame(1, $this->sendReminders(2));
        $this->assertSame(0, $this->sendReminders(2));

        foreach ($students as $student) {
            Notification::assertSentToTimes($student, PaymentReminderNotification::class, 1);
        }

        Notification::assertSentToTimes($owner, PaymentRemindersSentNotification::class, 1);
        Notification::assertSentTo($owner, PaymentRemindersSentNotification::class, fn (PaymentRemindersSentNotification $notification): bool => $notification->count === 3
            && $notification->toDatabase($owner)['period'] === '2026-11');
    }

    public function test_web_visits_trigger_the_reminders_when_enabled(): void
    {
        config(['cubania.notifications.payment_reminders.auto' => true]);
        $student = $this->enrolledStudent();
        $this->travelToLocal(10, 27, 10);

        $this->get(route('home'))->assertOk();

        Notification::assertSentTo($student, PaymentReminderNotification::class);
    }

    public function test_web_visits_do_nothing_before_the_sending_hour_or_when_disabled(): void
    {
        $student = $this->enrolledStudent();

        config(['cubania.notifications.payment_reminders.auto' => true]);
        $this->travelToLocal(10, 27, 7);
        $this->get(route('home'))->assertOk();

        config(['cubania.notifications.payment_reminders.auto' => false]);
        $this->travelToLocal(10, 27, 12);
        $this->get(route('home'))->assertOk();

        Notification::assertNotSentTo($student, PaymentReminderNotification::class);
    }

    public function test_the_artisan_command_sends_the_due_reminders(): void
    {
        $student = $this->enrolledStudent();
        $this->travelToLocal(10, 27);

        $this->artisan('cubania:send-payment-reminders')
            ->expectsOutputToContain('Recordatorios de pago enviados: 1.')
            ->assertSuccessful();

        Notification::assertSentTo($student, PaymentReminderNotification::class);
    }
}
