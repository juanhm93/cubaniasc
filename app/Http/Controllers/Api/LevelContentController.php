<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLevelContentRequest;
use App\Models\LevelContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LevelContentController extends Controller
{
    public function update(UpdateLevelContentRequest $request, LevelContent $levelContent): JsonResponse
    {
        $levelContent->update($request->validated());

        return response()->json($levelContent->fresh());
    }

    public function destroy(Request $request, LevelContent $levelContent): Response
    {
        $request->user()?->can('update', $levelContent->level) || abort(403);

        $levelContent->delete();

        return response()->noContent();
    }
}
