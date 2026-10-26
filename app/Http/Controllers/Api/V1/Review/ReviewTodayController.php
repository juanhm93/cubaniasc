<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Review;

use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Review\ReviewLevelResource;
use App\Http\Resources\V1\Review\ReviewSessionResource;
use App\Http\Resources\V1\Review\ReviewStreakResource;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\StreakService;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home of the review in the app: whether today's review is available, in progress or
 * already used, plus the level and the streak.
 */
final class ReviewTodayController extends Controller
{
    use InteractsWithReviewSessions;

    public function __construct(
        private readonly ReviewSessionService $sessionService,
        private readonly StudentLevelResolver $levelResolver,
        private readonly StreakService $streakService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        $level = $this->levelResolver->resolveLevel($student);
        $today = $this->sessionService->todayFor($student);

        return response()->json([
            'data' => [
                'status' => $today['status']->value,
                'level' => ReviewLevelResource::make($level),
                'session' => $today['session'] === null
                    ? null
                    : ReviewSessionResource::make($this->withProgress($today['session'])),
                'streak' => ReviewStreakResource::make($this->streakService->getOrCreate($student)),
            ],
        ]);
    }
}
