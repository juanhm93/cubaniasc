<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Review;

use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Review\StudentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class ReviewAuthController extends Controller
{
    use InteractsWithReviewSessions;

    public function me(Request $request): StudentResource
    {
        return StudentResource::make($this->authenticatedStudent($request));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authenticatedStudent($request);

        $accessToken = PersonalAccessToken::findToken((string) $request->bearerToken());

        if ($accessToken !== null) {
            $accessToken->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
