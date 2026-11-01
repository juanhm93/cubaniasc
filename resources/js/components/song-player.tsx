import { Download, LoaderCircle, Pause, Play, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useFileMediaPlayer } from '@/hooks/use-file-media-player';
import { useTranslation } from '@/i18n/use-translation';
import { formatDuration } from '@/lib/format-media';
import { cn } from '@/lib/utils';

type SongPlayerProps = {
    title: string;
    artist?: string;
    loadUrl: () => Promise<string>;
    durationSeconds?: number | null;
    downloadHref?: string;
    onDownload?: () => Promise<void>;
    autoLoad?: boolean;
    compact?: boolean;
    onDuration?: (seconds: number) => void;
    className?: string;
};

let playingElement: HTMLMediaElement | null = null;

/**
 * The platform's only song player: the panel, the bucket library and the review all
 * render this one. Playing is the main action, and downloading is a small secondary
 * button that only shows when it is passed in, as a plain link (`downloadHref`) or
 * as a request that goes for a signed URL (`onDownload`). Only one song sounds at a
 * time, whichever player it is in.
 *
 * The signed URL is requested on the first play, so a long list costs nothing until
 * then. With `autoLoad` it is requested on mount: the file is ready when the viewer
 * taps play, and iOS only starts audio that plays right from that tap. `compact` is
 * for table rows: no card, and the timeline only shows once the song has started.
 */
export function SongPlayer({
    title,
    artist,
    loadUrl,
    durationSeconds = null,
    downloadHref,
    onDownload,
    autoLoad = false,
    compact = false,
    onDuration,
    className,
}: SongPlayerProps) {
    const { t } = useTranslation();
    const audioRef = useRef<HTMLAudioElement>(null);
    const [requested, setRequested] = useState(autoLoad);
    const [downloading, setDownloading] = useState(false);
    const [downloadFailed, setDownloadFailed] = useState(false);
    const player = useFileMediaPlayer(audioRef, loadUrl, requested, {
        muteWhenBlocked: false,
    });

    const isActive =
        player.status === 'playing' || player.status === 'buffering';
    const isLoading = player.starting || player.status === 'buffering';
    const canSeek = player.status !== 'idle' && player.duration > 0;
    const totalSeconds = player.duration || durationSeconds || 0;
    const showTimeline = !compact || player.status !== 'idle';
    const elapsed = formatDuration(player.currentTime);
    const total = formatDuration(totalSeconds);
    const playLabel = player.failed
        ? t('songPlayer.retry', { title })
        : isActive
          ? t('songPlayer.pause', { title })
          : t('songPlayer.play', { title });

    useEffect(() => {
        const audio = audioRef.current;

        if (!audio) {
            return;
        }

        const onPlay = (): void => {
            if (playingElement !== audio) {
                playingElement?.pause();
                playingElement = audio;
            }
        };

        audio.addEventListener('play', onPlay);

        return () => {
            audio.removeEventListener('play', onPlay);

            if (playingElement === audio) {
                playingElement = null;
            }
        };
    }, []);

    useEffect(() => {
        if (player.duration > 0) {
            onDuration?.(player.duration);
        }
    }, [onDuration, player.duration]);

    const togglePlayback = (): void => {
        setRequested(true);

        if (player.starting) {
            player.pause();

            return;
        }

        player.toggle();
    };

    const download = async (): Promise<void> => {
        setDownloading(true);
        setDownloadFailed(false);

        try {
            await onDownload?.();
        } catch {
            setDownloadFailed(true);
        } finally {
            setDownloading(false);
        }
    };

    const downloadClassName =
        'flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-card-foreground disabled:opacity-50';

    return (
        <div
            role="group"
            aria-label={title}
            className={cn(
                'grid min-w-0 gap-2',
                !compact &&
                    'rounded-lg border border-border bg-card p-3 text-card-foreground',
                className,
            )}
        >
            <audio ref={audioRef} preload="metadata" />

            <div className="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    className={cn(
                        'flex shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-opacity hover:opacity-90',
                        compact ? 'size-9' : 'size-11',
                    )}
                    onClick={togglePlayback}
                    aria-label={playLabel}
                    data-cubania-cursor="interactive"
                >
                    {isLoading ? (
                        <LoaderCircle
                            className="size-5 animate-spin"
                            aria-hidden
                        />
                    ) : player.failed ? (
                        <RotateCcw className="size-5" aria-hidden />
                    ) : isActive ? (
                        <Pause className="size-5" aria-hidden />
                    ) : (
                        <Play className="size-5" aria-hidden />
                    )}
                </button>

                <span className="grid min-w-0 flex-1">
                    <span className="truncate text-sm font-medium">
                        {title}
                    </span>
                    {artist ? (
                        <span className="truncate text-xs text-muted-foreground">
                            {artist}
                        </span>
                    ) : null}
                </span>

                {downloadHref ? (
                    <a
                        href={downloadHref}
                        className={downloadClassName}
                        aria-label={t('songPlayer.download', { title })}
                        data-cubania-cursor="interactive"
                    >
                        <Download className="size-4" aria-hidden />
                    </a>
                ) : null}

                {onDownload ? (
                    <button
                        type="button"
                        className={downloadClassName}
                        onClick={() => void download()}
                        disabled={downloading}
                        aria-label={t('songPlayer.download', { title })}
                        data-cubania-cursor="interactive"
                    >
                        {downloading ? (
                            <LoaderCircle
                                className="size-4 animate-spin"
                                aria-hidden
                            />
                        ) : (
                            <Download className="size-4" aria-hidden />
                        )}
                    </button>
                ) : null}
            </div>

            {showTimeline ? (
                <div className="flex items-center gap-2 text-xs text-muted-foreground tabular-nums">
                    <span>{elapsed}</span>
                    <input
                        type="range"
                        className="h-1 min-w-0 flex-1 cursor-pointer accent-primary disabled:cursor-default"
                        min={0}
                        max={player.duration || 0}
                        step="any"
                        value={Math.min(player.currentTime, player.duration)}
                        onChange={(event) =>
                            player.seekTo(Number(event.target.value))
                        }
                        disabled={!canSeek}
                        aria-label={t('songPlayer.progress', { title })}
                        aria-valuetext={`${elapsed} / ${total}`}
                        data-cubania-cursor="interactive"
                    />
                    <span>{total}</span>
                </div>
            ) : null}

            {player.failed ? (
                <p className="text-xs text-destructive" role="alert">
                    {t('songPlayer.error')}
                </p>
            ) : null}

            {downloadFailed ? (
                <p className="text-xs text-destructive" role="alert">
                    {t('songPlayer.downloadFailed')}
                </p>
            ) : null}
        </div>
    );
}
