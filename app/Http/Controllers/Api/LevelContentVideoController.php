<?php

namespace App\Http\Controllers\Api;

use App\Actions\FigureVideos\AttachFigureVideo;
use App\Actions\FigureVideos\LinkFigureVideo;
use App\Actions\FigureVideos\ListFigureVideoLibrary;
use App\Actions\FigureVideos\RemoveFigureVideo;
use App\Enums\FigureVideoUrlPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmLevelContentVideoRequest;
use App\Http\Requests\FigureVideoLibraryRequest;
use App\Http\Requests\LevelContentVideoRequest;
use App\Http\Requests\LinkLevelContentVideoRequest;
use App\Http\Requests\RequestLevelContentVideoUploadRequest;
use App\Models\LevelContent;
use App\Services\Content\FigureVideoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Staff side of a figure's own MP4: upload (presigned PUT, then confirm), link a
 * file already in the bucket, remove, play and download. The YouTube link is
 * managed by {@see LevelContentController}.
 */
class LevelContentVideoController extends Controller
{
    public function __construct(private readonly FigureVideoStorage $storage) {}

    public function uploadUrl(RequestLevelContentVideoUploadRequest $request, LevelContent $levelContent): JsonResponse
    {
        $key = $this->storage->newUploadKey($levelContent);
        $upload = $this->storage->createUploadUrl($key);

        return response()->json([
            'key' => $key,
            'url' => $upload['url'],
            'headers' => $upload['headers'],
            'expires_at' => $upload['expires_at']->toIso8601String(),
        ]);
    }

    public function store(ConfirmLevelContentVideoRequest $request, LevelContent $levelContent, AttachFigureVideo $attach): JsonResponse
    {
        $durationSeconds = $request->validated('duration_seconds');

        return response()->json($attach->execute(
            $levelContent,
            $request->validated('key'),
            $durationSeconds === null ? null : (int) $durationSeconds,
        ));
    }

    /**
     * Without `folder` it opens the level's folder; an empty `folder` is the root.
     */
    public function library(FigureVideoLibraryRequest $request, LevelContent $levelContent, ListFigureVideoLibrary $library): JsonResponse
    {
        $folder = $request->has('folder')
            ? (string) $request->validated('folder')
            : $this->storage->defaultFolderFor($levelContent->level);

        return response()->json($library->execute($folder));
    }

    public function previewUrl(LinkLevelContentVideoRequest $request, LevelContent $levelContent): JsonResponse
    {
        $signed = $this->storage->previewUrl($request->validated('key'));

        return response()->json([
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at']->toIso8601String(),
        ]);
    }

    public function link(LinkLevelContentVideoRequest $request, LevelContent $levelContent, LinkFigureVideo $link): JsonResponse
    {
        $durationSeconds = $request->validated('duration_seconds');

        return response()->json($link->execute(
            $levelContent,
            $request->validated('key'),
            $durationSeconds === null ? null : (int) $durationSeconds,
        ));
    }

    public function destroy(LevelContentVideoRequest $request, LevelContent $levelContent, RemoveFigureVideo $remove): JsonResponse
    {
        $this->ensureHasVideoFile($levelContent);

        return response()->json($remove->execute($levelContent));
    }

    public function streamUrl(LevelContentVideoRequest $request, LevelContent $levelContent): JsonResponse
    {
        $this->ensureHasVideoFile($levelContent);

        $signed = $this->storage->signedUrl(
            $levelContent,
            FigureVideoUrlPurpose::Stream,
            (int) config('cubania.figure_videos.stream_url_minutes'),
        );

        return response()->json([
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at']->toIso8601String(),
        ]);
    }

    /**
     * The "Descargar MP4" button is a plain link: it lands here and is sent on to a
     * short-lived signed URL that answers with `Content-Disposition: attachment`.
     */
    public function download(LevelContentVideoRequest $request, LevelContent $levelContent): RedirectResponse
    {
        $this->ensureHasVideoFile($levelContent);

        $signed = $this->storage->signedUrl(
            $levelContent,
            FigureVideoUrlPurpose::Download,
            (int) config('cubania.figure_videos.staff_download_url_minutes'),
        );

        return redirect()->away($signed['url']);
    }

    private function ensureHasVideoFile(LevelContent $levelContent): void
    {
        $levelContent->hasVideoFile() || abort(404, 'Esta figura no tiene video.');
    }
}
