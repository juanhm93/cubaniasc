import {
    LoaderCircle,
    Maximize,
    Minimize,
    Pause,
    Play,
    RotateCcw,
    RotateCw,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, KeyboardEvent } from 'react';
import { useFileVideoPlayer } from '@/hooks/use-file-video-player';
import type { VideoSource } from '@/hooks/use-preferred-video-source';
import { formatTimer } from '@/hooks/use-review-panel';
import { PLAYBACK_RATES, useYouTubePlayer } from '@/hooks/use-youtube-player';
import { useTranslation } from '@/i18n/use-translation';

type ReviewVideoPlayerProps = {
    title: string;
    videoId: string | null;
    loadFileUrl: (() => Promise<string>) | null;
};

type FullscreenDocument = Document & {
    webkitFullscreenEnabled?: boolean;
    webkitFullscreenElement?: Element | null;
    webkitExitFullscreen?: () => Promise<void> | void;
};

type FullscreenElement = HTMLElement & {
    webkitRequestFullscreen?: () => Promise<void> | void;
};

const SKIP_SECONDS = 15;

function embedUrl(videoId: string): string {
    const params = new URLSearchParams({
        controls: '0',
        rel: '0',
        modestbranding: '1',
        playsinline: '1',
        fs: '0',
        disablekb: '1',
        iv_load_policy: '3',
        cc_load_policy: '0',
        enablejsapi: '1',
    });

    if (typeof window !== 'undefined') {
        params.set('origin', window.location.origin);
    }

    return `https://www.youtube.com/embed/${videoId}?${params.toString()}`;
}

function fullscreenElement(): Element | null {
    const doc = document as FullscreenDocument;

    return document.fullscreenElement ?? doc.webkitFullscreenElement ?? null;
}

function canUseFullscreen(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    const doc = document as FullscreenDocument;

    return Boolean(document.fullscreenEnabled || doc.webkitFullscreenEnabled);
}

function formatRate(rate: number): string {
    return `${rate}×`;
}

/**
 * The review's only video player. A figure can have two channels: YouTube
 * (channel 1, always first so it spends that resource) and its own MP4 from the
 * bucket (channel 2). Both live in the same stage under the same controls, and
 * the MP4 has no native controls: this component never offers a download.
 */
