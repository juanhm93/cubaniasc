<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\RequestLoginCodeRequest;
use App\Services\Auth\StudentLoginCodeService;
use App\Services\Review\StudentIdentificationService;
use Illuminate\Http\JsonResponse;
use Throwable;

use function Illuminate\Support\defer;

final class RequestLoginCodeController extends Controller
{
    public function __construct(
        private readonly StudentIdentificationService $identificationService,
        private readonly StudentLoginCodeService $loginCodeService,
    ) {}

    /**
     * Always answers the same way, so the endpoint does not reveal who is enrolled.
     * The code is generated and emailed after the response is sent: a synchronous
     * SMTP call only for real students would leak who is enrolled through timing.
     */
    public function __invoke(RequestLoginCodeRequest $request): JsonResponse
    {
        try {
            $student = $this->identificationService->identify(
                email: $request->validated('email'),
                dni: $request->validated('dni'),
            );

            defer(function () use ($student): void {
                try {
                    $this->loginCodeService->issue($student);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        } catch (StudentNotIdentifiableException) {
            // Same response as the happy path on purpose.
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'Si los datos corresponden a un alumno con inscripción activa, enviamos un código a su correo registrado.',
            'data' => [
                'code_length' => StudentLoginCodeService::CODE_LENGTH,
                'expires_in_minutes' => StudentLoginCodeService::EXPIRES_IN_MINUTES,
            ],
        ], 202);
    }
}
