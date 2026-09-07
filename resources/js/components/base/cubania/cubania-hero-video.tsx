import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

type CubaniaHeroVideoProps = {
    videoId: string;
    playbackRate: number;
};

function youtubeCommand(
    win: Window,
    func: string,
    args: Array<number | string> = [],
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

export function CubaniaHeroVideo({
    videoId,
    playbackRate,
}: CubaniaHeroVideoProps): ReactNode {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=0&rel=0&modestbranding=1&playsinline=1&loop=1&playlist=${videoId}&fs=0&disablekb=1&iv_load_policy=3&cc_load_policy=0&enablejsapi=1`;

    useEffect(() => {
        const iframe = iframeRef.current;

        if (!iframe) {
            return;
        }

        const applyPlayback = (): void => {
            const win = iframe.contentWindow;

            if (!win) {
                return;
            }

            win.postMessage(
                JSON.stringify({ event: 'listening', id: 'cubania-hero' }),
                '*',
            );
            youtubeCommand(win, 'mute');
            youtubeCommand(win, 'setPlaybackRate', [playbackRate]);
            youtubeCommand(win, 'playVideo');
        };

        iframe.addEventListener('load', applyPlayback);
        const retry = window.setTimeout(applyPlayback, 800);

        return () => {
            iframe.removeEventListener('load', applyPlayback);
            window.clearTimeout(retry);
        };
    }, [playbackRate, videoId]);

    return (
        <div
            className="cubania-hero__video"
            style={{
                backgroundImage: `url(https://i.ytimg.com/vi/${videoId}/maxresdefault.jpg)`,
            }}
            aria-hidden
        >
            <iframe
                ref={iframeRef}
                className="cubania-hero__video-frame"
                src={src}
                title=""
                allow="autoplay; encrypted-media; picture-in-picture"
                referrerPolicy="strict-origin-when-cross-origin"
                tabIndex={-1}
            />
        </div>
    );
}
