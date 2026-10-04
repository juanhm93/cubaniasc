<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Delivery helpers shared by every notification of the platform.
 *
 * Production has no queue worker, so notifications are never queued. Instead
 * they run in the same PHP process, after the response has been flushed to
 * the browser ({@see self::afterResponse()}), so the person saving a form does
 * not wait for the mail server.
 *
 * A saved record is worth more than a delivered email: every failure is
 * reported and swallowed so it can never break the action that triggered it.
 */
final class SafeNotifier
{
    /**
     * Run the delivery once the response has been sent. Laravel only runs
     * deferred callbacks for successful responses (status below 400).
     */
    public static function afterResponse(callable $delivery): void
    {
        defer(fn () => self::attempt($delivery));
    }

    /**
     * Notify recipients one by one so a single failure cannot cost the rest
     * their notification.
     *
     * @param  iterable<object>  $notifiables  Models using the Notifiable trait.
     */
    public static function notifyEach(iterable $notifiables, Notification $notification): void
    {
        foreach ($notifiables as $notifiable) {
            self::attempt(fn () => $notifiable->notify($notification));
        }
    }

    /**
     * Run one delivery step, reporting instead of throwing. Returns whether it
     * went through.
     */
    public static function attempt(callable $stage): bool
    {
        try {
            $stage();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
