<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Exceptions\Review\ReviewSessionNotCompletableException;
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
        $session = $this->sessionService->createOrResume($student);

        return ReviewSessionResource::make($this->withProgress($session))
            ->response()
            ->setStatusCode($session->wasRecentlyCreated ? 201 : 200);
    }

    public function current(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        $session = $this->sessionService->findActiveSession($student);

        if ($session === null) {
            $this->sessionService->closeExpiredIncompleteSessions($student);

            if ($this->sessionService->hasStartedToday($student)) {
                return response()->json([
                    'message' => 'Ya usaste tu repaso de hoy. Vuelve mañana.',
                    'locked' => true,
                ], 409);
            }

            return response()->json([
                'message' => 'Todavía no empezaste el repaso de hoy.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => ReviewSessionResource::make($this->withProgress($session)),
        ]);
    }

    public function show(Request $request, ReviewSession $session): ReviewSessionResource
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);

        return ReviewSessionResource::make($this->withProgress($session));
    }

    public function figureOptions(Request $request, ReviewSession $session): AnonymousResourceCollection
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        return LevelContentResource::collection(
            $this->figureSelectionService->optionsForSession($session, $student)
        );
    }

    public function storeFigures(
        StoreReviewSessionFiguresRequest $request,
        ReviewSession $session,
    ): JsonResponse {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        $this->figureSelectionService->optionsForSession($session, $student);
        $this->figureSelectionService->storeSelectedFigures(
            $session,
            $request->validated('level_content_ids'),
        );

        return response()->json([
            'message' => 'Figuras guardadas.',
            'data' => LevelContentResource::collection($this->figureSelectionService->selectedFigures($session)),
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

        if ($session->selectedFigures()->whereKey($content->id)->doesntExist()) {
            abort(404);
        }

        $this->figureSelectionService->recordView($student, $content);

        return response()->json([
            'message' => 'Figura registrada.',
        ]);
    }

    public function songs(Request $request, ReviewSession $session): AnonymousResourceCollection
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);

        if ($session->songs()->doesntExist()) {
            $this->songRecommendationService->assignToSession($session);
        }

        $session->load('songs');

        return RecommendedSongResource::collection($session->songs);
    }

    /**
     * Finishing is allowed after the timer runs out (the 30 minutes are a window, not a
     * hard stop), but only once the student chose the figures to review (unless the
     * catalog had none to offer).
     */
    public function complete(Request $request, ReviewSession $session): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);

        if (! $this->figureSelectionService->canComplete($session)) {
            throw ReviewSessionNotCompletableException::missingFigures();
        }

        $wasAlreadyCompleted = $session->completed_at !== null;
        $session = $this->sessionService->markCompleted($session);

        $streak = $wasAlreadyCompleted
            ? $this->streakService->getOrCreate($student)
            : $this->streakService->recordCompletion($student);

        return response()->json([
            'data' => [
                'session' => ReviewSessionResource::make($this->withProgress($session)),
                'streak' => StudentStreakResource::make($streak),
            ],
        ]);
    }
}
