import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { Eye, Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import LevelVideoPreview from '@/components/content/level-video-preview';
import VisualFiguresCatalog from '@/components/content/visual-figures-catalog';
import InputError from '@/components/input-error';
import LevelItem from '@/components/items/level-item';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { mapValidationErrors } from '@/lib/map-validation-errors';
import { cn } from '@/lib/utils';
import { index as contentIndex } from '@/routes/content';
import { createLevel, deleteLevel } from '@/services/levelService';
import { normalizeDanceTypeDetail, normalizeLevel } from '@/types/content';
import type {
    ContentLevel,
    DanceTypeDetail,
    FigureItem,
} from '@/types/content';

type ViewMode = 'editable' | 'visual';

const textareaClassName = cn(
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'disabled:cursor-not-allowed disabled:opacity-50',
    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
);

export default function ContentShow({
    danceType,
}: {
    danceType: DanceTypeDetail;
}) {
    const [detail, setDetail] = useState<DanceTypeDetail>(() =>
        normalizeDanceTypeDetail(danceType),
    );
    const [view, setView] = useState<ViewMode>('editable');
    const [addOpen, setAddOpen] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleting, setDeleting] = useState<ContentLevel | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [videoFigure, setVideoFigure] = useState<FigureItem | null>(null);

    const figureCount = useMemo(
        () =>
            detail.levels.reduce(
                (total, level) => total + level.level_contents.length,
                0,
            ),
        [detail.levels],
    );

    async function handleCreateLevel(e: React.FormEvent): Promise<void> {
        e.preventDefault();
        setFormErrors({});
        setSubmitting(true);

        try {
            const created = normalizeLevel(
                await createLevel({
                    name,
                    description: description.trim() === '' ? null : description,
                    dance_type_id: detail.id,
                }),
            );
            setDetail((prev) => ({
                ...prev,
                levels: [...prev.levels, created],
                levels_count: prev.levels_count + 1,
            }));
            toast.success('Nivel creado');
            setAddOpen(false);
            setName('');
            setDescription('');
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const mapped = mapValidationErrors(error.response.data);

                if (mapped) {
                    setFormErrors(mapped);
                } else {
                    toast.error('No se pudo crear el nivel');
                }
            } else {
                toast.error('No se pudo crear el nivel');
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleDeleteLevel(): Promise<void> {
        if (!deleting) {
            return;
        }

        setDeletingId(deleting.id);

        try {
            await deleteLevel(deleting.id);
            setDetail((prev) => ({
                ...prev,
                levels: prev.levels.filter((level) => level.id !== deleting.id),
                levels_count: Math.max(0, prev.levels_count - 1),
                figures_count: Math.max(
                    0,
                    prev.figures_count - deleting.level_contents.length,
                ),
            }));
            toast.success('Nivel eliminado');
            setDeleting(null);
        } catch (error) {
            const message =
                axios.isAxiosError(error) &&
                typeof error.response?.data?.message === 'string'
                    ? error.response.data.message
                    : 'No se pudo eliminar el nivel';
            toast.error(message);
        } finally {
            setDeletingId(null);
        }
    }

    return (
        <>
            <Head title={detail.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={contentIndex.url()}
                                className="underline-offset-4 hover:underline"
                            >
                                Contenido
                            </Link>
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            {detail.name}
                        </h1>
                        {detail.description ? (
                            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                {detail.description}
                            </p>
                        ) : null}
                        <p className="mt-2 text-sm text-muted-foreground">
                            {detail.levels.length} nivel
                            {detail.levels.length === 1 ? '' : 'es'} ·{' '}
                            {figureCount} figura
                            {figureCount === 1 ? '' : 's'}
                        </p>
                    </div>
                    <ToggleGroup
                        type="single"
                        value={view}
                        onValueChange={(value) => {
                            if (value === 'editable' || value === 'visual') {
                                setView(value);
                            }
                        }}
                        variant="outline"
                        className="bg-background"
                    >
                        <ToggleGroupItem value="editable" aria-label="Editar">
                            <Pencil className="size-4" />
                            Editar
                        </ToggleGroupItem>
                        <ToggleGroupItem value="visual" aria-label="Visual">
                            <Eye className="size-4" />
                            Visual
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                {view === 'visual' ? (
                    <VisualFiguresCatalog
                        danceTypeName={detail.name}
                        levels={detail.levels}
                        onSelectFigure={setVideoFigure}
                    />
                ) : (
                    <div className="relative flex min-h-[100vh] flex-1 flex-col gap-2 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
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
                                        Agregar nivel
                                    </span>
                                </button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={handleCreateLevel}>
                                    <DialogHeader>
                                        <DialogTitle>Nuevo nivel</DialogTitle>
                                        <DialogDescription>
                                            Agrega un nivel a {detail.name}. El
                                            orden se asigna automáticamente.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="level-name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="level-name"
                                                name="name"
                                                value={name}
                                                onChange={(event) =>
                                                    setName(event.target.value)
                                                }
                                                placeholder="Ej. Básico 1"
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
                                                Descripción (opcional)
                                            </Label>
                                            <textarea
                                                id="level-description"
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

                        {detail.levels.map((level) => (
                            <LevelItem
                                key={level.id}
                                danceTypeId={detail.id}
                                level={{
                                    id: level.id,
                                    name: level.name,
                                    description: level.description,
                                    figuresCount: level.level_contents.length,
                                }}
                                deletingId={deletingId}
                                onDelete={() => setDeleting(level)}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleting(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar nivel</DialogTitle>
                        <DialogDescription>
                            Se eliminarán también sus figuras. No se puede
                            eliminar si algún curso lo está usando.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="text-sm">
                        ¿Eliminar <strong>{deleting?.name}</strong>?
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deletingId !== null}
                            onClick={() => void handleDeleteLevel()}
                        >
                            {deletingId !== null ? 'Eliminando…' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={videoFigure !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setVideoFigure(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {videoFigure?.name ?? 'Video'}
                        </DialogTitle>
                    </DialogHeader>
                    <LevelVideoPreview url={videoFigure?.video_url} />
                </DialogContent>
            </Dialog>
        </>
    );
}

ContentShow.layout = {
    breadcrumbs: [
        {
            title: 'Contenido',
            href: contentIndex.url(),
        },
    ],
};
