import axios from 'axios';
import { CircleCheck, FileAudio, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { SongPlayer } from '@/components/song-player';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/i18n/use-translation';
import { formatBytes } from '@/lib/format-media';
import { storableDuration } from '@/lib/read-video-metadata';
import { readableFileName, searchableText } from '@/lib/song-file-name';
import { cn } from '@/lib/utils';
import {
    getSongLibrary,
    getSongPreviewUrl,
} from '@/services/songLibraryService';
import type {
    SongLibrary,
    SongLibraryFile,
} from '@/services/songLibraryService';

export type ChosenSongFile = {
    key: string;
    name: string;
    durationSeconds: number | null;
};

/**
 * Lets staff search the music folder of the bucket, listen to a file and choose it
 * for a song. The file stays where it is: nothing is moved. A file already used by
 * another song cannot be chosen, since one file is one song.
 */
export default function SongLibraryDialog({
    songId,
    open,
    onOpenChange,
    onChoose,
}: {
    songId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onChoose: (file: ChosenSongFile) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.recommendedSongs.library.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('admin.recommendedSongs.library.description')}
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <LibraryBrowser
                        songId={songId}
                        onClose={() => onOpenChange(false)}
                        onChoose={onChoose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

type ListingState =
    | { status: 'loading' }
    | { status: 'ready'; library: SongLibrary }
    | { status: 'error'; message: string };

function LibraryBrowser({
    songId,
    onClose,
    onChoose,
}: {
    songId: number | null;
    onClose: () => void;
    onChoose: (file: ChosenSongFile) => void;
}) {
    const { t } = useTranslation();
    const [attempt, setAttempt] = useState(0);
    const [listing, setListing] = useState<ListingState>({ status: 'loading' });
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<SongLibraryFile | null>(null);
    const [duration, setDuration] = useState<number | null>(null);

    useEffect(() => {
        let cancelled = false;

        getSongLibrary()
            .then((library) => {
                if (!cancelled) {
                    setListing({ status: 'ready', library });
                }
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setListing({
                        status: 'error',
                        message:
                            axios.isAxiosError(error) &&
                            error.response?.status === 503
                                ? t(
                                      'admin.recommendedSongs.library.unavailable',
                                  )
                                : t(
                                      'admin.recommendedSongs.library.loadFailed',
                                  ),
                    });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [attempt, t]);

    const visibleFiles = useMemo(() => {
        const files = listing.status === 'ready' ? listing.library.files : [];
        const query = searchableText(search.trim());

        return files.filter((file) =>
            searchableText(file.name).includes(query),
        );
    }, [listing, search]);

    function retry(): void {
        setListing({ status: 'loading' });
        setAttempt((current) => current + 1);
    }

    function choose(): void {
        if (!selected) {
            return;
        }

        onChoose({
            key: selected.key,
            name: selected.name,
            durationSeconds: storableDuration(duration),
        });
        onClose();
    }

    return (
        <div className="grid min-w-0 gap-4">
            {listing.status === 'loading' ? (
                <p
                    className="flex items-center gap-2 text-sm text-muted-foreground"
                    role="status"
                >
                    <Spinner />
                    {t('admin.recommendedSongs.library.loading')}
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
                        {t('admin.recommendedSongs.library.retry')}
                    </Button>
                </div>
            ) : null}

            {listing.status === 'ready' ? (
                <>
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('admin.recommendedSongs.library.search')}
                        aria-label={t('admin.recommendedSongs.library.search')}
                    />
                    <LibraryFiles
                        songId={songId}
                        files={visibleFiles}
                        isFolderEmpty={listing.library.files.length === 0}
                        selected={selected}
                        onSelect={(file) => {
                            if (file.key !== selected?.key) {
                                setDuration(null);
                                setSelected(file);
                            }
                        }}
                    />
                </>
            ) : null}

            {selected ? (
                <LibraryPreview
                    key={selected.key}
                    file={selected}
                    onDuration={setDuration}
                />
            ) : null}

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    {t('common.cancel')}
                </Button>
                <Button type="button" disabled={!selected} onClick={choose}>
                    {t('admin.recommendedSongs.library.choose')}
                </Button>
            </DialogFooter>
        </div>
    );
}

function LibraryFiles({
    songId,
    files,
    isFolderEmpty,
    selected,
    onSelect,
}: {
    songId: number | null;
    files: SongLibraryFile[];
    isFolderEmpty: boolean;
    selected: SongLibraryFile | null;
    onSelect: (file: SongLibraryFile) => void;
}) {
    const { t } = useTranslation();

    if (files.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {isFolderEmpty
                    ? t('admin.recommendedSongs.library.empty')
                    : t('admin.recommendedSongs.library.noResults')}
            </p>
        );
    }

    return (
        <ul className="grid max-h-64 gap-1 overflow-y-auto rounded-md border p-1">
            {files.map((file) => {
                const isCurrent = file.song !== null && file.song.id === songId;
                const isTaken = file.song !== null && !isCurrent;

                return (
                    <li key={file.key}>
                        <button
                            type="button"
                            className={cn(
                                'flex w-full min-w-0 items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-accent/50 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent',
                                selected?.key === file.key && 'bg-accent',
                            )}
                            aria-pressed={selected?.key === file.key}
                            disabled={isTaken}
                            onClick={() => onSelect(file)}
                        >
                            <FileAudio
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                            <span className="grid min-w-0 flex-1 gap-0.5">
                                <span className="truncate">
                                    {readableFileName(file.name)}
                                </span>
                                {isTaken ? (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {t(
                                            'admin.recommendedSongs.library.takenBy',
                                            { title: file.song?.title ?? '' },
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
                                    {t(
                                        'admin.recommendedSongs.library.current',
                                    )}
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

/**
 * Plays the chosen file so staff can tell it is the right one, and hands back its
 * duration, which is known once the browser reads the file.
 */
function LibraryPreview({
    file,
    onDuration,
}: {
    file: SongLibraryFile;
    onDuration: (seconds: number) => void;
}) {
    const loadUrl = useCallback(() => getSongPreviewUrl(file.key), [file.key]);
    const reportDuration = useCallback(
        (seconds: number) => onDuration(Math.round(seconds)),
        [onDuration],
    );

    return (
        <SongPlayer
            title={readableFileName(file.name)}
            loadUrl={loadUrl}
            onDuration={reportDuration}
            autoLoad
        />
    );
}
