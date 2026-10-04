<?php

namespace App\Http\Middleware;

use App\Actions\SendPaymentReminders;
use App\Support\BillingPeriod;
use App\Support\SafeNotifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * "Poor man's cron" for the monthly payment reminders: the host has no cron
 * and no queue worker, so web visits during the reminder days kick off a small
 * batch after the response has been sent. Outside those days it does nothing
 * but a date check.
 */
class TriggerPaymentReminders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->scheduleRunIfDue();

        return $next($request);
    }

    private function scheduleRunIfDue(): void
    {
        /** @var array{auto: bool, send_from_hour: int, batch_size: int, throttle_minutes: int} $config */
        $config = config('cubania.notifications.payment_reminders');

        if (! $config['auto'] || BillingPeriod::reminderKind() === null) {
            return;
        }

        if (BillingPeriod::localNow()->hour < $config['send_from_hour']) {
            return;
        }

        try {
            $isFirstVisitOfTheSlot = Cache::add(
                'payment-reminders:tick',
                true,
                now()->addMinutes(max(1, $config['throttle_minutes'])),
            );
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if (! $isFirstVisitOfTheSlot) {
            return;
        }

        defer(
            fn () => SafeNotifier::attempt(
                fn () => app(SendPaymentReminders::class)->execute($config['batch_size']),
            ),
            always: true,
        );
    }
}
