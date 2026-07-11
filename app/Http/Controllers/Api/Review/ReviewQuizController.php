<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreQuizAnswerRequest;
use App\Http\Resources\Review\QuizItemResource;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewSession;
use App\Services\Review\QuizGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewQuizController extends Controller
{
    use InteractsWithReviewSessions;

    public function __construct(
        private readonly QuizGeneratorService $quizGeneratorService,
    ) {}

    public function next(Request $request, ReviewSession $session): JsonResponse
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);
        $this->ensureSessionIsActive($session);

        $question = $this->quizGeneratorService->nextQuestion($session);

        if ($question === null) {
            return response()->json([
                'message' => 'No quiz questions are available for this session.',
            ], 404);
        }

        return response()->json([
            'data' => QuizItemResource::make($question),
        ]);
    }

    public function answer(
        StoreQuizAnswerRequest $request,
        ReviewSession $session,
        QuizItem $item,
    ): JsonResponse {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);
        $this->ensureSessionIsActive($session);

        if ($item->level_id !== null && $item->level_id !== $session->level_id) {
            abort(404);
        }

        $quizOption = QuizOption::query()->findOrFail($request->integer('quiz_option_id'));

        $response = $this->quizGeneratorService->recordAnswer($session, $item, $quizOption);

        return response()->json([
            'data' => [
                'is_correct' => $response->is_correct,
                'answered_at' => $response->answered_at?->toIso8601String(),
            ],
        ]);
    }
}
