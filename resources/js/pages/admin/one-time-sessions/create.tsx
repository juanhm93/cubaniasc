import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import admin from '@/routes/admin';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = {
    id: number;
    name: string;
};

type CreateOneTimeSessionProps = {
    places: Option[];
    teachers: Option[];
};

const TYPE_OPTIONS: { value: string; label: string }[] = [
    { value: 'workshop', label: 'Taller' },
    { value: 'private_class', label: 'Clase personalizada' },
    { value: 'event', label: 'Evento' },
];

export default function OneTimeSessionCreate({
    places,
    teachers,
}: CreateOneTimeSessionProps) {
    const form = useForm({
        type: 'workshop',
        name: '',
        description: '',
        price: '',
        starts_at: '',
        ends_at: '',
        capacity: '',
        place_id: '',
        user_id: '',
        notes: '',
        is_active: true as boolean,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        form.post(admin.oneTimeSessions.store.url(), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Nueva clase especial" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Nueva clase especial
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Registra talleres, clases personalizadas o eventos
                                de una sola oportunidad.
                            </p>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={admin.oneTimeSessions.index.url()}>
                                Volver
                            </Link>
                        </Button>
                    </div>

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">Datos generales</h2>

                            <div className="grid gap-2">
                                <Label htmlFor="type">Tipo</Label>
                                <select
                                    id="type"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.type}
                                    onChange={(event) =>
                                        form.setData('type', event.target.value)
                                    }
                                    required
                                >
                                    {TYPE_OPTIONS.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripción</Label>
                                <textarea
                                    id="description"
                                    className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.description} />
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Inicio</Label>
                                    <Input
                                        id="starts_at"
                                        type="datetime-local"
                                        value={form.data.starts_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'starts_at',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">Fin</Label>
                                    <Input
                                        id="ends_at"
                                        type="datetime-local"
                                        value={form.data.ends_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'ends_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.ends_at} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="price">Precio</Label>
                                    <Input
                                        id="price"
                                        type="number"
                                        inputMode="decimal"
                                        min="0"
                                        step="0.01"
                                        value={form.data.price}
                                        onChange={(event) =>
                                            form.setData('price', event.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.price} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="capacity">Capacidad</Label>
                                    <Input
                                        id="capacity"
                                        type="number"
                                        min="1"
                                        step="1"
                                        value={form.data.capacity}
                                        onChange={(event) =>
                                            form.setData(
                                                'capacity',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.capacity} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="place_id">Lugar</Label>
                                    <select
                                        id="place_id"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.place_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'place_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Sin lugar específico
                                        </option>
                                        {places.map((place) => (
                                            <option
                                                key={place.id}
                                                value={String(place.id)}
                                            >
                                                {place.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.place_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="user_id">Profesor</Label>
                                    <select
                                        id="user_id"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.user_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'user_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Sin profesor asignado
                                        </option>
                                        {teachers.map((teacher) => (
                                            <option
                                                key={teacher.id}
                                                value={String(teacher.id)}
                                            >
                                                {teacher.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.user_id} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Observaciones</Label>
                                <textarea
                                    id="notes"
                                    className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData('notes', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.notes} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Estado</Label>
                                <select
                                    id="is_active"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.is_active ? '1' : '0'}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_active',
                                            event.target.value === '1',
                                        )
                                    }
                                >
                                    <option value="1">Activa</option>
                                    <option value="0">Inactiva</option>
                                </select>
                                <InputError message={form.errors.is_active} />
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Guardando...'
                                    : 'Crear clase especial'}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={admin.oneTimeSessions.index.url()}>
                                    Cancelar
                                </Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

OneTimeSessionCreate.layout = {
    breadcrumbs: [
        {
            title: 'Clases especiales',
            href: admin.oneTimeSessions.index.url(),
        },
        {
            title: 'Nueva',
            href: admin.oneTimeSessions.create.url(),
        },
    ],
};
