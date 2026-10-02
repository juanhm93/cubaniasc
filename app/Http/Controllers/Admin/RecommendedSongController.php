<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecommendedSongRequest;
use App\Http\Requests\UpdateRecommendedSongRequest;
use App\Models\DanceType;
use App\Models\Level;
use App\Models\RecommendedSong;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecommendedSongController extends Controller
{
    /**
     * List the songs recommended in the review panel with their levels, and the level
     * catalog grouped by dance type for the form.
     */
    public function index(): Response
    {
        $songs = RecommendedSong::query()
            ->with(['levels' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('title')
            ->get()
            ->map(fn (RecommendedSong $song): array => [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist,
                'audio_or_link_url' => $song->audio_or_link_url,
                'is_active' => $song->is_active,
                'levels' => $song->levels
                    ->map(fn (Level $level): array => ['id' => $level->id, 'name' => $level->name])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $danceTypes = DanceType::query()
            ->with(['levels' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DanceType $danceType): array => [
                'id' => $danceType->id,
                'name' => $danceType->name,
                'levels' => $danceType->levels
                    ->map(fn (Level $level): array => ['id' => $level->id, 'name' => $level->name])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $danceType): bool => $danceType['levels'] !== [])
            ->values()
            ->all();

        return Inertia::render('admin/recommended-songs/index', [
            'songs' => $songs,
            'danceTypes' => $danceTypes,
        ]);
    }

    public function store(StoreRecommendedSongRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $song = RecommendedSong::query()->create($request->safe()->except('level_ids'));
            $song->levels()->sync($request->validated('level_ids'));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Canción creada.',
        ]);

        return redirect()->route('admin.recommended-songs.index');
    }

    public function update(UpdateRecommendedSongRequest $request, RecommendedSong $recommendedSong): RedirectResponse
    {
        DB::transaction(function () use ($request, $recommendedSong): void {
            $recommendedSong->update($request->safe()->except('level_ids'));
            $recommendedSong->levels()->sync($request->validated('level_ids'));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Canción actualizada.',
        ]);

        return redirect()->route('admin.recommended-songs.index');
    }

    /**
     * Soft-delete a song. Past review sessions keep their reference to it.
     */
    public function destroy(RecommendedSong $recommendedSong): RedirectResponse
    {
        $recommendedSong->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Canción eliminada.',
        ]);

        return redirect()->route('admin.recommended-songs.index');
    }
}
