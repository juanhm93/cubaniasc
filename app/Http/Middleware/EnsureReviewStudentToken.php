<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only student tokens issued for the review panel (ability `review`) may use /api/review.
 * Mobile-app tokens, staff tokens and old wildcard tokens are rejected.
 */
class EnsureReviewStudentToken
{
    public const ABILITY = 'review';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Student || ! $this->hasExplicitReviewAbility($user)) {
            abort(403, 'Este token no tiene acceso al panel de repaso.');
        }

        return $next($request);
    }

    /**
     * `tokenCan()` alone is not enough: tokens created without abilities get `*`.
     */
    private function hasExplicitReviewAbility(Student $student): bool
    {
        $token = $student->currentAccessToken();

        if ($token === null || ! $token->can(self::ABILITY)) {
            return false;
        }

        return ! in_array('*', (array) $token->abilities, true);
    }
}
