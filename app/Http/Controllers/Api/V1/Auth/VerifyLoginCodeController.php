<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAppStudentToken;
use App\Http\Requests\V1\Auth\VerifyLoginCodeRequest;
use App\Http\Resources\V1\StudentProfileResource;
use App\Models\Student;
use App\Services\Auth\StudentLoginCodeService;
use App\Services\Review\StudentIdentificationService;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class VerifyLoginCodeController extends Controller
{
    /**
     * Prefix of every mobile-app token name; the device name follows it.
     */
    public const TOKEN_PREFIX = 'mobile-app:';

    public const TOKEN_LIFETIME_DAYS = 60;

    public function __construct(
        private readonly StudentIdentificationService $identificationService,
        private readonly StudentLoginCodeService $loginCodeService,
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    public function __invoke(VerifyLoginCodeRequest $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if ($student === null || ! $this->loginCodeService->verify($student, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => 'El código no es válido o ya venció. Solicita uno nuevo.',
            ]);
        }

        // One app session per student: signing in on a new phone closes the old one.
        // Review-panel tokens are left untouched.
        $student->tokens()->where('name', 'like', self::TOKEN_PREFIX.'%')->delete();

        $expiresAt = now()->addDays(self::TOKEN_LIFETIME_DAYS);
        $token = $student->createToken(
            self::TOKEN_PREFIX.$request->validated('device_name'),
            [EnsureAppStudentToken::ABILITY],
            $expiresAt,
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'student' => new StudentProfileResource($student, $this->levelResolver->findLevel($student)),
            ],
        ]);
    }

    private function resolveStudent(VerifyLoginCodeRequest $request): ?Student
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
