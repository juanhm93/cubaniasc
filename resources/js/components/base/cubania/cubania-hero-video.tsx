import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

type CubaniaHeroVideoProps = {
    videoId: string;
    playbackRate: number;
};

type YouTubePlayer = {
    destroy: () => void;
    mute: () => void;
    playVideo: () => void;
    setPlaybackRate: (rate: number) => void;
};

type YouTubePlayerEvent = {
    data: number;
    target: YouTubePlayer;
};

type YouTubeIframeApi = {
    Player: new (
        element: HTMLElement | string,
        options: {
            videoId: string;
            host?: string;
            playerVars?: Record<string, number | string>;
            events?: {
                onReady?: (event: { target: YouTubePlayer }) => void;
                onStateChange?: (event: YouTubePlayerEvent) => void;
            };
        },
    ) => YouTubePlayer;
    PlayerState: {
        ENDED: number;
        PLAYING: number;
    };
};

declare global {
    interface Window {
        YT?: YouTubeIframeApi;
        onYouTubeIframeAPIReady?: () => void;
    }
}

let youtubeApiPromise: Promise<YouTubeIframeApi> | null = null;

function loadYouTubeIframeApi(): Promise<YouTubeIframeApi> {
    if (window.YT?.Player) {
        return Promise.resolve(window.YT);
    }

    if (youtubeApiPromise) {
        return youtubeApiPromise;
    }

    youtubeApiPromise = new Promise((resolve) => {
        const previous = window.onYouTubeIframeAPIReady;

        window.onYouTubeIframeAPIReady = () => {
            previous?.();

            if (window.YT) {
                resolve(window.YT);
            }
        };

        if (!document.getElementById('cubania-youtube-iframe-api')) {
            const script = document.createElement('script');
            script.id = 'cubania-youtube-iframe-api';
            script.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(script);
        }
    });

    return youtubeApiPromise;
}

export function CubaniaHeroVideo({
    videoId,
    playbackRate,
}: CubaniaHeroVideoProps): ReactNode {
    const hostRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const host = hostRef.current;

        if (!host) {
            return;
        }

        const mount = document.createElement('div');
        host.appendChild(mount);

        let cancelled = false;
        let player: YouTubePlayer | null = null;

        const applyPlayback = (target: YouTubePlayer): void => {
            target.mute();
            target.setPlaybackRate(playbackRate);
            target.playVideo();
        };

        loadYouTubeIframeApi()
            .then((api) => {
                if (cancelled) {
                    return;
                }

                player = new api.Player(mount, {
                    videoId,
                    host: 'https://www.youtube-nocookie.com',
                    playerVars: {
                        autoplay: 1,
                        mute: 1,
                        controls: 0,
                        rel: 0,
                        modestbranding: 1,
                        playsinline: 1,
                        loop: 1,
                        playlist: videoId,
                        fs: 0,
                        disablekb: 1,
                        iv_load_policy: 3,
                        cc_load_policy: 0,
                        origin: window.location.origin,
                    },
                    events: {
                        onReady: (event) => {
                            applyPlayback(event.target);
                        },
                        onStateChange: (event) => {
                            if (event.data === api.PlayerState.PLAYING) {
                                event.target.setPlaybackRate(playbackRate);
                            }

                            if (event.data === api.PlayerState.ENDED) {
                                event.target.playVideo();
                            }
                        },
                    },
                });
            })
            .catch(() => {
                youtubeApiPromise = null;
            });

        return () => {
            cancelled = true;
            player?.destroy();
            mount.remove();
        };
    }, [playbackRate, videoId]);

    return (
        <div className="cubania-hero__video" aria-hidden>
            <div ref={hostRef} className="cubania-hero__video-host" />
        </div>
    );
}
