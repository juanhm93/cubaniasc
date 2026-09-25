<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReorderSortOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderLevelsRequest;
use App\Http\Requests\StoreLevelRequest;
use App\Http\Requests\UpdateLevelRequest;
use App\Models\DanceType;
use App\Models\Level;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->user()?->can('viewAny', Level::class) || abort(403);

        $levels = Level::query()->with('danceType')->orderBy('sort_order')->get();

        return response()->json($levels);
    }

    public function store(StoreLevelRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $danceTypeId = (int) $validated['dance_type_id'];

        $level = Level::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => UniqueSlug::for(Level::class, $validated['name'], 'level'),
            'sort_order' => ((int) Level::withTrashed()->where('dance_type_id', $danceTypeId)->max('sort_order')) + 1,
            'dance_type_id' => $danceTypeId,
        ]);

        $level->load('danceType');

        return response()->json($level, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $level = Level::query()
            ->with([
                'danceType',
                'levelContents' => function ($query): void {
                    $query->orderBy('sort_order');
                },
            ])
            ->findOrFail($id);

        $request->user()?->can('view', $level) || abort(403);

        return response()->json($level);
    }

    public function update(UpdateLevelRequest $request, Level $level): JsonResponse
    {
        $level->update($request->validated());

        return response()->json($level->fresh()->load('danceType'));
    }

    public function reorder(ReorderLevelsRequest $request, DanceType $danceType, ReorderSortOrder $reorder): Response
    {
        $reorder->execute(
            Level::query()->where('dance_type_id', $danceType->id),
            $request->orderedIds(),
        );

        return response()->noContent();
    }

    public function destroy(Request $request, Level $level): JsonResponse|Response
    {
        $request->user()?->can('delete', $level) || abort(403);

        if ($level->isUsedByCourses()) {
            return response()->json([
                'message' => 'No se puede eliminar un nivel asociado a un curso.',
            ], 422);
        }

        $level->levelContents()->delete();
        $level->delete();

        return response()->noContent();
    }
}
