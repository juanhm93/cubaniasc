<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureReviewStudentToken;
use App\Http\Requests\Review\VerifyReviewLoginCodeRequest;
use App\Http\Resources\Review\LevelResource;
use App\Http\Resources\Review\StudentResource;
use App\Models\Student;
use App\Services\Auth\StudentLoginCodeService;
use App\Services\Review\StudentIdentificationService;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Exchanges the emailed one-time code for a review-panel token. The code is
 * requested through the same endpoint the mobile app uses.
 */
final class VerifyReviewLoginCodeController extends Controller
{
    public const TOKEN_NAME = 'review-panel';

    /**
     * One active token per student; signing in again on another device replaces it.
     */
    public const TOKEN_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly StudentIdentificationService $identificationService,
        private readonly StudentLoginCodeService $loginCodeService,
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    public function __invoke(VerifyReviewLoginCodeRequest $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if ($student === null || ! $this->loginCodeService->verify($student, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => 'El código no es válido o ya venció. Solicita uno nuevo.',
            ]);
        }

        $level = $this->levelResolver->resolveLevel($student);

        $student->tokens()->where('name', self::TOKEN_NAME)->delete();
        $token = $student->createToken(
            self::TOKEN_NAME,
            [EnsureReviewStudentToken::ABILITY],
            now()->addDays(self::TOKEN_LIFETIME_DAYS),
        )->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'student' => StudentResource::make($student),
                'level' => LevelResource::make($level),
            ],
        ]);
    }

    private function resolveStudent(VerifyReviewLoginCodeRequest $request): ?Student
    {
        try {
            return $this->identificationService->identify(
                email: $request->validated('email'),
                dni: $request->validated('dni'),
            );
        } catch (StudentNotIdentifiableException) {
            return null;
        }
    }
}
