export type VideoMetadata = {
    durationSeconds: number | null;
    width: number | null;
    height: number | null;
};

const EMPTY_METADATA: VideoMetadata = {
    durationSeconds: null,
    width: null,
    height: null,
};

const MAX_STORED_DURATION_SECONDS = 3600;

/**
 * The duration the server accepts: anything outside its range goes as unknown.
 */
export function storableDuration(seconds: number | null): number | null {
    return seconds !== null &&
        seconds >= 1 &&
        seconds <= MAX_STORED_DURATION_SECONDS
        ? seconds
        : null;
}

/**
 * Reads duration and size of a video without uploading or fully downloading it,
 * from a local file or a URL. Resolves with nulls when the browser cannot read it
 * (the upload still goes ahead; the server only needs the file).
 */
export function readVideoMetadata(
    source: Blob | string,
    timeoutMs = 10_000,
): Promise<VideoMetadata> {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const src =
            typeof source === 'string' ? source : URL.createObjectURL(source);
        let settled = false;
        let timer = 0;

        const finish = (metadata: VideoMetadata) => {
            if (settled) {
                return;
            }

            settled = true;
            window.clearTimeout(timer);
            video.removeAttribute('src');
            video.load();

            if (typeof source !== 'string') {
                URL.revokeObjectURL(src);
            }

            resolve(metadata);
        };

        timer = window.setTimeout(() => finish(EMPTY_METADATA), timeoutMs);

        video.preload = 'metadata';
        video.muted = true;
        video.onloadedmetadata = () =>
            finish({
                durationSeconds: Number.isFinite(video.duration)
                    ? Math.round(video.duration)
                    : null,
                width: video.videoWidth || null,
                height: video.videoHeight || null,
            });
        video.onerror = () => finish(EMPTY_METADATA);
        video.src = src;
    });
}
