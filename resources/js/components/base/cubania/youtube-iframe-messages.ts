/**
 * Helpers for driving a YouTube embed through the IFrame API postMessage
 * protocol, without loading the `iframe_api` script.
 */

export const YT_UNSTARTED = -1;
export const YT_ENDED = 0;
export const YT_PLAYING = 1;
export const YT_PAUSED = 2;
export const YT_BUFFERING = 3;
export const YT_CUED = 5;

export type YouTubeInfo = {
    playerState?: number;
    currentTime?: number;
    duration?: number;
    playbackRate?: number;
    muted?: boolean;
};

export type YouTubeMessage = {
    event?: string;
    info?: number | YouTubeInfo | null;
};

export function youtubeCommand(
    win: Window,
    func: string,
    args: Array<number | string | boolean> = [],
): void {
    win.postMessage(
        JSON.stringify({
            event: 'command',
            func,
            args,
        }),
        '*',
    );
}

export function isYouTubeOrigin(origin: string): boolean {
    return (
        origin === 'https://www.youtube.com' ||
        origin === 'https://www.youtube-nocookie.com'
    );
}

/**
 * Parses a raw `message` event payload coming from a YouTube iframe.
 */
export function parseYouTubeMessage(data: unknown): YouTubeMessage | null {
    let parsed: unknown = data;

    if (typeof parsed === 'string') {
        try {
            parsed = JSON.parse(parsed);
        } catch {
            return null;
        }
    }

    if (!parsed || typeof parsed !== 'object') {
        return null;
    }

    return parsed as YouTubeMessage;
}

export function readPlayerState(payload: YouTubeMessage): number | null {
    if (payload.event === 'onStateChange' && typeof payload.info === 'number') {
        return payload.info;
    }

    if (
        payload.event === 'infoDelivery' &&
        payload.info !== null &&
        typeof payload.info === 'object' &&
        typeof payload.info.playerState === 'number'
    ) {
        return payload.info.playerState;
    }

    return null;
}
