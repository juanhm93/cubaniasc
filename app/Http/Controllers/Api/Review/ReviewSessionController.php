<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewSessionFiguresRequest;
use App\Http\Resources\Review\LevelContentResource;
use App\Http\Resources\Review\RecommendedSongResource;
use App\Http\Resources\Review\ReviewSessionResource;
use App\Http\Resources\Review\StudentStreakResource;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Services\Review\FigureSelectionService;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\SongRecommendationService;
use App\Services\Review\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReviewSessionController extends Controller
{
    use InteractsWithReviewSessions;

    public function __construct(
        private readonly ReviewSessionService $sessionService,
        private readonly FigureSelectionService $figureSelectionService,
        private readonly SongRecommendationService $songRecommendationService,
        private readonly StreakService $streakService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        $session = $this->sessionService->create($student);
        $session->load('level');

        return ReviewSessionResource::make($session)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ReviewSession $session): ReviewSessionResource
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);
        $session->load('level');

        return ReviewSessionResource::make($session);
    }

    public function figureOptions(Request $request, ReviewSession $session): AnonymousResourceCollection
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        $session->loadMissing('level');
        $figures = $this->figureSelectionService->getSelectableFigures($student, $session->level);

        return LevelContentResource::collection($figures);
    }

    public function storeFigures(
        StoreReviewSessionFiguresRequest $request,
        ReviewSession $session,
    ): JsonResponse {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        $session->loadMissing('level');
        $allowedIds = $this->figureSelectionService
            ->getSelectableFigures($student, $session->level)
            ->pluck('id')
            ->all();

        $this->figureSelectionService->storeSelectedFigures(
            $session,
            $request->validated('level_content_ids'),
            $allowedIds,
        );

        return response()->json([
            'message' => 'Figures stored successfully.',
        ]);
    }

    public function recordFigureView(
        Request $request,
        ReviewSession $session,
        LevelContent $content,
    ): JsonResponse {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        if ($content->level_id !== $session->level_id) {
            abort(404);
        }

        $this->figureSelectionService->recordView($student, $content);

        return response()->json([
            'message' => 'Figure view recorded successfully.',
        ]);
    }

    public function songs(Request $request, ReviewSession $session): AnonymousResourceCollection
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);
        $this->ensureSessionIsActive($session);

        if ($session->songs()->doesntExist()) {
            $this->songRecommendationService->assignToSession($session);
        }

        $session->load('songs');

        return RecommendedSongResource::collection($session->songs);
    }

    public function complete(Request $request, ReviewSession $session): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        $session = $this->sessionService->markCompleted($session);
        $streak = $this->streakService->recordCompletion($student);
        $session->load('level');

        return response()->json([
            'data' => [
                'session' => ReviewSessionResource::make($session),
                'streak' => StudentStreakResource::make($streak),
            ],
        ]);
    }
}
