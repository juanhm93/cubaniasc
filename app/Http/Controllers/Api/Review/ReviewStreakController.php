<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Review\StudentStreakResource;
use App\Services\Review\StreakService;
use Illuminate\Http\Request;

final class ReviewStreakController extends Controller
{
    use InteractsWithReviewSessions;

    public function __invoke(Request $request, StreakService $streakService): StudentStreakResource
    {
        $student = $this->authenticatedStudent($request);

        return StudentStreakResource::make($streakService->getOrCreate($student));
    }
}
