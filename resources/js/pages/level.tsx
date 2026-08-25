import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import LevelContentItem from '@/components/items/level-content-item';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';
import {
    deleteLevelContent,
    getLevel,
    updateLevelContent,
} from '@/services/levelService';
import { levels as levelsIndexRoute } from '@/routes';

type LevelContentItem = {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    video_url: string | null;
};

type LevelWithContents = {
    id: number;
    name: string;
    description: string | null;
    level_contents: LevelContentItem[];
};

function normalizeLevelContent(raw: unknown): LevelContentItem {
    const c = raw as Partial<LevelContentItem>;

    return {
        id: c.id ?? 0,
        name: c.name ?? '',
        description: c.description ?? null,
        sort_order: c.sort_order ?? 0,
        video_url: c.video_url ?? null,
    };
}

function normalizeLevel(data: unknown): LevelWithContents {
    const d = data as Partial<LevelWithContents> & {
        level_contents?: unknown[];
        levelContents?: unknown[];
    };
    const contents = d.level_contents ?? d.levelContents ?? [];

    return {
        id: d.id ?? 0,
        name: d.name ?? '',
        description: d.description ?? null,
        level_contents: Array.isArray(contents)
            ? contents.map(normalizeLevelContent)
            : [],
    };
}

function LevelVideoPreview({
    url,
    emptyMessage,
    previewTitle,
}: {
    url: string | null | undefined;
    emptyMessage: string;
    previewTitle: string;
}) {
    const trimmed = url?.trim() ?? '';

    if (trimmed === '') {
        return (
            <div
                className="flex aspect-video items-center justify-center rounded-md border bg-muted px-4 text-center text-sm text-muted-foreground"
                role="status"
            >
                {emptyMessage}
            </div>
        );
    }

    const ytMatch = trimmed.match(
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
    );

    if (ytMatch) {
        return (
            <iframe
                title={previewTitle}
                className="aspect-video w-full rounded-md border-0"
                src={`https://www.youtube.com/embed/${ytMatch[1]}`}
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowFullScreen
            />
        );
    }

    return (
        <video
            className="aspect-video w-full rounded-md bg-black"
            controls
            playsInline
            src={trimmed}
        />
    );
}

