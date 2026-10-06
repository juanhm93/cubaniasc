<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only student tokens issued for the mobile app (ability `app`) may use the v1 app API.
 * Review-panel tokens and staff tokens are rejected.
 */
class EnsureAppStudentToken
{
    public const ABILITY = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Student || ! $this->hasExplicitAppAbility($user)) {
            abort(403, 'Este token no tiene acceso a la app.');
        }

        return $next($request);
    }

    /**
     * `tokenCan()` is not enough: tokens created without abilities get `*`,
     * which would let review-panel tokens through.
     */
    private function hasExplicitAppAbility(Student $student): bool
    {
        $token = $student->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return in_array(self::ABILITY, (array) $token->abilities, true);
    }
}
