<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Review\ReviewFigureRepository;
use App\Repositories\Review\ReviewSessionRepository;
use App\Services\Review\ReviewSessionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Leaves students as if they had not started today's review, so it can be tested again
 * without waiting for the next local day. The streak is left alone: finishing again on
 * the same day does not grow it.
 */
#[Signature('review:reset-today {--student= : ID del alumno; sin esto aplica a todos}')]
#[Description('Borra los repasos de hoy para volver a probarlos (solo en local)')]
final class ResetTodayReviews extends Command
{
    public function handle(ReviewSessionRepository $sessions, ReviewFigureRepository $figures): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo se puede usar en local.');

            return self::FAILURE;
        }

        $studentOption = $this->option('student');
        $studentId = $studentOption === null ? null : (int) $studentOption;

        [$startOfDay, $endOfDay] = ReviewSessionService::localDayBounds();

        $deletedSessions = $sessions->deleteStartedBetween($startOfDay, $endOfDay, $studentId);
        $deletedViews = $figures->deleteViewsBetween($startOfDay, $endOfDay, $studentId);

        $this->info("Sesiones de repaso borradas: {$deletedSessions}. Vistas de figuras borradas: {$deletedViews}.");

        return self::SUCCESS;
    }
}
