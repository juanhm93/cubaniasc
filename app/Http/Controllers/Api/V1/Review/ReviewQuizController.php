<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Review;

use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreQuizAnswerRequest;
use App\Http\Resources\V1\Review\QuizQuestionResource;
use App\Models\QuizItem;
use App\Models\ReviewSession;
use App\Services\Review\QuizGeneratorService;
use App\Services\Review\ReviewSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewQuizController extends Controller
{
    use InteractsWithReviewSessions;

    public function __construct(
        private readonly QuizGeneratorService $quizGeneratorService,
    ) {}

    /**
     * Next question, or `data: null` when the quiz of this session is over (the
     * question limit was reached or the pool ran out).
     */
    public function next(Request $request, ReviewSession $session): JsonResponse
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);
        $this->ensureSessionIsActive($session);

        $question = $this->quizGeneratorService->nextQuestion($session);

        return response()->json([
            'data' => $question === null ? null : QuizQuestionResource::make($question),
        ]);
    }

    public function answer(StoreQuizAnswerRequest $request, ReviewSession $session, QuizItem $item): JsonResponse
    {
        $this->authorizeReviewSession($this->authenticatedStudent($request), $session);
        $this->ensureSessionIsActive($session);

        if (! $this->quizGeneratorService->belongsToSessionPool($session, $item)) {
            abort(404);
        }

        $option = $this->quizGeneratorService->findOption($item, $request->integer('quiz_option_id'))
            ?? abort(404);

        $response = $this->quizGeneratorService->recordAnswer($session, $item, $option);

        return response()->json([
            'data' => [
                'is_correct' => $response->is_correct,
                'correct_option_id' => $this->quizGeneratorService->correctOptionId($item),
                'answered_at' => $response->answered_at?->toIso8601String(),
                'answered_count' => $this->quizGeneratorService->answeredCount($session),
                'quiz_max' => ReviewSessionService::MAX_QUIZ_QUESTIONS,
            ],
        ]);
    }
}
