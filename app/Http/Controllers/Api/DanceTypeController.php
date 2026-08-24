<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDanceTypeRequest;
use App\Http\Requests\UpdateDanceTypeRequest;
use App\Models\DanceType;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DanceTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->user()?->can('viewAny', DanceType::class) || abort(403);

        $danceTypes = DanceType::query()
            ->withCount(['levels', 'levelContents as figures_count'])
            ->orderBy('sort_order')
            ->get();

        return response()->json($danceTypes);
    }

    public function store(StoreDanceTypeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $danceType = DanceType::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => UniqueSlug::for(DanceType::class, $validated['name'], 'estilo'),
            'sort_order' => ((int) DanceType::withTrashed()->max('sort_order')) + 1,
        ]);

        $danceType->loadCount(['levels', 'levelContents as figures_count']);

        return response()->json($danceType, 201);
    }

    public function show(Request $request, DanceType $danceType): JsonResponse
    {
        $request->user()?->can('view', $danceType) || abort(403);

        $danceType->load([
            'levels' => function ($query): void {
                $query->orderBy('sort_order')->with([
                    'levelContents' => function ($query): void {
                        $query->orderBy('sort_order');
                    },
                ]);
            },
        ]);
        $danceType->loadCount(['levels', 'levelContents as figures_count']);

        return response()->json($danceType);
    }

    public function update(UpdateDanceTypeRequest $request, DanceType $danceType): JsonResponse
    {
        $danceType->update($request->validated());

        return response()->json(
            $danceType->fresh()?->loadCount(['levels', 'levelContents as figures_count'])
        );
    }

    public function destroy(Request $request, DanceType $danceType): JsonResponse|Response
    {
        $request->user()?->can('delete', $danceType) || abort(403);

        if ($danceType->isUsedByCourses()) {
            return response()->json([
                'message' => 'No se puede eliminar un estilo asociado a un curso.',
            ], 422);
        }

        $danceType->load('levels');

        foreach ($danceType->levels as $level) {
            $level->levelContents()->delete();
            $level->delete();
        }

        $danceType->delete();

        return response()->noContent();
    }
}
