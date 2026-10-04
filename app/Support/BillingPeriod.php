<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Review\ReviewSessionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Monthly billing calendar measured in the academy's local timezone.
 *
 * A payment covers a "period": the first day of the month of classes it pays
 * for. Students pay ahead, so during the last {@see self::REMINDER_WINDOW_DAYS}
 * days of a month the period being collected is already the next one.
 */
final class BillingPeriod
{
    public const REMINDER_WINDOW_DAYS = 5;

    public const REMINDER_WINDOW = 'window';

    public const REMINDER_LAST_DAY = 'last_day';

    /**
     * Current moment in the academy timezone.
     */
    public static function localNow(?CarbonInterface $moment = null): CarbonImmutable
    {
        return CarbonImmutable::instance($moment ?? now())
            ->setTimezone(ReviewSessionService::academyTimezone());
    }

    public static function current(?CarbonInterface $moment = null): CarbonImmutable
    {
        return self::localNow($moment)->startOfMonth()->startOfDay();
    }

    public static function upcoming(?CarbonInterface $moment = null): CarbonImmutable
    {
        return self::current($moment)->addMonthNoOverflow();
    }

    /**
     * Whether we are in the last days of the month, when next month is collected.
     */
    public static function isInReminderWindow(?CarbonInterface $moment = null): bool
    {
        return self::reminderKind($moment) !== null;
    }

    /**
     * Which reminder is due today: the window one (the days before the last
     * one) or the last-day one. Null outside the reminder window.
     */
    public static function reminderKind(?CarbonInterface $moment = null): ?string
    {
        $today = self::localNow($moment);
        $lastDay = $today->daysInMonth;

        if ($today->day === $lastDay) {
            return self::REMINDER_LAST_DAY;
        }

        return $today->day > $lastDay - self::REMINDER_WINDOW_DAYS
            ? self::REMINDER_WINDOW
            : null;
    }

    /**
     * Period a new payment most likely covers: next month inside the reminder
     * window, the current month otherwise.
     */
    public static function defaultForNewPayment(?CarbonInterface $moment = null): CarbonImmutable
    {
        return self::isInReminderWindow($moment)
            ? self::upcoming($moment)
            : self::current($moment);
    }

    /**
     * First day of the month from a "Y-m" key.
     */
    public static function fromKey(string $key): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $key, ReviewSessionService::academyTimezone())
            ->startOfMonth();
    }

    public static function key(CarbonInterface $period): string
    {
        return $period->format('Y-m');
    }

    /**
     * "noviembre 2026".
     */
    public static function label(CarbonInterface $period): string
    {
        return CarbonImmutable::instance($period)->locale('es')->translatedFormat('F Y');
    }
}
