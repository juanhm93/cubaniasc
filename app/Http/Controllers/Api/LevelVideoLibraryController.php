<?php

namespace App\Http\Controllers\Api;

use App\Actions\FigureVideos\SuggestFigureVideoLinks;
use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the "link the level's videos" screen. The links themselves are made one by
 * one through {@see LevelContentVideoController::link()}.
 */
class LevelVideoLibraryController extends Controller
{
    public function show(Request $request, Level $level, SuggestFigureVideoLinks $suggest): JsonResponse
    {
        $request->user()?->can('update', $level) || abort(403);

        return response()->json($suggest->execute($level));
    }
}