export default function Level() {
    const { t } = useTranslation();
    const levelId = usePage().props.level as string | undefined;

    const [level, setLevel] = useState<LevelWithContents | null>(null);
    const [videoContent, setVideoContent] = useState<LevelContentItem | null>(
        null,
    );
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [addOpen, setAddOpen] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [editingContent, setEditingContent] =
        useState<LevelContentItem | null>(null);
    const [editName, setEditName] = useState('');
    const [editDescription, setEditDescription] = useState('');
    const [editVideoUrl, setEditVideoUrl] = useState('');
    const [editFormErrors, setEditFormErrors] = useState<
        Record<string, string>
    >({});
    const [editSubmitting, setEditSubmitting] = useState(false);

    function beginEditContent(content: LevelContentItem) {
        setEditName(content.name);
        setEditDescription(content.description ?? '');
        setEditVideoUrl(content.video_url ?? '');
        setEditFormErrors({});
        setEditingContent(content);
    }

    useEffect(() => {
        if (levelId === undefined || levelId === '') {
            return;
        }

        let cancelled = false;

        getLevel(levelId).then((data) => {
            if (!cancelled) {
                setLevel(normalizeLevel(data));
            }
        });

        return () => {
            cancelled = true;
        };
    }, [levelId]);

    async function handleRemove(contentId: number): Promise<void> {
        setDeletingId(contentId);

        try {
            await deleteLevelContent(contentId);
            setLevel((prev) =>
                prev
                    ? {
                          ...prev,
                          level_contents: prev.level_contents.filter(
                              (c) => c.id !== contentId,
                          ),
                      }
                    : null,
            );
            toast.success(t('levels.show.contentRemoved'));
            setVideoContent((current) =>
                current?.id === contentId ? null : current,
            );
        } catch {
            toast.error(t('levels.show.couldNotRemove'));
        } finally {
            setDeletingId(null);
        }
    }

    async function handleCreateLevelContent(e: React.FormEvent) {
        e.preventDefault();
        setFormErrors({});
        setSubmitting(true);

        try {
            /* crear figura: pendiente de API */
        } finally {
            setSubmitting(false);
        }
    }

    async function handleUpdateLevelContent(e: React.FormEvent) {
        e.preventDefault();

        if (!editingContent) {
            return;
        }

        setEditFormErrors({});
        setEditSubmitting(true);

        try {
            const payload = {
                name: editName,
                description: editDescription.trim() === ''
                    ? null
                    : editDescription,
                video_url:
                    editVideoUrl.trim() === '' ? null : editVideoUrl.trim(),
            };

            const updated = await updateLevelContent(
                editingContent.id,
                payload,
            ) as {
                name?: string;
                description?: string | null;
                video_url?: string | null;
            };

            const merged = normalizeLevelContent(updated);

            setLevel((prev) =>
                prev
                    ? {
                          ...prev,
                          level_contents: prev.level_contents.map((c) =>
                              c.id === editingContent.id ? merged : c,
                          ),
                      }
                    : null,
            );
            setVideoContent((current) =>
                current?.id === editingContent.id ? merged : current,
            );
            setEditingContent(null);
            toast.success(t('levels.show.figureUpdated'));
        } catch (err: unknown) {
            const ax = err as {
                response?: {
                    status?: number;
                    data?: { errors?: Record<string, string[]> };
                };
            };

            if (
                ax.response?.status === 422 &&
                ax.response.data?.errors
            ) {
                const flat: Record<string, string> = {};

                for (const [key, msgs] of Object.entries(
                    ax.response.data.errors,
                )) {
                    if (Array.isArray(msgs) && msgs[0]) {
                        flat[key] = msgs[0];
                    }
                }

                setEditFormErrors(flat);
            } else {
                toast.error(t('levels.show.couldNotSave'));
            }
        } finally {
            setEditSubmitting(false);
        }
    }

    const contents = level?.level_contents ?? [];

    return (
        <>
            <Head
                title={
                    level?.name
                        ? level.name
                        : t('levels.show.headTitle')
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div
                    className={cn(
                        'relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border',
                    )}
                >
                    {level && (
                        <div className="rounded-[4px] border border-sidebar-border/70 bg-card px-4 py-4 shadow-sm dark:border-sidebar-border">
                            <h1 className="text-2xl font-bold">{level.name}</h1>
                            {level.description ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {level.description}
                                </p>
                            ) : null}
                        </div>
                    )}

                    {contents.length === 0 && level ? (
                        <p className="text-sm text-muted-foreground">
                            {t('levels.show.noContent')}
                        </p>
                    ) : null}

                    <div className="relative flex min-h-[100vh] flex-1 flex-col gap-2 overflow-hidden rounded-xl border border-[#e0e0e0] border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                        <Dialog
                            open={addOpen}
                            onOpenChange={(open) => {
                                setAddOpen(open);

                                if (open) {
                                    setName('');
                                    setDescription('');
                                    setFormErrors({});
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <button
                                    type="button"
                                    className={cn(
                                        'flex min-h-[72px] w-full items-center justify-center rounded-[4px] border border-dashed border-sidebar-border/70 bg-card/50 py-6 shadow-sm transition-colors',
                                        'hover:border-sidebar-border hover:bg-accent/30 dark:border-sidebar-border',
                                    )}
                                >
                                    <Plus
                                        className="size-8 text-muted-foreground"
                                        strokeWidth={1.5}
                                        aria-hidden
                                    />
                                    <span className="sr-only">
                                        {t('levels.show.addFigureSr')}
                                    </span>
                                </button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={handleCreateLevelContent}>
                                    <DialogHeader>
                                        <DialogTitle>
                                            {t('levels.show.newFigure')}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {t('levels.show.newFigureDescription')}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="level-name">
                                                {t('common.name')}
                                            </Label>
                                            <Input
                                                id="level-name"
                                                name="name"
                                                value={name}
                                                onChange={(ev) =>
                                                    setName(ev.target.value)
                                                }
                                                placeholder={t(
                                                    'levels.show.namePlaceholder',
                                                )}
                                                required
                                                autoComplete="off"
                                                disabled={submitting}
                                                maxLength={120}
                                                aria-invalid={
                                                    formErrors.name
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={formErrors.name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="level-description">
                                                {t(
                                                    'common.descriptionOptional',
                                                )}
                                            </Label>
                                            <textarea
                                                id="level-description"
                                                name="description"
                                                value={description}
                                                onChange={(ev) =>
                                                    setDescription(
                                                        ev.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'levels.show.descriptionPlaceholder',
                                                )}
                                                rows={3}
                                                disabled={submitting}
                                                maxLength={500}
                                                className={cn(
                                                    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm',
                                                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                    'disabled:cursor-not-allowed disabled:opacity-50',
                                                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                                                )}
                                                aria-invalid={
                                                    formErrors.description
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={formErrors.description}
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={submitting}
                                            onClick={() => setAddOpen(false)}
                                        >
                                            {t('common.cancel')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={submitting}
                                        >
                                            {submitting
                                                ? t('common.saving')
                                                : t('common.create')}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <Dialog
                            open={editingContent !== null}
                            onOpenChange={(open) => {
                                if (!open) {
                                    setEditingContent(null);
                                }
                            }}
                        >
                            <DialogContent>
                                <form onSubmit={handleUpdateLevelContent}>
                                    <DialogHeader>
                                        <DialogTitle>
                                            {t('levels.show.editFigure')}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {t('levels.show.editFigureDescription')}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-level-name">
                                                {t('common.name')}
                                            </Label>
                                            <Input
                                                id="edit-level-name"
                                                name="name"
                                                value={editName}
                                                onChange={(ev) =>
                                                    setEditName(ev.target.value)
                                                }
                                                placeholder={t(
                                                    'levels.show.namePlaceholder',
                                                )}
                                                required
                                                autoComplete="off"
                                                disabled={editSubmitting}
                                                maxLength={120}
                                                aria-invalid={
                                                    editFormErrors.name
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={editFormErrors.name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-level-description">
                                                {t(
                                                    'common.descriptionOptional',
                                                )}
                                            </Label>
                                            <textarea
                                                id="edit-level-description"
                                                name="description"
                                                value={editDescription}
                                                onChange={(ev) =>
                                                    setEditDescription(
                                                        ev.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'levels.show.descriptionPlaceholder',
                                                )}
                                                rows={3}
                                                disabled={editSubmitting}
                                                maxLength={500}
                                                className={cn(
                                                    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm',
                                                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                    'disabled:cursor-not-allowed disabled:opacity-50',
                                                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                                                )}
                                                aria-invalid={
                                                    editFormErrors.description
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={
                                                    editFormErrors.description
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-level-video-url">
                                                {t('levels.show.videoUrlOptional')}
                                            </Label>
                                            <Input
                                                id="edit-level-video-url"
                                                name="video_url"
                                                type="url"
                                                inputMode="url"
                                                value={editVideoUrl}
                                                onChange={(ev) =>
                                                    setEditVideoUrl(
                                                        ev.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'levels.show.videoUrlPlaceholder',
                                                )}
                                                autoComplete="off"
                                                disabled={editSubmitting}
                                                maxLength={255}
                                                aria-invalid={
                                                    editFormErrors.video_url
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={
                                                    editFormErrors.video_url
                                                }
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={editSubmitting}
                                            onClick={() =>
                                                setEditingContent(null)
                                            }
                                        >
                                            {t('common.cancel')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={editSubmitting}
                                        >
                                            {editSubmitting
                                                ? t('common.saving')
                                                : t('common.save')}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <ul className="flex flex-col gap-3">
                            {contents.map((content) => (
                                <LevelContentItem
                                    key={content.id.toString()}
                                    levelContent={content}
                                    deletingId={deletingId}
                                    handleRemove={handleRemove}
                                    setVideoContent={setVideoContent}
                                    onEdit={beginEditContent}
                                />
                            ))}
                        </ul>
                    </div>
                </div>
            </div>

            <Dialog
                open={videoContent !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setVideoContent(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {videoContent?.name ?? t('common.video')}
                        </DialogTitle>
                        {!videoContent?.video_url?.trim() ? (
                            <DialogDescription>
                                {t('levels.show.videoDialogDescription')}
                            </DialogDescription>
                        ) : null}
                    </DialogHeader>
                    <LevelVideoPreview
                        url={videoContent?.video_url}
                        emptyMessage={t('levels.show.videoPreviewEmpty')}
                        previewTitle={t('levels.show.videoPreviewTitle')}
                    />
                </DialogContent>
            </Dialog>
        </>
    );
}

Level.layout = {
    breadcrumbs: [
        {
            title: 'levels.show.breadcrumb',
            href: levelsIndexRoute.url(),
        },
    ],
};
