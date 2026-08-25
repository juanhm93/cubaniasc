import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import ConfirmDeleteDialog from '@/components/content/confirm-delete-dialog';
import LevelVideoPreview from '@/components/content/level-video-preview';
import SortableList from '@/components/content/sortable-list';
import InputError from '@/components/input-error';
import LevelContentItem from '@/components/items/level-content-item';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useIsAdmin } from '@/hooks/use-is-admin';
import { mapValidationErrors } from '@/lib/map-validation-errors';
import { cn } from '@/lib/utils';
import { index as contentIndex, show as contentShow } from '@/routes/content';
import { show as contentLevelShow } from '@/routes/content/levels';
import {
    createLevelContent,
    deleteLevelContent,
    reorderLevelContents,
    updateLevelContent,
} from '@/services/levelService';
import { normalizeFigure, normalizeLevel } from '@/types/content';
import type { ContentLevel, DanceTypeCard, FigureItem } from '@/types/content';

const textareaClassName = cn(
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'disabled:cursor-not-allowed disabled:opacity-50',
    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
);

export default function ContentLevel({
    danceType,
    level: initialLevel,
    canDelete = false,
}: {
    danceType: DanceTypeCard;
    level: ContentLevel;
    canDelete?: boolean;
}) {
    const isAdmin = useIsAdmin();
    const showDelete = canDelete && isAdmin;
    const [level, setLevel] = useState<ContentLevel>(() =>
        normalizeLevel(initialLevel),
    );
    const [videoContent, setVideoContent] = useState<FigureItem | null>(null);
    const [deleting, setDeleting] = useState<FigureItem | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [addOpen, setAddOpen] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [videoUrl, setVideoUrl] = useState('');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [editingContent, setEditingContent] = useState<FigureItem | null>(
        null,
    );
    const [editName, setEditName] = useState('');
    const [editDescription, setEditDescription] = useState('');
    const [editVideoUrl, setEditVideoUrl] = useState('');
    const [editFormErrors, setEditFormErrors] = useState<
        Record<string, string>
    >({});
    const [editSubmitting, setEditSubmitting] = useState(false);

    function beginEditContent(content: FigureItem): void {
        setEditName(content.name);
        setEditDescription(content.description ?? '');
        setEditVideoUrl(content.video_url ?? '');
        setEditFormErrors({});
        setEditingContent(content);
    }

    async function handleRemove(): Promise<void> {
        if (!deleting) {
            return;
        }

        setDeletingId(deleting.id);

        try {
            await deleteLevelContent(deleting.id);
            setLevel((prev) => ({
                ...prev,
                level_contents: prev.level_contents.filter(
                    (content) => content.id !== deleting.id,
                ),
            }));
            toast.success('Figura eliminada');
            setVideoContent((current) =>
                current?.id === deleting.id ? null : current,
            );
            setDeleting(null);
        } catch {
            toast.error('No se pudo eliminar la figura');
        } finally {
            setDeletingId(null);
        }
    }

    async function handleCreateLevelContent(e: React.FormEvent): Promise<void> {
        e.preventDefault();
        setFormErrors({});
        setSubmitting(true);

        try {
            const created = normalizeFigure(
                await createLevelContent(level.id, {
                    name,
                    description: description.trim() === '' ? null : description,
                    video_url: videoUrl.trim() === '' ? null : videoUrl.trim(),
                }),
            );
            setLevel((prev) => ({
                ...prev,
                level_contents: [...prev.level_contents, created],
            }));
            toast.success('Figura creada');
            setAddOpen(false);
            setName('');
            setDescription('');
            setVideoUrl('');
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const mapped = mapValidationErrors(error.response.data);

                if (mapped) {
                    setFormErrors(mapped);
                } else {
                    toast.error('No se pudo crear la figura');
                }
            } else {
                toast.error('No se pudo crear la figura');
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleUpdateLevelContent(e: React.FormEvent): Promise<void> {
        e.preventDefault();

        if (!editingContent) {
            return;
        }

        setEditFormErrors({});
        setEditSubmitting(true);

        try {
            const merged = normalizeFigure(
                await updateLevelContent(editingContent.id, {
                    name: editName,
                    description:
                        editDescription.trim() === '' ? null : editDescription,
                    video_url:
                        editVideoUrl.trim() === '' ? null : editVideoUrl.trim(),
                }),
            );

            setLevel((prev) => ({
                ...prev,
                level_contents: prev.level_contents.map((content) =>
                    content.id === editingContent.id ? merged : content,
                ),
            }));
            setVideoContent((current) =>
                current?.id === editingContent.id ? merged : current,
            );
            setEditingContent(null);
            toast.success('Figura actualizada');
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const mapped = mapValidationErrors(error.response.data);

                if (mapped) {
                    setEditFormErrors(mapped);
                } else {
                    toast.error('No se pudo guardar los cambios');
                }
            } else {
                toast.error('No se pudo guardar los cambios');
            }
        } finally {
            setEditSubmitting(false);
        }
    }

    async function handleReorder(orderedIds: number[]): Promise<void> {
        try {
            await reorderLevelContents(level.id, orderedIds);
        } catch (error) {
            toast.error('No se pudo guardar el orden');

            throw error;
        }
    }

    const contents = level.level_contents;

    return (
        <>
            <Head title={level.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="rounded-[4px] border border-sidebar-border/70 bg-card px-4 py-4 shadow-sm dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            {danceType.name}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold">
                            {level.name}
                        </h1>
                        {level.description ? (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {level.description}
                            </p>
                        ) : null}
                    </div>

                    {contents.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todavía no hay figuras en este nivel.
                        </p>
                    ) : null}

                    <div className="relative flex min-h-[100vh] flex-1 flex-col gap-2 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                        <Dialog
                            open={addOpen}
                            onOpenChange={(open) => {
                                setAddOpen(open);

                                if (open) {
                                    setName('');
                                    setDescription('');
                                    setVideoUrl('');
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
                                        Agregar figura
                                    </span>
                                </button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={handleCreateLevelContent}>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Nueva figura
                                        </DialogTitle>
                                        <DialogDescription>
                                            Agrega un nombre, una descripción
                                            opcional y la URL del video.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="figure-name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="figure-name"
                                                name="name"
                                                value={name}
                                                onChange={(event) =>
                                                    setName(event.target.value)
                                                }
                                                placeholder="Ej. Enchufla"
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
                                            <Label htmlFor="figure-description">
                                                Descripción (opcional)
                                            </Label>
                                            <textarea
                                                id="figure-description"
                                                name="description"
                                                value={description}
                                                onChange={(event) =>
                                                    setDescription(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Resumen breve"
                                                rows={3}
                                                disabled={submitting}
                                                maxLength={500}
                                                className={textareaClassName}
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
                                        <div className="grid gap-2">
                                            <Label htmlFor="figure-video-url">
                                                URL del video (opcional)
                                            </Label>
                                            <Input
                                                id="figure-video-url"
                                                name="video_url"
                                                type="url"
                                                inputMode="url"
                                                value={videoUrl}
                                                onChange={(event) =>
                                                    setVideoUrl(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="https://..."
                                                autoComplete="off"
                                                disabled={submitting}
                                                maxLength={255}
                                                aria-invalid={
                                                    formErrors.video_url
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={formErrors.video_url}
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
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={submitting}
                                        >
                                            {submitting
                                                ? 'Guardando…'
                                                : 'Crear'}
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
                                            Editar figura
                                        </DialogTitle>
                                        <DialogDescription>
                                            Modifica el nombre, la descripción o
                                            la URL del video.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-figure-name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="edit-figure-name"
                                                name="name"
                                                value={editName}
                                                onChange={(event) =>
                                                    setEditName(
                                                        event.target.value,
                                                    )
                                                }
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
                                            <Label htmlFor="edit-figure-description">
                                                Descripción (opcional)
                                            </Label>
                                            <textarea
                                                id="edit-figure-description"
                                                name="description"
                                                value={editDescription}
                                                onChange={(event) =>
                                                    setEditDescription(
                                                        event.target.value,
                                                    )
                                                }
                                                rows={3}
                                                disabled={editSubmitting}
                                                maxLength={500}
                                                className={textareaClassName}
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
                                            <Label htmlFor="edit-figure-video-url">
                                                URL del video (opcional)
                                            </Label>
                                            <Input
                                                id="edit-figure-video-url"
                                                name="video_url"
                                                type="url"
                                                inputMode="url"
                                                value={editVideoUrl}
                                                onChange={(event) =>
                                                    setEditVideoUrl(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="https://..."
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
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={editSubmitting}
                                        >
                                            {editSubmitting
                                                ? 'Guardando…'
                                                : 'Guardar'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <SortableList
                            items={contents}
                            className="gap-3"
                            onChange={(levelContents) =>
                                setLevel((prev) => ({
                                    ...prev,
                                    level_contents: levelContents,
                                }))
                            }
                            onReorder={handleReorder}
                            renderItem={(content, handleProps, isDragging) => (
                                <LevelContentItem
                                    key={content.id.toString()}
                                    levelContent={content}
                                    deletingId={deletingId}
                                    onDelete={
                                        showDelete ? setDeleting : undefined
                                    }
                                    setVideoContent={setVideoContent}
                                    onEdit={beginEditContent}
                                    handleProps={handleProps}
                                    isDragging={isDragging}
                                />
                            )}
                        />
                    </div>
                </div>
            </div>

            <ConfirmDeleteDialog
                open={deleting !== null}
                title="Eliminar figura"
                description="Esta acción no se puede deshacer."
                itemName={deleting?.name}
                confirming={deletingId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleting(null);
                    }
                }}
                onConfirm={() => void handleRemove()}
            />

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
                            {videoContent?.name ?? 'Video'}
                        </DialogTitle>
                    </DialogHeader>
                    <LevelVideoPreview url={videoContent?.video_url} />
                </DialogContent>
            </Dialog>
        </>
    );
}

ContentLevel.layout = (props: {
    danceType: DanceTypeCard;
    level: ContentLevel;
}) => ({
    breadcrumbs: [
        {
            title: 'Contenido',
            href: contentIndex.url(),
        },
        {
            title: props.danceType.name,
            href: contentShow.url(props.danceType.id),
        },
        {
            title: props.level.name,
            href: contentLevelShow.url({
                danceType: props.danceType.id,
                level: props.level.id,
            }),
        },
    ],
});
