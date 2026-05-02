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
