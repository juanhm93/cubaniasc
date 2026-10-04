<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\NotifyLevelContentChanged;
use App\Actions\ReorderSortOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderLevelContentsRequest;
use App\Http\Requests\StoreLevelContentRequest;
use App\Http\Requests\UpdateLevelContentRequest;
use App\Models\Level;
use App\Models\LevelContent;
use App\Notifications\LevelContentChangedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LevelContentController extends Controller
{
    public function store(StoreLevelContentRequest $request, Level $level, NotifyLevelContentChanged $notifyLevelContentChanged): JsonResponse
    {
        $validated = $request->validated();

        $content = $level->levelContents()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
            'sort_order' => ((int) $level->levelContents()->withTrashed()->max('sort_order')) + 1,
        ]);

        $notifyLevelContentChanged->execute(
            $content,
            [LevelContentChangedNotification::FIGURE_CREATED],
            $request->user(),
        );

        return response()->json($content, 201);
    }

    public function update(
        UpdateLevelContentRequest $request,
        LevelContent $levelContent,
        NotifyLevelContentChanged $notifyLevelContentChanged,
    ): JsonResponse {
        $levelContent->update($request->validated());

        $notifyLevelContentChanged->execute(
            $levelContent,
            NotifyLevelContentChanged::changesFromUpdate($levelContent),
            $request->user(),
        );

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

    public function destroy(Request $request, LevelContent $levelContent, NotifyLevelContentChanged $notifyLevelContentChanged): Response
    {
        $request->user()?->can('delete', $levelContent->level) || abort(403);

        $levelContent->delete();

        $notifyLevelContentChanged->execute(
            $levelContent,
            [LevelContentChangedNotification::FIGURE_DELETED],
            $request->user(),
        );

        return response()->noContent();
    }
}
