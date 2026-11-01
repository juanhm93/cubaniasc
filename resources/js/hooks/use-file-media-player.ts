import { useCallback, useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import type {
    PlaybackRate,
    VideoPlayer,
    VideoPlayerStatus,
} from '@/hooks/use-youtube-player';

export type FileMediaPlayer = VideoPlayer & {
    failed: boolean;
};

type FileMediaPlayerOptions = {
    muteWhenBlocked?: boolean;
};

/**
 * Drives a `<video>` or `<audio>` with the same controls as useYouTubePlayer,
 * so a page can render one player for both sources. The signed URL is only
 * requested once `enabled` is true: while the viewer stays on YouTube, or has
 * not pressed play on a song, the bucket is not touched. Plays and seeks made
 * before the file is ready wait for it.
 *
 * `muteWhenBlocked` starts muted when the browser blocks a programmatic start.
 * It suits a media, which can still be watched, but not a song: with it off
 * the file stays loaded and the viewer's next tap plays it.
 */
export function useFileMediaPlayer(
    mediaRef: RefObject<HTMLMediaElement | null>,
    loadUrl: (() => Promise<string>) | null,
    enabled: boolean,
    { muteWhenBlocked = true }: FileMediaPlayerOptions = {},
): FileMediaPlayer {
    const [status, setStatus] = useState<VideoPlayerStatus>('idle');
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);
    const [starting, setStarting] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);
    const [playbackRate, setPlaybackRateState] = useState<PlaybackRate>(1);
    const [muted, setMuted] = useState(false);
    const [mutedByFallback, setMutedByFallback] = useState(false);
    const [loadAttempt, setLoadAttempt] = useState(0);

    const statusRef = useRef<VideoPlayerStatus>('idle');
    const readyRef = useRef(false);
    const failedRef = useRef(false);
    const sourceLoadedRef = useRef(false);
    const durationRef = useRef(0);
    const rateRef = useRef<PlaybackRate>(1);
    const pendingPlayRef = useRef(false);
    const pendingSeekRef = useRef<number | null>(null);

    const updateStatus = useCallback((next: VideoPlayerStatus): void => {
        statusRef.current = next;
        setStatus(next);
    }, []);

    const updateReady = useCallback((next: boolean): void => {
        readyRef.current = next;
        setReady(next);
    }, []);

    const updateFailed = useCallback((next: boolean): void => {
        failedRef.current = next;
        setFailed(next);
    }, []);

    const fail = useCallback((): void => {
        pendingPlayRef.current = false;
        updateFailed(true);
        updateReady(false);
        setStarting(false);
        updateStatus('idle');
    }, [updateFailed, updateReady, updateStatus]);

    const reload = useCallback((): void => {
        pendingSeekRef.current ??= mediaRef.current?.currentTime ?? null;
        sourceLoadedRef.current = false;
        updateFailed(false);
        setLoadAttempt((attempt) => attempt + 1);
    }, [updateFailed, mediaRef]);

    const startPlayback = useCallback((): void => {
        const media = mediaRef.current;

        if (!media) {
            return;
        }

        media.play().catch((error: unknown) => {
            const blocked =
                error instanceof DOMException &&
                error.name === 'NotAllowedError';

            if (!blocked || media.muted || !muteWhenBlocked) {
                setStarting(false);

                return;
            }

            // Some mobile browsers only allow a programmatic start when the
            // media is muted; the viewer can turn the sound back on.
            media.muted = true;
            setMutedByFallback(true);
            media.play().catch(() => setStarting(false));
        });
    }, [mediaRef, muteWhenBlocked]);

    const play = useCallback((): void => {
        if (failedRef.current) {
            reload();
        }

        setStarting(true);

        if (!readyRef.current) {
            pendingPlayRef.current = true;

            return;
        }

        startPlayback();
    }, [reload, startPlayback]);

    const pause = useCallback((): void => {
        pendingPlayRef.current = false;
        setStarting(false);
        mediaRef.current?.pause();
    }, [mediaRef]);

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
            const media = mediaRef.current;

            if (!media) {
                return;
            }

            if (!readyRef.current) {
                pendingSeekRef.current = seconds;

                return;
            }

            const max = Math.max(durationRef.current - 0.1, 0);
            const target = Math.min(Math.max(seconds, 0), max);

            media.currentTime = target;
            setCurrentTime(target);

            if (
                statusRef.current === 'ended' ||
                (statusRef.current === 'idle' && target > 0)
            ) {
                updateStatus('paused');
            }
        },
        [updateStatus, mediaRef],
    );

    const seekBy = useCallback(
        (seconds: number): void => {
            seekTo((mediaRef.current?.currentTime ?? 0) + seconds);
        },
        [seekTo, mediaRef],
    );

    const setPlaybackRate = useCallback(
        (rate: PlaybackRate): void => {
            rateRef.current = rate;
            setPlaybackRateState(rate);

            if (mediaRef.current) {
                mediaRef.current.playbackRate = rate;
            }
        },
        [mediaRef],
    );

    const toggleMute = useCallback((): void => {
        const media = mediaRef.current;

        if (!media) {
            return;
        }

        media.muted = !media.muted;
        setMuted(media.muted);
        setMutedByFallback(false);
    }, [mediaRef]);

    useEffect(() => {
        const media = mediaRef.current;

        if (!enabled || !media || !loadUrl || sourceLoadedRef.current) {
            return;
        }

        let cancelled = false;

        loadUrl()
            .then((url) => {
                if (!cancelled) {
                    sourceLoadedRef.current = true;
                    media.src = url;
                }
            })
            .catch(() => {
                if (!cancelled) {
                    fail();
                }
            });

        return () => {
            cancelled = true;
        };
    }, [enabled, fail, loadAttempt, loadUrl, mediaRef]);

    useEffect(() => {
        const media = mediaRef.current;

        if (!media) {
            return;
        }

        const onLoadedMetadata = (): void => {
            const total = Number.isFinite(media.duration) ? media.duration : 0;

            durationRef.current = total;
            setDuration(total);
            media.playbackRate = rateRef.current;
            updateReady(true);

            if (pendingSeekRef.current !== null) {
                seekTo(pendingSeekRef.current);
                pendingSeekRef.current = null;
            }

            if (pendingPlayRef.current) {
                pendingPlayRef.current = false;
                startPlayback();
            }
        };

        const onPlaying = (): void => {
            setStarting(false);
            updateStatus('playing');
        };

        const onWaiting = (): void => {
            if (!media.paused) {
                updateStatus('buffering');
            }
        };

        const onPause = (): void => {
            setStarting(false);

            // A media error is followed by a pause: keep the error state.
            if (!media.ended && !failedRef.current) {
                updateStatus('paused');
            }
        };

        const onEnded = (): void => {
            media.currentTime = 0;
            setCurrentTime(0);
            updateStatus('ended');
        };

        const onTimeUpdate = (): void => {
            if (statusRef.current !== 'playing') {
                setCurrentTime(media.currentTime);
            }
        };

        media.addEventListener('loadedmetadata', onLoadedMetadata);
        media.addEventListener('playing', onPlaying);
        media.addEventListener('waiting', onWaiting);
        media.addEventListener('pause', onPause);
        media.addEventListener('ended', onEnded);
        media.addEventListener('timeupdate', onTimeUpdate);
        media.addEventListener('error', fail);

        return () => {
            media.removeEventListener('loadedmetadata', onLoadedMetadata);
            media.removeEventListener('playing', onPlaying);
            media.removeEventListener('waiting', onWaiting);
            media.removeEventListener('pause', onPause);
            media.removeEventListener('ended', onEnded);
            media.removeEventListener('timeupdate', onTimeUpdate);
            media.removeEventListener('error', fail);

            // Without this the browser keeps downloading the file.
            sourceLoadedRef.current = false;
            media.pause();
            media.removeAttribute('src');
            media.load();
        };
    }, [fail, seekTo, startPlayback, updateReady, updateStatus, mediaRef]);

    useEffect(() => {
        if (status !== 'playing') {
            return;
        }

        let frame = 0;

        const tick = (): void => {
            if (mediaRef.current) {
                setCurrentTime(mediaRef.current.currentTime);
            }

            frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [status, mediaRef]);

    return {
        status,
        ready,
        failed,
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
