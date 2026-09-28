/**
 * Converts a YouTube watch URL (or youtu.be) to an embed URL for iframes.
 */
export function youtubeWatchUrlToEmbedUrl(watchUrl: string): string {
    const trimmed = watchUrl.trim();

    try {
        const url = new URL(trimmed);

        if (url.hostname === 'youtu.be') {
            const id = url.pathname.replace(/^\//, '').split('/')[0];

            if (id) {
                return `https://www.youtube.com/embed/${id}`;
            }
        }

        if (
            url.hostname.includes('youtube.com') ||
            url.hostname.includes('youtube-nocookie.com')
        ) {
            const v = url.searchParams.get('v');

            if (v) {
                return `https://www.youtube.com/embed/${v}`;
            }

            const pathMatch = url.pathname.match(/\/embed\/([^/?]+)/);

            if (pathMatch?.[1]) {
                return `https://www.youtube.com/embed/${pathMatch[1]}`;
            }

            const shortMatch = url.pathname.match(/\/shorts\/([^/?]+)/);

            if (shortMatch?.[1]) {
                return `https://www.youtube.com/embed/${shortMatch[1]}`;
            }
        }
    } catch {
        return trimmed;
    }

    return trimmed;
}

const YOUTUBE_ID_PATTERN = /^[A-Za-z0-9_-]{11}$/;

/**
 * Extracts the 11-character video id from a YouTube watch, youtu.be,
 * embed or shorts URL. Returns null when the URL is not a YouTube video.
 */
export function youtubeVideoId(videoUrl: string): string | null {
    const embedUrl = youtubeWatchUrlToEmbedUrl(videoUrl);
    const match = embedUrl.match(
        /^https:\/\/www\.youtube\.com\/embed\/([^/?#]+)/,
    );
    const id = match?.[1] ?? null;

    return id !== null && YOUTUBE_ID_PATTERN.test(id) ? id : null;
}
