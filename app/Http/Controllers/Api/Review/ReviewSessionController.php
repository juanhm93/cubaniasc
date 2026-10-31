<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Actions\Review\CompleteReviewSession;
use App\Enums\FigureVideoUrlPurpose;
use App\Enums\ReviewTodayStatus;
use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewSessionFiguresRequest;
use App\Http\Resources\Review\LevelContentResource;
use App\Http\Resources\Review\RecommendedSongResource;
use App\Http\Resources\Review\ReviewSessionResource;
use App\Http\Resources\Review\StudentStreakResource;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Services\Content\FigureVideoStorage;
use App\Services\Review\FigureSelectionService;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\SongRecommendationService;
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
        $today = $this->sessionService->todayFor($this->authenticatedStudent($request));

        if ($today['status'] === ReviewTodayStatus::Locked) {
            return response()->json([
                'message' => 'Ya usaste tu repaso de hoy. Vuelve mañana.',
                'locked' => true,
            ], 409);
        }

        if ($today['session'] === null) {
            return response()->json([
                'message' => 'Todavía no empezaste el repaso de hoy.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => ReviewSessionResource::make($this->withProgress($today['session'])),
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

        $selected = $this->figureSelectionService->selectForSession(
            $session,
            $student,
            $request->validated('level_content_ids'),
        );

        return response()->json([
            'message' => 'Figuras guardadas.',
            'data' => LevelContentResource::collection($selected),
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

        if (! $this->figureSelectionService->isSelected($session, $content)) {
            abort(404);
        }

        $this->figureSelectionService->recordView($student, $content);

        return response()->json([
            'message' => 'Figura registrada.',
        ]);
    }

    /**
     * Signed URL to play the figure's MP4 ("Canal 2"). The review never offers a
     * download: the purpose is fixed and the URL lives no longer than the session.
     */
    public function figureVideoUrl(
        Request $request,
        ReviewSession $session,
        LevelContent $content,
        FigureVideoStorage $storage,
    ): JsonResponse {
        $student = $this->authenticatedStudent($request);
        $this->authorizeReviewSession($student, $session);
        $this->ensureSessionIsActive($session);

        if (! $this->figureSelectionService->isSelected($session, $content) || ! $content->hasVideoFile()) {
            abort(404);
        }

        $remainingMinutes = (int) ceil($this->sessionService->remainingSeconds($session) / 60);

        $signed = $storage->signedUrl(
            $content,
            FigureVideoUrlPurpose::Stream,
            max(1, min($remainingMinutes, (int) config('cubania.figure_videos.stream_url_minutes'))),
        );

        return response()->json([
            'data' => [
                'url' => $signed['url'],
                'expires_at' => $signed['expires_at']->toIso8601String(),
                'size_bytes' => $content->video_file_size_bytes,
                'version' => $content->video_file_version,
                'filename' => $signed['filename'],
            ],
        ]);
    }

    public function songs(Request $request, ReviewSession $session): AnonymousResourceCollection
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);

        return RecommendedSongResource::collection($this->songRecommendationService->forSession($session));
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
                'streak' => StudentStreakResource::make($result['streak']),
            ],
        ]);
    }
}
