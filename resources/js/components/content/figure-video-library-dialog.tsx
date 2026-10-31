import { CircleCheck, FileVideo, Folder, RefreshCw } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/i18n/use-translation';
import { figureVideoFailureMessage } from '@/lib/figure-video-failure';
import { formatBytes } from '@/lib/format-media';
import { storableDuration } from '@/lib/read-video-metadata';
import { cn } from '@/lib/utils';
import {
    getFigureVideoLibrary,
    getFigureVideoPreviewUrl,
    linkFigureVideo,
} from '@/services/figureVideoService';
import type {
    FigureVideoLibrary,
    FigureVideoLibraryFile,
} from '@/services/figureVideoService';
import type { FigureItem } from '@/types/content';

/**
 * Lets staff walk the bucket's folders, watch a file and make it the figure's video.
 * It opens on the level's folder. The file stays where it is: nothing is moved.
 */
export default function FigureVideoLibraryDialog({
    figure,
    open,
    onOpenChange,
    onChange,
}: {
    figure: FigureItem;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onChange: (figure: FigureItem) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('content.figureVideo.library.title', {
                            name: figure.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('content.figureVideo.library.description')}
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <LibraryBrowser
                        figure={figure}
                        onClose={() => onOpenChange(false)}
                        onChange={onChange}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

type ListingState =
    | { status: 'loading' }
    | { status: 'ready'; library: FigureVideoLibrary }
    | { status: 'error'; message: string };

function LibraryBrowser({
    figure,
    onClose,
    onChange,
}: {
    figure: FigureItem;
    onClose: () => void;
    onChange: (figure: FigureItem) => void;
}) {
    const { t } = useTranslation();
    const [folder, setFolder] = useState<string | undefined>(undefined);
    const [attempt, setAttempt] = useState(0);
    const [listing, setListing] = useState<ListingState>({ status: 'loading' });
    const [selected, setSelected] = useState<FigureVideoLibraryFile | null>(
        null,
    );
    const [duration, setDuration] = useState<number | null>(null);
    const [linking, setLinking] = useState(false);
    const [linkError, setLinkError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        getFigureVideoLibrary(figure.id, folder)
            .then((library) => {
                if (!cancelled) {
                    setListing({ status: 'ready', library });
                }
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setListing({
                        status: 'error',
                        message: figureVideoFailureMessage(
                            error,
                            t,
                            t('content.figureVideo.library.loadFailed'),
                        ),
                    });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [figure.id, folder, attempt, t]);

    function resetSelection(): void {
        setSelected(null);
        setDuration(null);
        setLinkError(null);
    }

    function openFolder(path: string): void {
        resetSelection();
        setListing({ status: 'loading' });
        setFolder(path);
    }

    function retry(): void {
        setListing({ status: 'loading' });
        setAttempt((current) => current + 1);
    }

    async function link(): Promise<void> {
        if (!selected) {
            return;
        }

        setLinking(true);
        setLinkError(null);

        try {
            onChange(
                await linkFigureVideo(
                    figure.id,
                    selected.key,
                    storableDuration(duration),
                ),
            );
            toast.success(t('content.figureVideo.library.linked'));
            onClose();
        } catch (error) {
            setLinkError(
                figureVideoFailureMessage(
                    error,
                    t,
                    t('content.figureVideo.library.linkFailed'),
                ),
            );
            setLinking(false);
        }
    }

    return (
        <div className="grid min-w-0 gap-4">
            {listing.status === 'loading' ? (
                <p
                    className="flex items-center gap-2 text-sm text-muted-foreground"
                    role="status"
                >
                    <Spinner />
                    {t('content.figureVideo.library.loading')}
                </p>
            ) : null}

            {listing.status === 'error' ? (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-destructive" role="alert">
                        {listing.message}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={retry}
                    >
                        <RefreshCw className="size-4" aria-hidden />
                        {t('content.figureVideo.retry')}
                    </Button>
                </div>
            ) : null}

            {listing.status === 'ready' ? (
                <>
                    <FolderBreadcrumb
                        folder={listing.library.folder}
                        onOpen={openFolder}
                    />
                    <LibraryEntries
                        figure={figure}
                        library={listing.library}
                        selected={selected}
                        onOpenFolder={openFolder}
                        onSelect={(file) => {
                            if (file.key !== selected?.key) {
                                resetSelection();
                                setSelected(file);
                            }
                        }}
                    />
                </>
            ) : null}

            {selected ? (
                <LibraryPreview
                    key={selected.key}
                    figureId={figure.id}
                    fileKey={selected.key}
                    onDuration={setDuration}
                />
            ) : null}

            {linkError ? (
                <p className="text-sm text-destructive" role="alert">
                    {linkError}
                </p>
            ) : null}

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    disabled={linking}
                    onClick={onClose}
                >
                    {t('content.figureVideo.library.close')}
                </Button>
                <Button
                    type="button"
                    disabled={!selected || linking}
                    onClick={() => void link()}
                >
                    {linking
                        ? t('content.figureVideo.library.linking')
                        : t('content.figureVideo.library.link')}
                </Button>
            </DialogFooter>
        </div>
    );
}

function FolderBreadcrumb({
    folder,
    onOpen,
}: {
    folder: string;
    onOpen: (path: string) => void;
}) {
    const { t } = useTranslation();
    const segments = folder === '' ? [] : folder.split('/');

    return (
        <Breadcrumb>
            <BreadcrumbList>
                <BreadcrumbItem>
                    {segments.length === 0 ? (
                        <BreadcrumbPage>
                            {t('content.figureVideo.library.root')}
                        </BreadcrumbPage>
                    ) : (
                        <BreadcrumbLink asChild>
                            <button type="button" onClick={() => onOpen('')}>
                                {t('content.figureVideo.library.root')}
                            </button>
                        </BreadcrumbLink>
                    )}
                </BreadcrumbItem>
                {segments.map((segment, index) => (
                    <Fragment key={segments.slice(0, index + 1).join('/')}>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            {index === segments.length - 1 ? (
                                <BreadcrumbPage>{segment}</BreadcrumbPage>
                            ) : (
                                <BreadcrumbLink asChild>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onOpen(
                                                segments
                                                    .slice(0, index + 1)
                                                    .join('/'),
                                            )
                                        }
                                    >
                                        {segment}
                                    </button>
                                </BreadcrumbLink>
                            )}
                        </BreadcrumbItem>
                    </Fragment>
                ))}
            </BreadcrumbList>
        </Breadcrumb>
    );
}

function LibraryEntries({
    figure,
    library,
    selected,
    onOpenFolder,
    onSelect,
}: {
    figure: FigureItem;
    library: FigureVideoLibrary;
    selected: FigureVideoLibraryFile | null;
    onOpenFolder: (path: string) => void;
    onSelect: (file: FigureVideoLibraryFile) => void;
}) {
    const { t } = useTranslation();
    const rowClassName =
        'flex w-full min-w-0 items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-accent/50';

    if (library.folders.length === 0 && library.files.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('content.figureVideo.library.empty')}
            </p>
        );
    }

    return (
        <ul className="grid max-h-64 gap-1 overflow-y-auto rounded-md border p-1">
            {library.folders.map((path) => (
                <li key={path}>
                    <button
                        type="button"
                        className={rowClassName}
                        onClick={() => onOpenFolder(path)}
                    >
                        <Folder
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                        <span className="truncate">
                            {path.split('/').pop()}
                        </span>
                    </button>
                </li>
            ))}
            {library.files.map((file) => {
                const isCurrent = file.linked_to.some(
                    (linked) => linked.id === figure.id,
                );
                const others = file.linked_to.filter(
                    (linked) => linked.id !== figure.id,
                );

                return (
                    <li key={file.key}>
                        <button
                            type="button"
                            className={cn(
                                rowClassName,
                                selected?.key === file.key && 'bg-accent',
                            )}
                            aria-pressed={selected?.key === file.key}
                            onClick={() => onSelect(file)}
                        >
                            <FileVideo
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                            <span className="grid min-w-0 flex-1 gap-0.5">
                                <span className="truncate">{file.name}</span>
                                {others.length > 0 ? (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {t(
                                            'content.figureVideo.library.linkedTo',
                                            {
                                                names: others
                                                    .map(
                                                        (linked) => linked.name,
                                                    )
                                                    .join(', '),
                                            },
                                        )}
                                    </span>
                                ) : null}
                            </span>
                            {isCurrent ? (
                                <span className="flex shrink-0 items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400">
                                    <CircleCheck
                                        className="size-3.5"
                                        aria-hidden
                                    />
                                    {t('content.figureVideo.library.current')}
                                </span>
                            ) : null}
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {formatBytes(file.size_bytes)}
                            </span>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

type PreviewState =
    | { status: 'loading' }
    | { status: 'ready'; url: string }
    | { status: 'error' };

/**
 * Plays the chosen file so staff can tell it is the right one, and hands back its
 * duration. The signed URL is short, so a failed load offers to ask for a new one.
 */
function LibraryPreview({
    figureId,
    fileKey,
    onDuration,
}: {
    figureId: number;
    fileKey: string;
    onDuration: (seconds: number | null) => void;
}) {
    const { t } = useTranslation();
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState<PreviewState>({ status: 'loading' });
    const boxClassName =
        'flex aspect-video flex-col items-center justify-center gap-3 rounded-md border bg-muted px-4 text-center text-sm text-muted-foreground';

    useEffect(() => {
        let cancelled = false;

        getFigureVideoPreviewUrl(figureId, fileKey)
            .then(({ url }) => {
                if (!cancelled) {
                    setState({ status: 'ready', url });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setState({ status: 'error' });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [figureId, fileKey, attempt]);

    if (state.status === 'loading') {
        return (
            <div className={boxClassName} role="status">
                <Spinner className="size-6" />
                {t('content.figureVideo.player.loading')}
            </div>
        );
    }

    if (state.status === 'error') {
        return (
            <div className={boxClassName} role="alert">
                {t('content.figureVideo.player.error')}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        setState({ status: 'loading' });
                        setAttempt((current) => current + 1);
                    }}
                >
                    <RefreshCw className="size-4" aria-hidden />
                    {t('content.figureVideo.retry')}
                </Button>
            </div>
        );
    }

    return (
        <video
            className="aspect-video w-full rounded-md bg-black"
            controls
            playsInline
            preload="metadata"
            src={state.url}
            onLoadedMetadata={(event) => {
                const seconds = event.currentTarget.duration;

                onDuration(
                    Number.isFinite(seconds) ? Math.round(seconds) : null,
                );
            }}
            onError={() => setState({ status: 'error' })}
        />
    );
}
