import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    isYouTubeOrigin,
    parseYouTubeMessage,
    readPlayerState,
    youtubeCommand,
    YT_ENDED,
    YT_PAUSED,
    YT_PLAYING,
} from '@/components/base/cubania/youtube-iframe-messages';

type CubaniaHeroVideoProps = {
    videoId: string;
    playbackRate: number;
    showControls?: boolean;
    paused?: boolean;
};

export function CubaniaHeroVideo({
    videoId,
    playbackRate,
    showControls = true,
    paused = false,
}: CubaniaHeroVideoProps): ReactNode {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const pausedRef = useRef(paused);
    const [isPlaying, setIsPlaying] = useState(false);
    const controlsParam = showControls ? 1 : 0;
    const src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=${controlsParam}&rel=0&modestbranding=1&playsinline=1&fs=0&disablekb=1&iv_load_policy=3&cc_load_policy=0&enablejsapi=1`;

    useEffect(() => {
        const iframe = iframeRef.current;

        if (!iframe) {
            return;
        }

        setIsPlaying(false);

        const applyPlayback = (): void => {
            const win = iframe.contentWindow;

            if (!win) {
                return;
            }

            win.postMessage(
                JSON.stringify({ event: 'listening', id: 'cubania-hero' }),
                '*',
            );
            youtubeCommand(win, 'addEventListener', ['onStateChange']);
            youtubeCommand(win, 'mute');
            youtubeCommand(win, 'setPlaybackRate', [playbackRate]);

            if (!pausedRef.current) {
                youtubeCommand(win, 'playVideo');
            }
        };

        let restarting = false;
        let revealTimeout: number | undefined;

        const hideVideo = (): void => {
            if (revealTimeout !== undefined) {
                window.clearTimeout(revealTimeout);
                revealTimeout = undefined;
            }

            setIsPlaying(false);
        };

        const revealVideo = (): void => {
            if (revealTimeout !== undefined) {
                window.clearTimeout(revealTimeout);
            }

            revealTimeout = window.setTimeout(() => {
                setIsPlaying(true);
                revealTimeout = undefined;
            }, 500);
        };

        const restartFromStart = (): void => {
            if (restarting) {
                return;
            }

            const win = iframe.contentWindow;

            if (!win) {
                return;
            }

            restarting = true;
            hideVideo();
            youtubeCommand(win, 'seekTo', [0, true]);
            youtubeCommand(win, 'mute');
            youtubeCommand(win, 'setPlaybackRate', [playbackRate]);
            youtubeCommand(win, 'playVideo');
            window.setTimeout(() => {
                restarting = false;
            }, 750);
        };

        const onMessage = (event: MessageEvent): void => {
            if (!isYouTubeOrigin(event.origin)) {
                return;
            }

            const payload = parseYouTubeMessage(event.data);

            if (!payload) {
                return;
            }

            const playerState = readPlayerState(payload);

            if (playerState === null) {
                return;
            }

            if (playerState === YT_PLAYING) {
                revealVideo();

                return;
            }

            if (playerState === YT_ENDED) {
                restartFromStart();

                return;
            }

            if (playerState === YT_PAUSED) {
                // A pause asked for from the reel button keeps the frame on
                // screen; any other pause is a hiccup we recover from.
                if (pausedRef.current) {
                    return;
                }

                hideVideo();
                const win = iframe.contentWindow;

                if (win) {
                    youtubeCommand(win, 'playVideo');
                }
            }
        };

        iframe.addEventListener('load', applyPlayback);
        window.addEventListener('message', onMessage);
        const retry = window.setTimeout(applyPlayback, 800);

        return () => {
            iframe.removeEventListener('load', applyPlayback);
            window.removeEventListener('message', onMessage);
            window.clearTimeout(retry);

            if (revealTimeout !== undefined) {
                window.clearTimeout(revealTimeout);
            }
        };
    }, [playbackRate, videoId, showControls]);

    useEffect(() => {
        pausedRef.current = paused;

        const win = iframeRef.current?.contentWindow;

        if (!win) {
            return;
        }

        if (paused) {
            youtubeCommand(win, 'pauseVideo');

            return;
        }

        youtubeCommand(win, 'mute');
        youtubeCommand(win, 'setPlaybackRate', [playbackRate]);
        youtubeCommand(win, 'playVideo');
    }, [paused, playbackRate]);

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
            <div
                className={[
                    'cubania-hero__video-cover',
                    isPlaying ? 'cubania-hero__video-cover--hidden' : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                <img
                    className="cubania-hero__video-logo"
                    src="/logo.webp"
                    alt=""
                    draggable={false}
                />
            </div>
        </div>
    );
}
