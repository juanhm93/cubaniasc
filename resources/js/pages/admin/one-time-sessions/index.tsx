import { Head, Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type SessionRow = {
    id: number;
    name: string;
    type: string;
    starts_at: string | null;
    ends_at: string | null;
    price: string | null;
    is_active: boolean;
    place_name: string | null;
    teacher_name: string | null;
    attendees_count: number;
};

type OneTimeSessionsIndexProps = {
    sessions: SessionRow[];
};

const sessionTypeLabels: Record<string, string> = {
    workshop: 'Taller',
    private_class: 'Clase personalizada',
    event: 'Evento',
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return new Intl.DateTimeFormat('es-VE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export default function OneTimeSessionsIndex({
    sessions,
}: OneTimeSessionsIndexProps) {
    return (
        <>
            <Head title="Clases especiales" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Clases especiales
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Talleres, clases personalizadas y eventos de una sola
                            oportunidad, ordenados del m&aacute;s reciente al m&aacute;s
                            viejo.
                        </p>
                    </div>
                    <div className="flex justify-end">
                        <Button asChild>
                            <Link href={admin.oneTimeSessions.create.url()}>
                                Crear clase especial
                            </Link>
                        </Button>
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[840px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Nombre
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Tipo
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Inicio
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Fin
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Lugar
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Profesor
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Participantes
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Precio
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Estado
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {sessions.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            No hay clases especiales registradas.
                                        </td>
                                    </tr>
                                ) : (
                                    sessions.map((session) => (
                                        <tr
                                            key={session.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle font-medium">
                                                {session.name}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {sessionTypeLabels[session.type] ??
                                                    session.type}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {formatDateTime(session.starts_at)}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {formatDateTime(session.ends_at)}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.place_name ?? '—'}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.teacher_name ?? '—'}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.attendees_count}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {session.price ?? '—'}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.is_active ? (
                                                    <Badge>Activa</Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Inactiva
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

OneTimeSessionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Clases especiales',
            href: admin.oneTimeSessions.index.url(),
        },
    ],
};
