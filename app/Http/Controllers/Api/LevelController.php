<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLevelRequest;
use App\Models\DanceType;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $request->user()?->can('create', Level::class) || abort(403);

        $validated = $request->validated();

        $baseSlug = Str::slug($validated['name']);
        if ($baseSlug === '') {
            $baseSlug = 'level';
        }

        $slug = $baseSlug;
        $suffix = 1;
        while (Level::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $nextSortOrder = ((int) Level::query()->max('sort_order')) + 1;
        $danceTypeId = (int) ($validated['dance_type_id'] ?? DanceType::query()->value('id'));

        if ($danceTypeId === 0) {
            $danceTypeId = (int) DanceType::query()->create([
                'name' => 'Default Dance Type',
                'slug' => 'default-dance-type',
                'description' => null,
                'sort_order' => ((int) DanceType::query()->max('sort_order')) + 1,
            ])->id;
        }

        $level = Level::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => $slug,
            'sort_order' => $nextSortOrder,
            'dance_type_id' => $danceTypeId,
        ]);

        return response()->json($level, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $level = Level::query()
            ->with([
                'levelContents' => function ($query): void {
                    $query->orderBy('sort_order');
                },
            ])
            ->findOrFail($id);

        $request->user()?->can('view', $level) || abort(403);

        return response()->json($level);
    }
}
