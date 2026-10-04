<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use App\Notifications\PaymentRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentNotificationTest extends TestCase
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

    private function activeEnrollment(): Enrollment
    {
        return Enrollment::factory()->create(['status' => EnrollmentStatus::Active]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function paymentPayload(Enrollment $enrollment, array $overrides = []): array
    {
        return [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '35.00',
            'method' => 'efectivo',
            'period' => '2026-11',
            ...$overrides,
        ];
    }

    public function test_payments_staff_and_the_student_are_notified(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin_staff');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $enrollment = $this->activeEnrollment();

        $this->actingAs($actor)
            ->post(route('admin.payments.store'), $this->paymentPayload($enrollment))
            ->assertRedirect();

        Notification::assertSentTo($owner, PaymentRecordedNotification::class);
        Notification::assertSentTo($admin, PaymentRecordedNotification::class);
        Notification::assertNotSentTo($actor, PaymentRecordedNotification::class);
        Notification::assertNotSentTo($teacher, PaymentRecordedNotification::class);
        Notification::assertSentTo($enrollment->student, PaymentReceiptNotification::class);
    }

    public function test_the_period_is_stored_and_described(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $enrollment = $this->activeEnrollment();

        $this->actingAs($actor)->post(route('admin.payments.store'), $this->paymentPayload($enrollment));

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('2026-11-01', $payment->period->toDateString());

        Notification::assertSentTo($owner, PaymentRecordedNotification::class, function (PaymentRecordedNotification $notification) use ($owner, $payment): bool {
            $payload = $notification->toDatabase($owner);
            $mail = $notification->toMail($owner)->render();

            return $payload['payment_id'] === $payment->id
                && $payload['period'] === '2026-11'
                && $payload['period_label'] === 'noviembre 2026'
                && $payload['amount'] === '35,00'
                && str_contains($mail, 'noviembre 2026')
                && str_contains($mail, e(route('admin.payments.index', ['month' => '2026-11'])));
        });

        Notification::assertSentTo($enrollment->student, PaymentReceiptNotification::class, function (PaymentReceiptNotification $notification) use ($enrollment): bool {
            $mail = $notification->toMail($enrollment->student)->render();

            return str_contains($mail, 'noviembre 2026') && str_contains($mail, '35,00');
        });
    }

    public function test_pending_payments_do_not_send_a_receipt(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin');
        $enrollment = $this->activeEnrollment();

        $this->actingAs($actor)->post(route('admin.payments.store'), $this->paymentPayload($enrollment, [
            'status' => PaymentStatus::Pending->value,
        ]));

        Notification::assertNotSentTo($enrollment->student, PaymentReceiptNotification::class);
    }

    public function test_period_must_be_a_year_and_month(): void
    {
        Notification::fake();

        $actor = $this->userWithRole('admin');
        $enrollment = $this->activeEnrollment();

        $this->actingAs($actor)
            ->post(route('admin.payments.store'), $this->paymentPayload($enrollment, ['period' => 'noviembre']))
            ->assertSessionHasErrors('period');

        $this->assertSame(0, Payment::query()->count());
        Notification::assertNothingSent();
    }

    public function test_without_period_the_default_is_next_month_in_the_last_days(): void
    {
        Notification::fake();
        $this->travelTo(now('America/Caracas')->setDate(2026, 10, 29)->setTime(12, 0));

        $actor = $this->userWithRole('admin');
        $enrollment = $this->activeEnrollment();

        $this->actingAs($actor)->post(route('admin.payments.store'), $this->paymentPayload($enrollment, ['period' => null]));

        $this->assertSame('2026-11-01', Payment::query()->firstOrFail()->period->toDateString());
    }

    public function test_payments_page_proposes_the_default_period(): void
    {
        $actor = $this->userWithRole('admin');

        $this->travelTo(now('America/Caracas')->setDate(2026, 10, 10)->setTime(12, 0));
        $this->actingAs($actor)->get(route('admin.payments.index'))
            ->assertInertia(fn (Assert $page) => $page->where('defaultPeriod', '2026-10'));

        $this->travelTo(now('America/Caracas')->setDate(2026, 10, 28)->setTime(12, 0));
        $this->actingAs($actor)->get(route('admin.payments.index'))
            ->assertInertia(fn (Assert $page) => $page->where('defaultPeriod', '2026-11'));

        $this->actingAs($actor)->get(route('admin.payments.index', ['month' => '2026-08']))
            ->assertInertia(fn (Assert $page) => $page->where('defaultPeriod', '2026-08'));
    }

    public function test_month_column_uses_the_covered_period_and_ignores_cancelled_payments(): void
    {
        $actor = $this->userWithRole('admin');
        $paidAhead = $this->activeEnrollment();
        $cancelled = $this->activeEnrollment();

        Payment::factory()->create([
            'student_id' => $paidAhead->student_id,
            'course_id' => $paidAhead->course_id,
            'period' => '2026-11-01',
            'paid_at' => '2026-10-28 10:00:00',
            'status' => PaymentStatus::Paid,
        ]);
        Payment::factory()->create([
            'student_id' => $cancelled->student_id,
            'course_id' => $cancelled->course_id,
            'period' => '2026-11-01',
            'paid_at' => '2026-11-02 10:00:00',
            'status' => PaymentStatus::Cancelled,
        ]);

        $this->actingAs($actor)->get(route('admin.payments.index', ['month' => '2026-11']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.student_id', $paidAhead->student_id)
                ->whereNot('rows.0.last_payment_in_selected_month_at', null)
                ->where('rows.1.student_id', $cancelled->student_id)
                ->where('rows.1.last_payment_in_selected_month_at', null));

        $this->actingAs($actor)->get(route('admin.payments.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.last_payment_in_selected_month_at', null));
    }
}
