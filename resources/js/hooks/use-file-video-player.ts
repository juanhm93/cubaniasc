import { useCallback, useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import type {
    PlaybackRate,
    VideoPlayer,
    VideoPlayerStatus,
} from '@/hooks/use-youtube-player';

export type FileVideoPlayer = VideoPlayer & {
    failed: boolean;
};

/**
 * Drives a `<video>` with the same controls as useYouTubePlayer, so the page
 * can render one player for both sources. The signed URL is only requested
 * once `enabled` is true: while the viewer stays on YouTube the bucket is not
 * touched. Plays and seeks made before the file is ready wait for it.
 */
export function useFileVideoPlayer(
    videoRef: RefObject<HTMLVideoElement | null>,
    loadUrl: (() => Promise<string>) | null,
    enabled: boolean,
): FileVideoPlayer {
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
        pendingSeekRef.current ??= videoRef.current?.currentTime ?? null;
        sourceLoadedRef.current = false;
        updateFailed(false);
        setLoadAttempt((attempt) => attempt + 1);
    }, [updateFailed, videoRef]);

    const startPlayback = useCallback((): void => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        video.play().catch((error: unknown) => {
            const blocked =
                error instanceof DOMException &&
                error.name === 'NotAllowedError';

            if (!blocked || video.muted) {
                setStarting(false);

                return;
            }

            // Some mobile browsers only allow a programmatic start when the
            // video is muted; the viewer can turn the sound back on.
            video.muted = true;
            setMutedByFallback(true);
            video.play().catch(() => setStarting(false));
        });
    }, [videoRef]);

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
        videoRef.current?.pause();
    }, [videoRef]);

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
            const video = videoRef.current;

            if (!video) {
                return;
            }

            if (!readyRef.current) {
                pendingSeekRef.current = seconds;

                return;
            }

            const max = Math.max(durationRef.current - 0.1, 0);
            const target = Math.min(Math.max(seconds, 0), max);

            video.currentTime = target;
            setCurrentTime(target);

            if (
                statusRef.current === 'ended' ||
                (statusRef.current === 'idle' && target > 0)
            ) {
                updateStatus('paused');
            }
        },
        [updateStatus, videoRef],
    );

    const seekBy = useCallback(
        (seconds: number): void => {
            seekTo((videoRef.current?.currentTime ?? 0) + seconds);
        },
        [seekTo, videoRef],
    );

    const setPlaybackRate = useCallback(
        (rate: PlaybackRate): void => {
            rateRef.current = rate;
            setPlaybackRateState(rate);

            if (videoRef.current) {
                videoRef.current.playbackRate = rate;
            }
        },
        [videoRef],
    );

    const toggleMute = useCallback((): void => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        video.muted = !video.muted;
        setMuted(video.muted);
        setMutedByFallback(false);
    }, [videoRef]);

    useEffect(() => {
        const video = videoRef.current;

        if (!enabled || !video || !loadUrl || sourceLoadedRef.current) {
            return;
        }

        let cancelled = false;

        loadUrl()
            .then((url) => {
                if (!cancelled) {
                    sourceLoadedRef.current = true;
                    video.src = url;
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
    }, [enabled, fail, loadAttempt, loadUrl, videoRef]);

    useEffect(() => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        const onLoadedMetadata = (): void => {
            const total = Number.isFinite(video.duration) ? video.duration : 0;

            durationRef.current = total;
            setDuration(total);
            video.playbackRate = rateRef.current;
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
            if (!video.paused) {
                updateStatus('buffering');
            }
        };

        const onPause = (): void => {
            setStarting(false);

            // A media error is followed by a pause: keep the error state.
            if (!video.ended && !failedRef.current) {
                updateStatus('paused');
            }
        };

        const onEnded = (): void => {
            video.currentTime = 0;
            setCurrentTime(0);
            updateStatus('ended');
        };

        const onTimeUpdate = (): void => {
            if (statusRef.current !== 'playing') {
                setCurrentTime(video.currentTime);
            }
        };

        video.addEventListener('loadedmetadata', onLoadedMetadata);
        video.addEventListener('playing', onPlaying);
        video.addEventListener('waiting', onWaiting);
        video.addEventListener('pause', onPause);
        video.addEventListener('ended', onEnded);
        video.addEventListener('timeupdate', onTimeUpdate);
        video.addEventListener('error', fail);

        return () => {
            video.removeEventListener('loadedmetadata', onLoadedMetadata);
            video.removeEventListener('playing', onPlaying);
            video.removeEventListener('waiting', onWaiting);
            video.removeEventListener('pause', onPause);
            video.removeEventListener('ended', onEnded);
            video.removeEventListener('timeupdate', onTimeUpdate);
            video.removeEventListener('error', fail);

            // Without this the browser keeps downloading the file.
            sourceLoadedRef.current = false;
            video.pause();
            video.removeAttribute('src');
            video.load();
        };
    }, [fail, seekTo, startPlayback, updateReady, updateStatus, videoRef]);

    useEffect(() => {
        if (status !== 'playing') {
            return;
        }

        let frame = 0;

        const tick = (): void => {
            if (videoRef.current) {
                setCurrentTime(videoRef.current.currentTime);
            }

            frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [status, videoRef]);

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
