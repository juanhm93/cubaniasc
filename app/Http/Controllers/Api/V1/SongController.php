<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Songs\CreateSongAudioUrl;
use App\Http\Controllers\Api\Review\Concerns\InteractsWithReviewSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\SongAudioUrlRequest;
use App\Http\Resources\V1\Review\SongResource;
use App\Models\Level;
use App\Models\RecommendedSong;
use App\Repositories\Review\RecommendedSongRepository;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The student's music library in the mobile app: the songs of their level and of the
 * earlier ones, the same pool the review recommends from. Without an active
 * enrollment the library is empty.
 */
final class SongController extends Controller
{
    use InteractsWithReviewSessions;

    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
        private readonly RecommendedSongRepository $songs,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $currentLevel = $this->levelResolver->findLevel($this->authenticatedStudent($request));
        $levels = $currentLevel === null ? collect() : $this->levelResolver->reviewLevelsFor($currentLevel);

        return SongResource::collection($this->songs->activeForLevels($levels->map(fn (Level $level): int => (int) $level->id)->all()))
            ->additional([
                'meta' => [
                    'current_level_id' => $currentLevel?->id,
                    'levels' => $levels
                        ->map(fn (Level $level): array => [
                            'id' => $level->id,
                            'name' => $level->name,
                            'slug' => $level->slug,
                            'sort_order' => $level->sort_order,
                            'is_current' => $level->is($currentLevel),
                        ])
                        ->all(),
                ],
            ]);
    }

    /**
     * Signed URL to play or save a song. Access is checked first (403), then that the
     * song can be used (404).
     */
    public function audioUrl(SongAudioUrlRequest $request, RecommendedSong $song, CreateSongAudioUrl $createUrl): JsonResponse
    {
        $level = $this->levelResolver->findLevel($this->authenticatedStudent($request));
        $levelIds = $level === null ? [] : $this->levelResolver->reviewLevelIdsFor($level);

        if (! $this->songs->belongsToLevels($song, $levelIds)) {
            abort(403, 'No tienes acceso a esta canción.');
        }

        if (! $song->is_active || ! $song->hasAudioFile()) {
            abort(404, 'Esta canción no está disponible.');
        }

        return response()->json(['data' => $createUrl->execute($song, $request->purpose())]);
    }
}
