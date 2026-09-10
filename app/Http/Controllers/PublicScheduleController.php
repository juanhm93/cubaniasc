<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildPublicWeeklySchedule;
use Illuminate\Http\JsonResponse;

final class PublicScheduleController extends Controller
{
    public function __invoke(BuildPublicWeeklySchedule $build): JsonResponse
    {
        return response()->json($build->execute());
    }
}
