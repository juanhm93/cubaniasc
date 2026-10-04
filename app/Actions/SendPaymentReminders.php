<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformAbility;
use App\Models\Enrollment;
use App\Notifications\PaymentReminderNotification;
use App\Notifications\PaymentRemindersSentNotification;
use App\Support\BillingPeriod;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Emails the monthly payment reminders that are due today.
 *
 * Safe to run as often as wanted: every reminder is first claimed in the
 * payment_reminders table (unique per student, period and kind), so a student
 * never gets the same reminder twice. Runs are small batches so a request on
 * shared hosting never has to send hundreds of emails at once.
 *
 * Catch-up is built in: if nobody visited the site on the first window day,
 * the window reminder goes out on the next visit; on the last day of the month
 * only the last-day reminder is sent.
 */
final class SendPaymentReminders
{
    /**
     * @return int Students reminded in this run.
     */
    public function execute(int $limit): int
    {
        $kind = BillingPeriod::reminderKind();

        if ($kind === null) {
            return 0;
        }

        $lock = Cache::lock('payment-reminders', 300);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->sendBatch($kind, BillingPeriod::upcoming(), max(1, $limit));
        } finally {
            $lock->release();
        }
    }

    private function sendBatch(string $kind, CarbonImmutable $period, int $limit): int
    {
        $pendingByStudent = $this->pendingEnrollmentsByStudent($kind, $period);
        $sent = 0;

        foreach ($pendingByStudent->take($limit) as $studentId => $enrollments) {
            $claimed = DB::table('payment_reminders')->insertOrIgnore([
                'student_id' => $studentId,
                'period' => $period->toDateString(),
                'kind' => $kind,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($claimed === 0) {
                continue;
            }

            $student = $enrollments->first()->student;
            $notification = new PaymentReminderNotification($enrollments->pluck('course'), $period, $kind);

            if (SafeNotifier::attempt(fn () => $student->notify($notification))) {
                DB::table('payment_reminders')
                    ->where('student_id', $studentId)
                    ->where('period', $period->toDateString())
                    ->where('kind', $kind)
                    ->update(['sent_at' => now(), 'updated_at' => now()]);

                $sent++;
            }
        }

        if ($pendingByStudent->count() <= $limit) {
            $this->summarizeForStaffOnce($kind, $period);
        }

        return $sent;
    }

    /**
     * Active enrollments in active courses with no paid payment for the period
     * and no reminder of this kind yet, grouped by student.
     *
     * @return Collection<int, Collection<int, Enrollment>>
     */
    private function pendingEnrollmentsByStudent(string $kind, CarbonImmutable $period): Collection
    {
        return Enrollment::query()
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('course', fn ($query) => $query->where('is_active', true))
            ->whereHas('student')
            ->whereNotExists(function (Builder $query) use ($period): void {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.student_id', 'enrollments.student_id')
                    ->whereColumn('payments.course_id', 'enrollments.course_id')
                    ->whereNull('payments.deleted_at')
                    ->whereDate('payments.period', $period->toDateString())
                    ->where('payments.status', PaymentStatus::Paid->value);
            })
            ->whereNotExists(function (Builder $query) use ($kind, $period): void {
                $query->selectRaw('1')
                    ->from('payment_reminders')
                    ->whereColumn('payment_reminders.student_id', 'enrollments.student_id')
                    ->where('payment_reminders.period', $period->toDateString())
                    ->where('payment_reminders.kind', $kind);
            })
            ->with(['student', 'course.level.danceType'])
            ->orderBy('student_id')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');
    }

    /**
     * One bell summary per round, once every pending student was handled.
     */
    private function summarizeForStaffOnce(string $kind, CarbonImmutable $period): void
    {
        $sentCount = DB::table('payment_reminders')
            ->where('period', $period->toDateString())
            ->where('kind', $kind)
            ->whereNotNull('sent_at')
            ->count();

        if ($sentCount === 0) {
            return;
        }

        $summaryKey = 'payment-reminders:summary:'.$period->toDateString().':'.$kind;

        if (! Cache::add($summaryKey, true, now()->addDays(40))) {
            return;
        }

        SafeNotifier::notifyEach(
            NotificationRecipients::withAbility(PlatformAbility::Payments),
            new PaymentRemindersSentNotification($period, $kind, $sentCount),
        );
    }
}
