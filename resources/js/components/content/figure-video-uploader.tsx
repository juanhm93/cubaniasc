import {
    CircleCheck,
    Download,
    FolderOpen,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ConfirmDeleteDialog from '@/components/content/confirm-delete-dialog';
import FigureVideoLibraryDialog from '@/components/content/figure-video-library-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/i18n/use-translation';
import { figureVideoFailureMessage } from '@/lib/figure-video-failure';
import { formatBytes, formatDuration } from '@/lib/format-media';
import { readVideoMetadata, storableDuration } from '@/lib/read-video-metadata';
import {
    UploadAbortedError,
    uploadToSignedUrl,
} from '@/lib/upload-to-signed-url';
import {
    confirmFigureVideo,
    figureVideoDownloadUrl,
    removeFigureVideo,
    requestFigureVideoUpload,
} from '@/services/figureVideoService';
import type { FigureItem, FigureVideoSettings } from '@/types/content';

/** Short side above this is heavier than needed for a figure clip. */
const RECOMMENDED_SHORT_SIDE = 720;

type UploadState =
    | { status: 'idle' }
    | { status: 'uploading'; progress: number }
    | { status: 'confirming' }
    | { status: 'error'; message: string };

function isMp4(file: File): boolean {
    return (
        file.type === 'video/mp4' ||
        (file.type === '' && /\.mp4$/i.test(file.name))
    );
}

/**
 * Uploads, replaces, removes and downloads the figure's own MP4. The file goes
 * from the browser straight to the bucket with a signed URL, then the server
 * checks it and makes it the figure's video. A file already in the bucket can be
 * linked instead; removing that one leaves the file in place. The YouTube link is
 * not touched.
 */
export default function FigureVideoUploader({
    figure,
    settings,
    onChange,
    onBusyChange,
}: {
    figure: FigureItem;
    settings: FigureVideoSettings;
    onChange: (figure: FigureItem) => void;
    onBusyChange?: (busy: boolean) => void;
}) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const [state, setState] = useState<UploadState>({ status: 'idle' });
    const [retryFile, setRetryFile] = useState<File | null>(null);
    const [warning, setWarning] = useState<string | null>(null);
    const [removeOpen, setRemoveOpen] = useState(false);
    const [removing, setRemoving] = useState(false);
    const [libraryOpen, setLibraryOpen] = useState(false);
    const busy =
        state.status === 'uploading' ||
        state.status === 'confirming' ||
        removing;
    const videoFile = figure.video_file;

    useEffect(() => {
        onBusyChange?.(busy);
    }, [busy, onBusyChange]);

    useEffect(() => () => abortRef.current?.abort(), []);

    async function upload(file: File): Promise<void> {
        setWarning(null);

        if (!isMp4(file)) {
            setState({
                status: 'error',
                message: t('content.figureVideo.errors.notMp4'),
            });

            return;
        }

        if (file.size > settings.maxSizeBytes) {
            setState({
                status: 'error',
                message: t('content.figureVideo.errors.tooLarge', {
                    size: formatBytes(file.size),
                    max: formatBytes(settings.maxSizeBytes),
                }),
            });

            return;
        }

        setRetryFile(file);
        const controller = new AbortController();
        abortRef.current = controller;
        setState({ status: 'uploading', progress: 0 });

        try {
            const metadata = await readVideoMetadata(file);

            if (
                metadata.width !== null &&
                metadata.height !== null &&
                Math.min(metadata.width, metadata.height) >
                    RECOMMENDED_SHORT_SIDE
            ) {
                setWarning(
                    t('content.figureVideo.warnings.highResolution', {
                        width: metadata.width,
                        height: metadata.height,
                    }),
                );
            }

            const ticket = await requestFigureVideoUpload(figure.id, file.size);

            await uploadToSignedUrl(ticket.url, file, ticket.headers, {
                signal: controller.signal,
                onProgress: ({ loaded, total }) =>
                    setState({
                        status: 'uploading',
                        progress: Math.round((loaded / total) * 100),
                    }),
            });

            setState({ status: 'confirming' });

            const updated = await confirmFigureVideo(
                figure.id,
                ticket.key,
                storableDuration(metadata.durationSeconds),
            );

            setRetryFile(null);
            setState({ status: 'idle' });
            onChange(updated);
            toast.success(t('content.figureVideo.uploaded'));
        } catch (error) {
            setState(
                error instanceof UploadAbortedError
                    ? { status: 'idle' }
                    : {
                          status: 'error',
                          message: figureVideoFailureMessage(
                              error,
                              t,
                              t('content.figureVideo.errors.uploadFailed'),
                          ),
                      },
            );
        } finally {
            if (abortRef.current === controller) {
                abortRef.current = null;
            }
        }
    }

    async function remove(): Promise<void> {
        setRemoving(true);

        try {
            onChange(await removeFigureVideo(figure.id));
            setRemoveOpen(false);
            setState({ status: 'idle' });
            toast.success(
                videoFile.linked
                    ? t('content.figureVideo.unlinked')
                    : t('content.figureVideo.removed'),
            );
        } catch {
            toast.error(t('content.figureVideo.errors.removeFailed'));
        } finally {
            setRemoving(false);
        }
    }

    function handleLinked(updated: FigureItem): void {
        setRetryFile(null);
        setWarning(null);
        setState({ status: 'idle' });
        onChange(updated);
    }

    if (!settings.enabled) {
        return null;
    }

    return (
        <section
            className="grid gap-3 rounded-md border border-dashed border-sidebar-border/70 p-3 dark:border-sidebar-border"
            aria-label={t('content.figureVideo.title')}
        >
            <div className="grid gap-1">
                <h3 className="text-sm font-medium">
                    {t('content.figureVideo.title')}
                </h3>
                <p className="text-xs text-muted-foreground">
                    {t('content.figureVideo.formatHint', {
                        max: formatBytes(settings.maxSizeBytes),
                    })}
                </p>
            </div>

            {videoFile.available ? (
                <div className="grid gap-1">
                    <p className="flex items-center gap-2 text-sm">
                        <CircleCheck
                            className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            aria-hidden
                        />
                        {videoFile.duration_seconds !== null
                            ? t('content.figureVideo.summaryWithDuration', {
                                  size: formatBytes(videoFile.size_bytes ?? 0),
                                  duration: formatDuration(
                                      videoFile.duration_seconds,
                                  ),
                              })
                            : t('content.figureVideo.summary', {
                                  size: formatBytes(videoFile.size_bytes ?? 0),
                              })}
                    </p>
                    {videoFile.linked ? (
                        <p className="text-xs text-muted-foreground">
                            {t('content.figureVideo.linkedHint')}
                        </p>
                    ) : null}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t('content.figureVideo.none')}
                </p>
            )}

            {state.status === 'uploading' ? (
                <div className="grid gap-2">
                    <div
                        className="h-2 w-full overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-label={t('content.figureVideo.progressLabel')}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={state.progress}
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${state.progress}%` }}
                        />
                    </div>
                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span>
                            {t('content.figureVideo.uploading', {
                                percent: state.progress,
                            })}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => abortRef.current?.abort()}
                        >
                            <X className="size-4" aria-hidden />
                            {t('content.figureVideo.cancel')}
                        </Button>
                    </div>
                </div>
            ) : null}

            {state.status === 'confirming' ? (
                <p
                    className="flex items-center gap-2 text-sm text-muted-foreground"
                    role="status"
                >
                    <Spinner />
                    {t('content.figureVideo.confirming')}
                </p>
            ) : null}

            {state.status === 'error' ? (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-destructive" role="alert">
                        {state.message}
                    </p>
                    {retryFile ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => void upload(retryFile)}
                        >
                            {t('content.figureVideo.retry')}
                        </Button>
                    ) : null}
                </div>
            ) : null}

            {warning ? (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                    {warning}
                </p>
            ) : null}

            <div className="flex flex-wrap gap-2">
                <input
                    ref={inputRef}
                    type="file"
                    accept="video/mp4,.mp4"
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden
                    onChange={(event) => {
                        const file = event.target.files?.[0];
                        event.target.value = '';

                        if (file) {
                            void upload(file);
                        }
                    }}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={busy}
                    onClick={() => inputRef.current?.click()}
                >
                    <Upload className="size-4" aria-hidden />
                    {videoFile.available
                        ? t('content.figureVideo.replace')
                        : t('content.figureVideo.choose')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={busy}
                    onClick={() => setLibraryOpen(true)}
                >
                    <FolderOpen className="size-4" aria-hidden />
                    {t('content.figureVideo.chooseFromLibrary')}
                </Button>
                {videoFile.available ? (
                    <>
                        <Button asChild variant="outline" size="sm">
                            <a href={figureVideoDownloadUrl(figure.id)}>
                                <Download className="size-4" aria-hidden />
                                {t('content.figureVideo.download')}
                            </a>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            disabled={busy}
                            onClick={() => setRemoveOpen(true)}
                        >
                            <Trash2 className="size-4" aria-hidden />
                            {videoFile.linked
                                ? t('content.figureVideo.unlink')
                                : t('content.figureVideo.remove')}
                        </Button>
                    </>
                ) : null}
            </div>

            <FigureVideoLibraryDialog
                figure={figure}
                open={libraryOpen}
                onOpenChange={setLibraryOpen}
                onChange={handleLinked}
            />

            <ConfirmDeleteDialog
                open={removeOpen}
                {...(videoFile.linked
                    ? {
                          title: t('content.figureVideo.unlinkTitle'),
                          description: t(
                              'content.figureVideo.unlinkDescription',
                          ),
                          confirmLabel: t('content.figureVideo.unlinkConfirm'),
                          confirmingLabel: t('content.figureVideo.unlinking'),
                      }
                    : {
                          title: t('content.figureVideo.removeTitle'),
                          description: t(
                              'content.figureVideo.removeDescription',
                          ),
                          itemName: figure.name,
                      })}
                confirming={removing}
                onOpenChange={(open) => {
                    if (!removing) {
                        setRemoveOpen(open);
                    }
                }}
                onConfirm={() => void remove()}
            />
        </section>
    );
}
