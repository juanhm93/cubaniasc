<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Review;

use App\Actions\Review\CompleteReviewSession;
use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewSessionFiguresRequest;
use App\Http\Resources\V1\Review\FigureResource;
use App\Http\Resources\V1\Review\ReviewSessionResource;
use App\Http\Resources\V1\Review\ReviewStreakResource;
use App\Http\Resources\V1\Review\SongResource;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Services\Review\FigureSelectionService;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\SongRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Review session for the mobile app. Same rules as the web panel: they share the
 * services and repositories, only the response format is versioned here.
 */
final class ReviewSessionController extends Controller
{
    use InteractsWithReviewSessions;

    public function __construct(
        private readonly ReviewSessionService $sessionService,
        private readonly FigureSelectionService $figureSelectionService,
        private readonly SongRecommendationService $songRecommendationService,
    ) {}

    /**
     * Starts today's session, or resumes the one in progress (200 instead of 201).
     */
    public function store(Request $request): JsonResponse
    {
        $session = $this->sessionService->createOrResume($this->authenticatedStudent($request));

        return ReviewSessionResource::make($this->withProgress($session))
            ->response()
            ->setStatusCode($session->wasRecentlyCreated ? 201 : 200);
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

        return FigureResource::collection($this->figureSelectionService->optionsForSession($session, $student));
    }

    public function storeFigures(StoreReviewSessionFiguresRequest $request, ReviewSession $session): AnonymousResourceCollection
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        return FigureResource::collection($this->figureSelectionService->selectForSession(
            $session,
            $student,
            $request->validated('level_content_ids'),
        ));
    }

    /**
     * Records that the student reviewed one of the figures chosen for the session.
     */
    public function recordFigureView(Request $request, ReviewSession $session, LevelContent $figure): Response
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        if (! $this->figureSelectionService->isSelected($session, $figure)) {
            abort(404);
        }

        $this->figureSelectionService->recordView($student, $figure);

        return response()->noContent();
    }

    public function songs(Request $request, ReviewSession $session): AnonymousResourceCollection
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);

        return SongResource::collection($this->songRecommendationService->forSession($session));
    }

    /**
     * Allowed after the timer runs out; see {@see CompleteReviewSession}.
     */
    public function complete(Request $request, ReviewSession $session, CompleteReviewSession $completeReviewSession): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);

        $result = $completeReviewSession->execute($session, $student);

        return response()->json([
            'data' => [
                'session' => ReviewSessionResource::make($this->withProgress($result['session'])),
                'streak' => ReviewStreakResource::make($result['streak']),
            ],
        ]);
    }
}
