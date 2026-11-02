<?php

namespace App\Http\Controllers\Api;

use App\Actions\Songs\ListSongLibrary;
use App\Enums\MediaUrlPurpose;
use App\Http\Controllers\Admin\RecommendedSongController;
use App\Http\Controllers\Controller;
use App\Http\Requests\SongLibraryPreviewRequest;
use App\Models\RecommendedSong;
use App\Services\Content\SongAudioStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Staff side of the songs' audio: browse the music folder, listen to a file before
 * creating its song, and play or download a song of the catalog. Creating and
 * updating songs is in {@see RecommendedSongController}.
 */
class RecommendedSongAudioController extends Controller
{
    public function __construct(private readonly SongAudioStorage $storage) {}

    public function library(ListSongLibrary $library): JsonResponse
    {
        return response()->json($library->execute());
    }

    public function previewUrl(SongLibraryPreviewRequest $request): JsonResponse
    {
        $signed = $this->storage->previewUrl($request->validated('key'));

        return response()->json([
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at']->toIso8601String(),
        ]);
    }

    public function streamUrl(RecommendedSong $recommendedSong): JsonResponse
    {
        $this->ensureHasAudioFile($recommendedSong);

        $signed = $this->storage->signedUrl(
            $recommendedSong,
            MediaUrlPurpose::Stream,
            (int) config('cubania.songs.stream_url_minutes'),
        );

        return response()->json([
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at']->toIso8601String(),
        ]);
    }

    /**
     * The download button is a plain link: it lands here and is sent on to a
     * short-lived signed URL that answers with `Content-Disposition: attachment`.
     */
    public function download(RecommendedSong $recommendedSong): RedirectResponse
    {
        $this->ensureHasAudioFile($recommendedSong);

        $signed = $this->storage->signedUrl(
            $recommendedSong,
            MediaUrlPurpose::Download,
            (int) config('cubania.songs.staff_download_url_minutes'),
        );

        return redirect()->away($signed['url']);
    }

    private function ensureHasAudioFile(RecommendedSong $recommendedSong): void
    {
        $recommendedSong->hasAudioFile() || abort(404, 'Esta canción no tiene archivo.');
    }
}
