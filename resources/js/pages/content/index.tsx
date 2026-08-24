import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ArrowRight, Music2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
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
import { mapValidationErrors } from '@/lib/map-validation-errors';
import { cn } from '@/lib/utils';
import { index as contentIndex, show as contentShow } from '@/routes/content';
import {
    createDanceType,
    deleteDanceType,
    updateDanceType,
} from '@/services/levelService';
import { normalizeDanceTypeCard } from '@/types/content';
import type { DanceTypeCard } from '@/types/content';

const textareaClassName = cn(
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'disabled:cursor-not-allowed disabled:opacity-50',
    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
);

export default function ContentIndex({
    danceTypes,
}: {
    danceTypes: DanceTypeCard[];
}) {
    const [items, setItems] = useState<DanceTypeCard[]>(() =>
        danceTypes.map(normalizeDanceTypeCard),
    );
    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<DanceTypeCard | null>(null);
    const [deleting, setDeleting] = useState<DanceTypeCard | null>(null);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    function resetForm(): void {
        setName('');
        setDescription('');
        setFormErrors({});
    }

    function beginEdit(danceType: DanceTypeCard): void {
        setName(danceType.name);
        setDescription(danceType.description ?? '');
        setFormErrors({});
        setEditing(danceType);
    }

    async function handleCreate(e: React.FormEvent): Promise<void> {
        e.preventDefault();
        setFormErrors({});
        setSubmitting(true);

        try {
            const created = normalizeDanceTypeCard(
                await createDanceType({
                    name,
                    description: description.trim() === '' ? null : description,
                }),
            );
            setItems((prev) => [...prev, created]);
            toast.success('Estilo creado');
            setAddOpen(false);
            resetForm();
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const mapped = mapValidationErrors(error.response.data);

                if (mapped) {
                    setFormErrors(mapped);
                } else {
                    toast.error('No se pudo crear el estilo');
                }
            } else {
                toast.error('No se pudo crear el estilo');
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleUpdate(e: React.FormEvent): Promise<void> {
        e.preventDefault();

        if (!editing) {
            return;
        }

        setFormErrors({});
        setSubmitting(true);

        try {
            const updated = normalizeDanceTypeCard(
                await updateDanceType(editing.id, {
                    name,
                    description: description.trim() === '' ? null : description,
                }),
            );
            setItems((prev) =>
                prev.map((item) => (item.id === editing.id ? updated : item)),
            );
            toast.success('Estilo actualizado');
            setEditing(null);
            resetForm();
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const mapped = mapValidationErrors(error.response.data);

                if (mapped) {
                    setFormErrors(mapped);
                } else {
                    toast.error('No se pudo guardar el estilo');
                }
            } else {
                toast.error('No se pudo guardar el estilo');
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleDelete(): Promise<void> {
        if (!deleting) {
            return;
        }

        setDeletingId(deleting.id);

        try {
            await deleteDanceType(deleting.id);
            setItems((prev) => prev.filter((item) => item.id !== deleting.id));
            toast.success('Estilo eliminado');
            setDeleting(null);
        } catch (error) {
            const message =
                axios.isAxiosError(error) &&
                typeof error.response?.data?.message === 'string'
                    ? error.response.data.message
                    : 'No se pudo eliminar el estilo';
            toast.error(message);
        } finally {
            setDeletingId(null);
        }
    }

    return (
        <>
            <Head title="Contenido" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {items.length} estilo{items.length === 1 ? '' : 's'} de
                        baile
                    </p>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Base de conocimiento de ritmos, niveles y figuras. Se
                        puede adjuntar a los cursos para marcar lo que lleva
                        cada clase.
                    </p>
                </div>
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-2 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <Dialog
                        open={addOpen}
                        onOpenChange={(open) => {
                            setAddOpen(open);

                            if (open) {
                                resetForm();
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
                                    Agregar estilo de baile
                                </span>
                            </button>
                        </DialogTrigger>
                        <DialogContent>
                            <form onSubmit={handleCreate}>
                                <DialogHeader>
                                    <DialogTitle>
                                        Nuevo estilo de baile
                                    </DialogTitle>
                                    <DialogDescription>
                                        Crea un ritmo. Luego podrás agregar
                                        niveles y figuras.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4 py-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="dance-type-name">
                                            Nombre
                                        </Label>
                                        <Input
                                            id="dance-type-name"
                                            name="name"
                                            value={name}
                                            onChange={(event) =>
                                                setName(event.target.value)
                                            }
                                            placeholder="Ej. Salsa Casino"
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
                                        <InputError message={formErrors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="dance-type-description">
                                            Descripción (opcional)
                                        </Label>
                                        <textarea
                                            id="dance-type-description"
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
                                    <Button type="submit" disabled={submitting}>
                                        {submitting ? 'Guardando…' : 'Crear'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>

                    {items.map((danceType) => (
                        <div
                            key={danceType.id}
                            className="flex items-center gap-3 rounded-[4px] border border-sidebar-border/70 bg-card px-3 py-3 shadow-sm dark:border-sidebar-border"
                        >
                            <Music2
                                className="size-5 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                            <div className="min-w-0 flex-1">
                                <h2 className="truncate font-semibold">
                                    {danceType.name}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {danceType.levels_count} nivel
                                    {danceType.levels_count === 1
                                        ? ''
                                        : 'es'} · {danceType.figures_count}{' '}
                                    figura
                                    {danceType.figures_count === 1 ? '' : 's'}
                                </p>
                                {danceType.description ? (
                                    <p className="line-clamp-2 text-sm text-muted-foreground">
                                        {danceType.description}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 text-muted-foreground"
                                    aria-label={`Editar ${danceType.name}`}
                                    onClick={() => beginEdit(danceType)}
                                >
                                    <Pencil className="size-5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 text-muted-foreground"
                                    aria-label={`Abrir ${danceType.name}`}
                                    onClick={() =>
                                        router.visit(
                                            contentShow.url(danceType.id),
                                        )
                                    }
                                >
                                    <ArrowRight className="size-5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 text-muted-foreground hover:text-destructive"
                                    aria-label={`Eliminar ${danceType.name}`}
                                    disabled={deletingId === danceType.id}
                                    onClick={() => setDeleting(danceType)}
                                >
                                    <Trash2 className="size-5" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
            >
                <DialogContent>
                    <form onSubmit={handleUpdate}>
                        <DialogHeader>
                            <DialogTitle>Editar estilo</DialogTitle>
                            <DialogDescription>
                                Actualiza el nombre o la descripción del ritmo.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-2">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-dance-type-name">
                                    Nombre
                                </Label>
                                <Input
                                    id="edit-dance-type-name"
                                    name="name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    required
                                    autoComplete="off"
                                    disabled={submitting}
                                    maxLength={120}
                                    aria-invalid={
                                        formErrors.name ? true : undefined
                                    }
                                />
                                <InputError message={formErrors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-dance-type-description">
                                    Descripción (opcional)
                                </Label>
                                <textarea
                                    id="edit-dance-type-description"
                                    name="description"
                                    value={description}
                                    onChange={(event) =>
                                        setDescription(event.target.value)
                                    }
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
                                <InputError message={formErrors.description} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={submitting}
                                onClick={() => setEditing(null)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Guardando…' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

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
                        <DialogTitle>Eliminar estilo</DialogTitle>
                        <DialogDescription>
                            Se eliminarán también sus niveles y figuras. No se
                            puede eliminar si algún curso lo está usando.
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
                            onClick={() => void handleDelete()}
                        >
                            {deletingId !== null ? 'Eliminando…' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ContentIndex.layout = {
    breadcrumbs: [
        {
            title: 'Contenido',
            href: contentIndex.url(),
        },
    ],
};
