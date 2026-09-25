<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\IdentifyStudentRequest;
use App\Http\Resources\Review\LevelResource;
use App\Http\Resources\Review\StudentResource;
use App\Services\Review\StudentIdentificationService;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Http\JsonResponse;

final class StudentIdentificationController extends Controller
{
    public const TOKEN_NAME = 'review-panel';

    /**
     * One active token per student; identifying again on another device replaces it.
     */
    public const TOKEN_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly StudentIdentificationService $identificationService,
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    public function __invoke(IdentifyStudentRequest $request): JsonResponse
    {
        $student = $this->identificationService->identify(
            email: $request->validated('email'),
            dni: $request->validated('dni'),
        );

        $level = $this->levelResolver->resolveLevel($student);

        $student->tokens()->where('name', self::TOKEN_NAME)->delete();
        $token = $student->createToken(
            self::TOKEN_NAME,
            expiresAt: now()->addDays(self::TOKEN_LIFETIME_DAYS),
        )->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'student' => StudentResource::make($student),
                'level' => LevelResource::make($level),
            ],
        ]);
    }
}