export function ReviewVideoPlayer({
    title,
    videoId,
    loadFileUrl,
}: ReviewVideoPlayerProps) {
    const { t } = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const [src] = useState(() => (videoId ? embedUrl(videoId) : undefined));
    const [fullscreenSupported] = useState(canUseFullscreen);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [source, setSource] = useState<VideoSource>(
        videoId ? 'youtube' : 'file',
    );
    const youtube = useYouTubePlayer(iframeRef, videoId ?? '');
    const file = useFileVideoPlayer(videoRef, loadFileUrl, source === 'file');
    const player = source === 'file' ? file : youtube;

    const channels: Array<{ source: VideoSource; label: string }> = [
        { source: 'youtube', label: t('review.player.channelOne') },
        { source: 'file', label: t('review.player.channelTwo') },
    ];
    const hasBothChannels = videoId !== null && loadFileUrl !== null;
    const channelFailed = source === 'file' && file.failed;
    const isActive =
        player.status === 'playing' || player.status === 'buffering';
    const showCover = player.status === 'idle' || player.status === 'ended';
    const showPausedOverlay = player.status === 'paused';
    const showSpinner = player.starting || player.status === 'buffering';
    const canSeek = player.status !== 'idle' && player.duration > 0;
    const progressPercent =
        player.duration > 0
            ? Math.min((player.currentTime / player.duration) * 100, 100)
            : 0;

    const coverLabel = channelFailed
        ? t('review.player.channelError')
        : player.status === 'ended'
          ? t('review.player.replay')
          : t('review.player.play');

    useEffect(() => {
        const onFullscreenChange = (): void => {
            setIsFullscreen(fullscreenElement() === containerRef.current);
        };

        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);

        return () => {
            document.removeEventListener(
                'fullscreenchange',
                onFullscreenChange,
            );
            document.removeEventListener(
                'webkitfullscreenchange',
                onFullscreenChange,
            );
        };
    }, []);

    /**
     * Hands playback to the other channel at the same point, so changing
     * channel feels like the same player. A YouTube video that never started
     * ignores the seek and begins from zero.
     */
    const selectSource = (next: VideoSource): void => {
        if (next === source) {
            return;
        }

        const target = next === 'file' ? file : youtube;

        player.pause();
        target.setPlaybackRate(player.playbackRate);

        if (target.muted !== player.muted) {
            target.toggleMute();
        }

        target.seekTo(player.currentTime);

        if (isActive) {
            target.play();
        }

        setSource(next);
    };

    const toggleFullscreen = (): void => {
        const container = containerRef.current as FullscreenElement | null;
        const doc = document as FullscreenDocument;

        if (!container) {
            return;
        }

        if (fullscreenElement()) {
            if (document.exitFullscreen) {
                void document.exitFullscreen();
            } else {
                void doc.webkitExitFullscreen?.();
            }

            return;
        }

        if (container.requestFullscreen) {
            void container.requestFullscreen();
        } else {
            void container.webkitRequestFullscreen?.();
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        const isOwnSurface = event.target === event.currentTarget;

        if (
            (event.key === ' ' && isOwnSurface) ||
            event.key === 'k' ||
            event.key === 'K'
        ) {
            event.preventDefault();
            player.toggle();

            return;
        }

        if (!canSeek) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            player.seekBy(-SKIP_SECONDS);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            player.seekBy(SKIP_SECONDS);
        }
    };

    return (
        <div
            ref={containerRef}
            className={[
                'cubania-review__player',
                isFullscreen ? 'cubania-review__player--fullscreen' : '',
            ]
                .filter(Boolean)
                .join(' ')}
            role="region"
            aria-label={title}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onContextMenu={(event) => event.preventDefault()}
            data-status={player.status}
        >
            <div className="cubania-review__player-stage">
                {videoId ? (
                    <iframe
                        ref={iframeRef}
                        className={[
                            'cubania-review__player-frame',
                            source === 'youtube'
                                ? ''
                                : 'cubania-review__player-source--inactive',
                        ]
                            .filter(Boolean)
                            .join(' ')}
                        src={src}
                        title={title}
                        allow="autoplay; encrypted-media; picture-in-picture"
                        referrerPolicy="strict-origin-when-cross-origin"
                        tabIndex={-1}
                    />
                ) : null}

                {loadFileUrl ? (
                    <video
                        ref={videoRef}
                        className={[
                            'cubania-review__player-video',
                            source === 'file'
                                ? ''
                                : 'cubania-review__player-source--inactive',
                        ]
                            .filter(Boolean)
                            .join(' ')}
                        playsInline
                        preload="metadata"
                        controlsList="nodownload"
                        disablePictureInPicture
                        disableRemotePlayback
                        tabIndex={-1}
                    />
                ) : null}

                {isActive ? (
                    <button
                        type="button"
                        className="cubania-review__player-shield"
                        onClick={player.pause}
                        aria-label={t('review.player.pause')}
                        tabIndex={-1}
                        data-cubania-cursor="interactive"
                    />
                ) : null}

                {showPausedOverlay ? (
                    <button
                        type="button"
                        className="cubania-review__player-overlay"
                        onClick={player.play}
                        aria-label={t('review.player.play')}
                        tabIndex={-1}
                        data-cubania-cursor="interactive"
                    >
                        <span className="cubania-review__player-big-button">
                            {player.starting ? (
                                <LoaderCircle className="cubania-review__player-spin" />
                            ) : (
                                <Play />
                            )}
                        </span>
                    </button>
                ) : null}

                {showCover ? (
                    <button
                        type="button"
                        className={[
                            'cubania-review__player-cover',
                            videoId
                                ? ''
                                : 'cubania-review__player-cover--frame',
                        ]
                            .filter(Boolean)
                            .join(' ')}
                        style={
                            videoId
                                ? {
                                      backgroundImage: `url(https://i.ytimg.com/vi/${videoId}/hqdefault.jpg)`,
                                  }
                                : undefined
                        }
                        onClick={player.play}
                        aria-label={coverLabel}
                        disabled={player.starting}
                        data-cubania-cursor="interactive"
                    >
                        <span className="cubania-review__player-big-button">
                            {player.starting ? (
                                <LoaderCircle className="cubania-review__player-spin" />
                            ) : player.status === 'ended' || channelFailed ? (
                                <RotateCcw />
                            ) : (
                                <Play />
                            )}
                        </span>
                        {(player.status === 'ended' || channelFailed) &&
                        !player.starting ? (
                            <span className="cubania-review__player-cover-label">
                                {coverLabel}
                            </span>
                        ) : null}
                    </button>
                ) : null}

                {showSpinner && isActive ? (
                    <span
                        className="cubania-review__player-buffering"
                        role="status"
                        aria-label={t('review.player.loading')}
                    >
                        <LoaderCircle className="cubania-review__player-spin" />
                    </span>
                ) : null}

                {player.mutedByFallback && player.muted && isActive ? (
                    <button
                        type="button"
                        className="cubania-review__player-sound-hint"
                        onClick={player.toggleMute}
                        data-cubania-cursor="interactive"
                    >
                        <VolumeX />
                        {t('review.player.unmute')}
                    </button>
                ) : null}
            </div>

            <div className="cubania-review__player-controls">
                <div className="cubania-review__player-timeline">
                    <span className="cubania-review__player-time">
                        {formatTimer(Math.floor(player.currentTime))}
                    </span>
                    <input
                        type="range"
                        className="cubania-review__player-progress"
                        min={0}
                        max={player.duration || 0}
                        step="any"
                        value={Math.min(player.currentTime, player.duration)}
                        onChange={(event) =>
                            player.seekTo(Number(event.target.value))
                        }
                        disabled={!canSeek}
                        aria-label={t('review.player.progress')}
                        aria-valuetext={`${formatTimer(Math.floor(player.currentTime))} / ${formatTimer(Math.floor(player.duration))}`}
                        style={
                            {
                                '--player-progress': `${progressPercent}%`,
                            } as CSSProperties
                        }
                        data-cubania-cursor="interactive"
                    />
                    <span className="cubania-review__player-time">
                        {formatTimer(Math.floor(player.duration))}
                    </span>
                </div>

                <div className="cubania-review__player-buttons">
                    <div className="cubania-review__player-transport">
                        <button
                            type="button"
                            className="cubania-review__player-icon-button"
                            onClick={() => player.seekBy(-SKIP_SECONDS)}
                            disabled={!canSeek}
                            aria-label={t('review.player.rewind')}
                            data-cubania-cursor="interactive"
                        >
                            <RotateCcw />
                            <span className="cubania-review__player-skip-label">
                                {SKIP_SECONDS}
                            </span>
                        </button>
                        <button
                            type="button"
                            className="cubania-review__player-play"
                            onClick={player.toggle}
                            aria-label={
                                isActive
                                    ? t('review.player.pause')
                                    : t('review.player.play')
                            }
                            data-cubania-cursor="interactive"
                        >
                            {isActive ? <Pause /> : <Play />}
                        </button>
                        <button
                            type="button"
                            className="cubania-review__player-icon-button"
                            onClick={() => player.seekBy(SKIP_SECONDS)}
                            disabled={!canSeek}
                            aria-label={t('review.player.forward')}
                            data-cubania-cursor="interactive"
                        >
                            <RotateCw />
                            <span className="cubania-review__player-skip-label">
                                {SKIP_SECONDS}
                            </span>
                        </button>
                    </div>

                    {hasBothChannels ? (
                        <div
                            className="cubania-review__player-speed"
                            role="group"
                            aria-label={t('review.player.channel')}
                        >
                            {channels.map((channel) => (
                                <button
                                    key={channel.source}
                                    type="button"
                                    className={[
                                        'cubania-review__player-speed-option',
                                        source === channel.source
                                            ? 'cubania-review__player-speed-option--active'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    onClick={() => selectSource(channel.source)}
                                    aria-pressed={source === channel.source}
                                    data-cubania-cursor="interactive"
                                >
                                    {channel.label}
                                </button>
                            ))}
                        </div>
                    ) : null}

                    <div className="cubania-review__player-extras">
                        <div
                            className="cubania-review__player-speed"
                            role="group"
                            aria-label={t('review.player.speed')}
                        >
                            {PLAYBACK_RATES.map((rate) => (
                                <button
                                    key={rate}
                                    type="button"
                                    className={[
                                        'cubania-review__player-speed-option',
                                        player.playbackRate === rate
                                            ? 'cubania-review__player-speed-option--active'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    onClick={() => player.setPlaybackRate(rate)}
                                    aria-pressed={player.playbackRate === rate}
                                    data-cubania-cursor="interactive"
                                >
                                    {formatRate(rate)}
                                </button>
                            ))}
                        </div>
                        <button
                            type="button"
                            className="cubania-review__player-icon-button"
                            onClick={player.toggleMute}
                            aria-label={
                                player.muted
                                    ? t('review.player.unmute')
                                    : t('review.player.mute')
                            }
                            data-cubania-cursor="interactive"
                        >
                            {player.muted ? <VolumeX /> : <Volume2 />}
                        </button>
                        {fullscreenSupported ? (
                            <button
                                type="button"
                                className="cubania-review__player-icon-button"
                                onClick={toggleFullscreen}
                                aria-label={
                                    isFullscreen
                                        ? t('review.player.exitFullscreen')
                                        : t('review.player.fullscreen')
                                }
                                data-cubania-cursor="interactive"
                            >
                                {isFullscreen ? <Minimize /> : <Maximize />}
                            </button>
                        ) : null}
                    </div>
                </div>
            </div>
        </div>
    );
}
