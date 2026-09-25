<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReorderSortOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderLevelContentsRequest;
use App\Http\Requests\StoreLevelContentRequest;
use App\Http\Requests\UpdateLevelContentRequest;
use App\Models\Level;
use App\Models\LevelContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LevelContentController extends Controller
{
    public function store(StoreLevelContentRequest $request, Level $level): JsonResponse
    {
        $validated = $request->validated();

        $content = $level->levelContents()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
            'sort_order' => ((int) $level->levelContents()->withTrashed()->max('sort_order')) + 1,
        ]);

        return response()->json($content, 201);
    }

    public function update(UpdateLevelContentRequest $request, LevelContent $levelContent): JsonResponse
    {
        $levelContent->update($request->validated());

        return response()->json($levelContent->fresh());
    }

    public function reorder(ReorderLevelContentsRequest $request, Level $level, ReorderSortOrder $reorder): Response
    {
        $reorder->execute(
            LevelContent::query()->where('level_id', $level->id),
            $request->orderedIds(),
            65535,
        );

        return response()->noContent();
    }

    public function destroy(Request $request, LevelContent $levelContent): Response
    {
        $request->user()?->can('delete', $levelContent->level) || abort(403);

        $levelContent->delete();

        return response()->noContent();
    }
}
