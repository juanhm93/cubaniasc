import { CircleCheck, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import FigureVideoLibraryDialog from '@/components/content/figure-video-library-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/i18n/use-translation';
import { figureVideoFailureMessage } from '@/lib/figure-video-failure';
import { formatBytes } from '@/lib/format-media';
import { readVideoMetadata, storableDuration } from '@/lib/read-video-metadata';
import {
    getFigureVideoPreviewUrl,
    getLevelVideoLibrary,
    linkFigureVideo,
} from '@/services/figureVideoService';
import type {
    BrokenVideoLink,
    LevelVideoLibrary,
    LevelVideoLibraryFile,
} from '@/services/figureVideoService';
import type { ContentLevel, FigureItem } from '@/types/content';

/** Radix's Select cannot hold an empty value. */
const NO_FIGURE = 'none';

type LibraryState =
    | { status: 'loading' }
    | { status: 'ready'; library: LevelVideoLibrary }
    | { status: 'error'; message: string };

type RowStatus =
    | { status: 'linking' }
    | { status: 'done' }
    | { status: 'error'; message: string };

/**
 * The files in the level's folder next to the figure each one seems to belong to.
 * Staff review the pairs, and each one is linked with its own request, so a failure
 * stays on its row and the rest go through. Also lists the level's linked videos
 * that no longer match the bucket.
 */
export default function LevelVideoLinksDialog({
    level,
    open,
    onOpenChange,
    onFigureUpdate,
}: {
    level: ContentLevel;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onFigureUpdate: (figure: FigureItem) => void;
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!busy) {
                    onOpenChange(next);
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('content.figureVideo.levelLinks.title', {
                            level: level.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('content.figureVideo.levelLinks.description')}
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <LevelLinks
                        level={level}
                        onClose={() => onOpenChange(false)}
                        onBusyChange={setBusy}
                        onFigureUpdate={onFigureUpdate}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function LevelLinks({
    level,
    onClose,
    onBusyChange,
    onFigureUpdate,
}: {
    level: ContentLevel;
    onClose: () => void;
    onBusyChange: (busy: boolean) => void;
    onFigureUpdate: (figure: FigureItem) => void;
}) {
    const { t } = useTranslation();
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState<LibraryState>({ status: 'loading' });
    const [assignments, setAssignments] = useState<Record<string, number>>({});
    const [rows, setRows] = useState<Record<string, RowStatus>>({});
    const [linking, setLinking] = useState(false);
    const [relinking, setRelinking] = useState<FigureItem | null>(null);
    const levelId = level.id;

    useEffect(() => {
        let cancelled = false;

        getLevelVideoLibrary(levelId)
            .then((library) => {
                if (cancelled) {
                    return;
                }

                setState({ status: 'ready', library });
                setAssignments(
                    Object.fromEntries(
                        library.files.flatMap((file) =>
                            file.suggested_figure_id === null
                                ? []
                                : [[file.key, file.suggested_figure_id]],
                        ),
                    ),
                );
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setState({
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
    }, [levelId, attempt, t]);

    const pending = Object.entries(assignments).filter(
        ([key]) => rows[key]?.status !== 'done',
    );

    function assign(key: string, value: string): void {
        setAssignments((current) => {
            const next = { ...current };

            if (value === NO_FIGURE) {
                delete next[key];
            } else {
                next[key] = Number(value);
            }

            return next;
        });
    }

    function setRow(key: string, status: RowStatus): void {
        setRows((current) => ({ ...current, [key]: status }));
    }

    /**
     * The duration is a nicety: without it the file is still linked.
     */
    async function durationOf(
        figureId: number,
        key: string,
    ): Promise<number | null> {
        try {
            const { url } = await getFigureVideoPreviewUrl(figureId, key);
            const metadata = await readVideoMetadata(url);

            return storableDuration(metadata.durationSeconds);
        } catch {
            return null;
        }
    }

    async function linkAll(): Promise<void> {
        setLinking(true);
        onBusyChange(true);

        let failures = 0;

        for (const [key, figureId] of pending) {
            setRow(key, { status: 'linking' });

            try {
                onFigureUpdate(
                    await linkFigureVideo(
                        figureId,
                        key,
                        await durationOf(figureId, key),
                    ),
                );
                setRow(key, { status: 'done' });
            } catch (error) {
                failures += 1;
                setRow(key, {
                    status: 'error',
                    message: figureVideoFailureMessage(
                        error,
                        t,
                        t('content.figureVideo.library.linkFailed'),
                    ),
                });
            }
        }

        setLinking(false);
        onBusyChange(false);

        if (failures === 0) {
            toast.success(t('content.figureVideo.levelLinks.done'));
            onClose();
        }
    }

    function removeBrokenLink(figureId: number): void {
        setState((current) =>
            current.status === 'ready'
                ? {
                      status: 'ready',
                      library: {
                          ...current.library,
                          broken_links: current.library.broken_links.filter(
                              (broken) => broken.figure_id !== figureId,
                          ),
                      },
                  }
                : current,
        );
    }

    if (state.status === 'loading') {
        return (
            <p
                className="flex items-center gap-2 text-sm text-muted-foreground"
                role="status"
            >
                <Spinner />
                {t('content.figureVideo.library.loading')}
            </p>
        );
    }

    if (state.status === 'error') {
        return (
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-destructive" role="alert">
                    {state.message}
                </p>
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

    const { library } = state;

    return (
        <div className="grid min-w-0 gap-4">
            <p className="text-xs break-all text-muted-foreground">
                {t('content.figureVideo.levelLinks.folder', {
                    folder: library.folder,
                })}
            </p>

            {library.broken_links.length > 0 ? (
                <BrokenLinks
                    brokenLinks={library.broken_links}
                    disabled={linking}
                    onRelink={(figureId) =>
                        setRelinking(
                            level.level_contents.find(
                                (figure) => figure.id === figureId,
                            ) ?? null,
                        )
                    }
                />
            ) : null}

            {library.files.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('content.figureVideo.levelLinks.empty')}
                </p>
            ) : (
                <ul className="grid gap-2">
                    {library.files.map((file) => (
                        <FileRow
                            key={file.key}
                            file={file}
                            figures={level.level_contents}
                            assignments={assignments}
                            row={rows[file.key]}
                            disabled={linking}
                            onAssign={(value) => assign(file.key, value)}
                        />
                    ))}
                </ul>
            )}

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
                    disabled={linking || pending.length === 0}
                    onClick={() => void linkAll()}
                >
                    {linking
                        ? t('content.figureVideo.library.linking')
                        : t('content.figureVideo.levelLinks.linkAll', {
                              count: pending.length,
                          })}
                </Button>
            </DialogFooter>

            {relinking ? (
                <FigureVideoLibraryDialog
                    figure={relinking}
                    open
                    onOpenChange={(next) => {
                        if (!next) {
                            setRelinking(null);
                        }
                    }}
                    onChange={(updated) => {
                        onFigureUpdate(updated);
                        removeBrokenLink(updated.id);
                    }}
                />
            ) : null}
        </div>
    );
}

function BrokenLinks({
    brokenLinks,
    disabled,
    onRelink,
}: {
    brokenLinks: BrokenVideoLink[];
    disabled: boolean;
    onRelink: (figureId: number) => void;
}) {
    const { t } = useTranslation();

    return (
        <section
            className="grid gap-2 rounded-md border border-destructive/40 p-3"
            aria-label={t('content.figureVideo.levelLinks.brokenTitle')}
        >
            <h3 className="text-sm font-medium">
                {t('content.figureVideo.levelLinks.brokenTitle')}
            </h3>
            <ul className="grid gap-2">
                {brokenLinks.map((broken) => (
                    <li
                        key={broken.figure_id}
                        className="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span className="grid min-w-0 gap-0.5 text-sm">
                            <span>{broken.name}</span>
                            <span className="text-xs break-all text-muted-foreground">
                                {broken.reason === 'missing'
                                    ? t(
                                          'content.figureVideo.levelLinks.brokenMissing',
                                          { key: broken.key },
                                      )
                                    : t(
                                          'content.figureVideo.levelLinks.brokenSizeChanged',
                                          { key: broken.key },
                                      )}
                            </span>
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={disabled}
                            onClick={() => onRelink(broken.figure_id)}
                        >
                            {t('content.figureVideo.levelLinks.relink')}
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function FileRow({
    file,
    figures,
    assignments,
    row,
    disabled,
    onAssign,
}: {
    file: LevelVideoLibraryFile;
    figures: FigureItem[];
    assignments: Record<string, number>;
    row: RowStatus | undefined;
    disabled: boolean;
    onAssign: (value: string) => void;
}) {
    const { t } = useTranslation();
    const assigned = assignments[file.key];
    const takenByOtherRows = new Set(
        Object.entries(assignments)
            .filter(([key]) => key !== file.key)
            .map(([, figureId]) => figureId),
    );
    const available = figures.filter(
        (figure) => !figure.video_file.available || figure.id === assigned,
    );
    const isSuggestion =
        assigned !== undefined && assigned === file.suggested_figure_id;

    return (
        <li className="grid gap-2 rounded-md border p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:items-center">
            <div className="grid min-w-0 gap-0.5">
                <span className="truncate text-sm">{file.name}</span>
                <span className="text-xs text-muted-foreground">
                    {formatBytes(file.size_bytes)}
                </span>
            </div>

            <div className="grid min-w-0 gap-1">
                {file.linked_to.length > 0 ? (
                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                        <CircleCheck
                            className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            aria-hidden
                        />
                        <span className="truncate">
                            {t('content.figureVideo.library.linkedTo', {
                                names: file.linked_to
                                    .map((linked) => linked.name)
                                    .join(', '),
                            })}
                        </span>
                    </p>
                ) : row?.status === 'done' ? (
                    <p className="flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-400">
                        <CircleCheck className="size-4 shrink-0" aria-hidden />
                        {t('content.figureVideo.library.linked')}
                    </p>
                ) : (
                    <div className="flex min-w-0 items-center gap-2">
                        <Select
                            value={assigned?.toString() ?? NO_FIGURE}
                            disabled={disabled}
                            onValueChange={onAssign}
                        >
                            <SelectTrigger
                                className="min-w-0 flex-1"
                                aria-label={t(
                                    'content.figureVideo.levelLinks.figureFor',
                                    { name: file.name },
                                )}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_FIGURE}>
                                    {t(
                                        'content.figureVideo.levelLinks.noFigure',
                                    )}
                                </SelectItem>
                                {available.map((figure) => (
                                    <SelectItem
                                        key={figure.id}
                                        value={figure.id.toString()}
                                        disabled={takenByOtherRows.has(
                                            figure.id,
                                        )}
                                    >
                                        {figure.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {row?.status === 'linking' ? <Spinner /> : null}
                        {isSuggestion && file.match === 'exact' ? (
                            <Badge variant="secondary">
                                {t('content.figureVideo.levelLinks.exact')}
                            </Badge>
                        ) : null}
                        {isSuggestion && file.match === 'similar' ? (
                            <Badge variant="outline">
                                {t('content.figureVideo.levelLinks.similar')}
                            </Badge>
                        ) : null}
                    </div>
                )}
                {row?.status === 'error' ? (
                    <p className="text-xs text-destructive" role="alert">
                        {row.message}
                    </p>
                ) : null}
            </div>
        </li>
    );
}
