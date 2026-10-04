<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Review\ReviewSessionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Formatting shared by the notification emails (Spanish, academy timezone).
 */
final class NotificationFormat
{
    public static function amount(mixed $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    /**
     * Date typed by the staff in a form (payment date, session start): the
     * form sends local wall time and it is stored as is, so it is shown as is.
     */
    public static function dateTime(?CarbonInterface $moment): string
    {
        if ($moment === null) {
            return 'No indicada';
        }

        return $moment->format('d/m/Y H:i');
    }

    /**
     * System timestamp (created_at and the like), stored in UTC: shown in the
     * academy timezone.
     */
    public static function localDateTime(?CarbonInterface $moment): string
    {
        if ($moment === null) {
            return 'No indicada';
        }

        return CarbonImmutable::instance($moment)
            ->setTimezone(ReviewSessionService::academyTimezone())
            ->format('d/m/Y H:i');
    }

    public static function sessionType(?string $type): string
    {
        return match ($type) {
            'workshop' => 'Taller',
            'private_class' => 'Clase personalizada',
            'event' => 'Evento',
            default => 'Sesión',
        };
    }

    public static function paymentMethod(?string $method): string
    {
        return match ($method) {
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'otro' => 'Otro',
            default => 'No indicado',
        };
    }
}
