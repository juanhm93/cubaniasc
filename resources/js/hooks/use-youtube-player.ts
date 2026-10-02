import { useCallback, useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import {
    isYouTubeOrigin,
    parseYouTubeMessage,
    readPlayerState,
    youtubeCommand,
    YT_BUFFERING,
    YT_ENDED,
    YT_PAUSED,
    YT_PLAYING,
} from '@/components/base/cubania/youtube-iframe-messages';

export const PLAYBACK_RATES = [1, 1.25, 1.5] as const;

export type PlaybackRate = (typeof PLAYBACK_RATES)[number];

export type YouTubePlayerStatus =
    | 'idle'
    | 'buffering'
    | 'playing'
    | 'paused'
    | 'ended';

export type YouTubePlayer = {
    status: YouTubePlayerStatus;
    ready: boolean;
    starting: boolean;
    currentTime: number;
    duration: number;
    playbackRate: PlaybackRate;
    muted: boolean;
    mutedByFallback: boolean;
    play: () => void;
    pause: () => void;
    toggle: () => void;
    seekTo: (seconds: number) => void;
    seekBy: (seconds: number) => void;
    setPlaybackRate: (rate: PlaybackRate) => void;
    toggleMute: () => void;
};

/**
 * Seconds before the real end where playback is stopped, so YouTube never
 * gets to paint its end screen with suggested videos.
 */
const END_GUARD_SECONDS = 0.4;

const PLAY_FALLBACK_MS = 2000;

const LISTEN_INTERVAL_MS = 400;

const LISTEN_MAX_ATTEMPTS = 25;

/**
 * Drives a YouTube embed (loaded with `enablejsapi=1`) through postMessage
 * so the page can render its own controls on top of it.
 */
export function useYouTubePlayer(
    iframeRef: RefObject<HTMLIFrameElement | null>,
    videoId: string,
): YouTubePlayer {
    const [status, setStatus] = useState<YouTubePlayerStatus>('idle');
    const [ready, setReady] = useState(false);
    const [starting, setStarting] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);
    const [playbackRate, setPlaybackRateState] = useState<PlaybackRate>(1);
    const [muted, setMuted] = useState(false);
    const [mutedByFallback, setMutedByFallback] = useState(false);

    const statusRef = useRef<YouTubePlayerStatus>('idle');
    const readyRef = useRef(false);
    const durationRef = useRef(0);
    const rateRef = useRef<PlaybackRate>(1);
    const mutedRef = useRef(false);
    const anchorRef = useRef({ time: 0, at: 0 });
    const endedRef = useRef(false);
    const pendingPlayRef = useRef(false);
    const playConfirmedRef = useRef(false);
    const mutedFallbackTriedRef = useRef(false);
    const fallbackTimeoutRef = useRef<number | undefined>(undefined);

    const updateStatus = useCallback((next: YouTubePlayerStatus): void => {
        statusRef.current = next;
        setStatus(next);
    }, []);

    const updateMuted = useCallback((next: boolean): void => {
        mutedRef.current = next;
        setMuted(next);
    }, []);

    const syncPlaybackRate = useCallback((rate: number): void => {
        const knownRate = PLAYBACK_RATES.find((option) => option === rate);

        if (knownRate !== undefined && knownRate !== rateRef.current) {
            rateRef.current = knownRate;
            setPlaybackRateState(knownRate);
        }
    }, []);

    const setAnchor = useCallback((time: number): void => {
        anchorRef.current = { time, at: performance.now() };
    }, []);

    const send = useCallback(
        (func: string, args: Array<number | string | boolean> = []): void => {
            const win = iframeRef.current?.contentWindow;

            if (win) {
                youtubeCommand(win, func, args);
            }
        },
        [iframeRef],
    );

    const clearFallback = useCallback((): void => {
        if (fallbackTimeoutRef.current !== undefined) {
            window.clearTimeout(fallbackTimeoutRef.current);
            fallbackTimeoutRef.current = undefined;
        }
    }, []);

    const finish = useCallback((): void => {
        endedRef.current = true;
        send('pauseVideo');
        send('seekTo', [0, true]);
        setAnchor(0);
        setCurrentTime(0);
        updateStatus('ended');
    }, [send, setAnchor, updateStatus]);

    const startPlayback = useCallback((): void => {
        send('setPlaybackRate', [rateRef.current]);
        send('playVideo');

        clearFallback();
        fallbackTimeoutRef.current = window.setTimeout(() => {
            if (playConfirmedRef.current) {
                return;
            }

            // Some mobile browsers only allow a programmatic start when the
            // video is muted; the viewer can turn the sound back on.
            if (!mutedFallbackTriedRef.current) {
                mutedFallbackTriedRef.current = true;
                send('mute');
                send('playVideo');
                updateMuted(true);
                setMutedByFallback(true);
                fallbackTimeoutRef.current = window.setTimeout(() => {
                    if (!playConfirmedRef.current) {
                        setStarting(false);
                    }
                }, PLAY_FALLBACK_MS);

                return;
            }

            setStarting(false);
        }, PLAY_FALLBACK_MS);
    }, [clearFallback, send, updateMuted]);

    const play = useCallback((): void => {
        endedRef.current = false;
        playConfirmedRef.current = false;
        setStarting(true);

        if (!readyRef.current) {
            pendingPlayRef.current = true;

            return;
        }

        startPlayback();
    }, [startPlayback]);

    const pause = useCallback((): void => {
        pendingPlayRef.current = false;
        clearFallback();
        setStarting(false);
        send('pauseVideo');

        if (
            statusRef.current === 'playing' ||
            statusRef.current === 'buffering'
        ) {
            updateStatus('paused');
        }
    }, [clearFallback, send, updateStatus]);

    const toggle = useCallback((): void => {
        if (
            statusRef.current === 'playing' ||
            statusRef.current === 'buffering'
        ) {
            pause();

            return;
        }

        play();
    }, [pause, play]);

    const seekTo = useCallback(
        (seconds: number): void => {
            if (statusRef.current === 'idle') {
                return;
            }

            const max = Math.max(
                durationRef.current - END_GUARD_SECONDS - 0.1,
                0,
            );
            const target = Math.min(Math.max(seconds, 0), max);

            send('seekTo', [target, true]);
            setAnchor(target);
            setCurrentTime(target);

            if (statusRef.current === 'ended') {
                endedRef.current = false;
                updateStatus('paused');
            }
        },
        [send, setAnchor, updateStatus],
    );

    const seekBy = useCallback(
        (seconds: number): void => {
            const anchor = anchorRef.current;
            const elapsed =
                statusRef.current === 'playing'
                    ? ((performance.now() - anchor.at) / 1000) * rateRef.current
                    : 0;

            seekTo(anchor.time + elapsed + seconds);
        },
        [seekTo],
    );

    const setPlaybackRate = useCallback(
        (rate: PlaybackRate): void => {
            const anchor = anchorRef.current;

            if (statusRef.current === 'playing') {
                setAnchor(
                    anchor.time +
                        ((performance.now() - anchor.at) / 1000) *
                            rateRef.current,
                );
            }

            rateRef.current = rate;
            setPlaybackRateState(rate);
            send('setPlaybackRate', [rate]);
        },
        [send, setAnchor],
    );

    const toggleMute = useCallback((): void => {
        const nextMuted = !mutedRef.current;

        send(nextMuted ? 'mute' : 'unMute');
        updateMuted(nextMuted);
        setMutedByFallback(false);
    }, [send, updateMuted]);

    useEffect(() => {
        const iframe = iframeRef.current;

        if (!iframe) {
            return;
        }

        let listenAttempts = 0;
        let listenInterval: number | undefined;

        const stopListening = (): void => {
            if (listenInterval !== undefined) {
                window.clearInterval(listenInterval);
                listenInterval = undefined;
            }
        };

        const announce = (): void => {
            const win = iframe.contentWindow;

            listenAttempts += 1;

            if (listenAttempts > LISTEN_MAX_ATTEMPTS) {
                stopListening();

                return;
            }

            if (win) {
                win.postMessage(
                    JSON.stringify({
                        event: 'listening',
                        id: `cubania-review-${videoId}`,
                    }),
                    '*',
                );
            }
        };

        const startListening = (): void => {
            if (readyRef.current) {
                return;
            }

            stopListening();
            listenAttempts = 0;
            announce();
            listenInterval = window.setInterval(announce, LISTEN_INTERVAL_MS);
        };

        const markReady = (): void => {
            if (readyRef.current) {
                return;
            }

            readyRef.current = true;
            setReady(true);
            stopListening();
            send('addEventListener', ['onStateChange']);
            send('addEventListener', ['onPlaybackRateChange']);

            if (pendingPlayRef.current) {
                pendingPlayRef.current = false;
                startPlayback();
            }
        };

        const applyPlayerState = (state: number): void => {
            if (state === YT_PLAYING || state === YT_BUFFERING) {
                // Late reports from before the end-of-video stop are ignored;
                // only play() leaves the ended state.
                if (endedRef.current) {
                    return;
                }

                playConfirmedRef.current = true;
                clearFallback();
                setStarting(false);
                setAnchor(anchorRef.current.time);
                updateStatus(state === YT_PLAYING ? 'playing' : 'buffering');

                return;
            }

            if (state === YT_ENDED) {
                finish();

                return;
            }

            if (state === YT_PAUSED) {
                updateStatus(endedRef.current ? 'ended' : 'paused');
            }
        };

        const onMessage = (event: MessageEvent): void => {
            if (
                !isYouTubeOrigin(event.origin) ||
                event.source !== iframe.contentWindow
            ) {
                return;
            }

            const payload = parseYouTubeMessage(event.data);

            if (!payload) {
                return;
            }

            markReady();

            if (
                payload.event === 'onPlaybackRateChange' &&
                typeof payload.info === 'number'
            ) {
                syncPlaybackRate(payload.info);

                return;
            }

            const info =
                payload.info !== null && typeof payload.info === 'object'
                    ? payload.info
                    : null;

            if (info) {
                if (typeof info.duration === 'number' && info.duration > 0) {
                    durationRef.current = info.duration;
                    setDuration(info.duration);
                }

                if (typeof info.muted === 'boolean') {
                    updateMuted(info.muted);
                }

                if (typeof info.playbackRate === 'number') {
                    syncPlaybackRate(info.playbackRate);
                }

                if (typeof info.currentTime === 'number' && !endedRef.current) {
                    setAnchor(info.currentTime);

                    if (statusRef.current !== 'playing') {
                        setCurrentTime(info.currentTime);
                    }
                }
            }

            const state = readPlayerState(payload);

            if (state !== null) {
                applyPlayerState(state);
            }
        };

        window.addEventListener('message', onMessage);
        iframe.addEventListener('load', startListening);
        startListening();

        return () => {
            window.removeEventListener('message', onMessage);
            iframe.removeEventListener('load', startListening);
            stopListening();
            clearFallback();
        };
    }, [
        clearFallback,
        finish,
        iframeRef,
        send,
        setAnchor,
        startPlayback,
        syncPlaybackRate,
        updateMuted,
        updateStatus,
        videoId,
    ]);

    useEffect(() => {
        if (status !== 'playing') {
            return;
        }

        let frame = 0;

        const tick = (): void => {
            const anchor = anchorRef.current;
            const time =
                anchor.time +
                ((performance.now() - anchor.at) / 1000) * rateRef.current;
            const total = durationRef.current;

            if (total > 0 && time >= total - END_GUARD_SECONDS) {
                finish();

                return;
            }

            setCurrentTime(total > 0 ? Math.min(time, total) : time);
            frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [finish, status]);

    return {
        status,
        ready,
        starting,
        currentTime,
        duration,
        playbackRate,
        muted,
        mutedByFallback,
        play,
        pause,
        toggle,
        seekTo,
        seekBy,
        setPlaybackRate,
        toggleMute,
    };
}
