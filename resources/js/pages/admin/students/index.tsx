import { Head, Link, router } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useEffect, useState } from 'react';
import admin from '@/routes/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type EnrollmentRow = {
    id: number;
    student_id: number;
    student_name: string;
    student_email: string;
    course_id: number;
    course_label: string;
    level_name: string;
    status: string;
    status_label: string;
};

type Option = { id: number; label?: string; name?: string };

type FiltersState = {
    course_id?: string | number | null;
    level_id?: string | number | null;
    status?: string | null;
    search?: string | null;
};

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEnrollments = {
    data: EnrollmentRow[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};

type StudentsIndexProps = {
    enrollments: PaginatedEnrollments;
    filters: FiltersState;
    courseOptions: Option[];
    levelOptions: Option[];
};

function paginationLabel(raw: string): string {
    return raw
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/Previous/i, 'Anterior')
        .replace(/Next/i, 'Siguiente')
        .replace(/<[^>]*>/g, '');
}

function queryFromFilters(f: FiltersState): Record<string, string> {
    const q: Record<string, string> = {};

    if (f.course_id != null && String(f.course_id) !== '') {
        q.course_id = String(f.course_id);
    }

    if (f.level_id != null && String(f.level_id) !== '') {
        q.level_id = String(f.level_id);
    }

    if (f.status != null && String(f.status) !== '') {
        q.status = String(f.status);
    }

    if (f.search != null && f.search.trim() !== '') {
        q.search = f.search.trim();
    }

    return q;
}

export default function AdminStudentsIndex({
    enrollments,
    filters,
    courseOptions,
    levelOptions,
}: StudentsIndexProps) {
    const [searchDraft, setSearchDraft] = useState(filters.search ?? '');

    useEffect(() => {
        setSearchDraft(filters.search ?? '');
    }, [filters.search]);

    function visitFilters(next: FiltersState): void {
        router.get(admin.students.index.url(), queryFromFilters(next), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        visitFilters({
            ...filters,
            search: searchDraft.trim() || undefined,
        });
    };

    const clearFilters = (): void => {
        setSearchDraft('');
        router.get(admin.students.index.url(), {}, { preserveState: true });
    };

    const { total, from, to, last_page: lastPage } = enrollments;
    const links = enrollments.links ?? [];

    return (
        <>
            <Head title="Alumnos" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">Alumnos</h1>
                            <p className="text-sm text-muted-foreground">
                                Matrículas por curso: filtra por curso, nivel,
                                estado o nombre.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={admin.students.enroll.url()}>
                                Inscribir alumnos
                            </Link>
                        </Button>
                    </div>

                    <form
                        onSubmit={submitSearch}
                        className="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-course">Curso</Label>
                            <select
                                id="filter-course"
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={
                                    filters.course_id != null
                                        ? String(filters.course_id)
                                        : ''
                                }
                                onChange={(e) => {
                                    visitFilters({
                                        ...filters,
                                        course_id: e.target.value || undefined,
                                    });
                                }}
                            >
                                <option value="">Todos</option>
                                {courseOptions.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.label ?? `Curso #${c.id}`}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-level">Nivel</Label>
                            <select
                                id="filter-level"
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={
                                    filters.level_id != null
                                        ? String(filters.level_id)
                                        : ''
                                }
                                onChange={(e) => {
                                    visitFilters({
                                        ...filters,
                                        level_id: e.target.value || undefined,
                                    });
                                }}
                            >
                                <option value="">Todos</option>
                                {levelOptions.map((l) => (
                                    <option key={l.id} value={l.id}>
                                        {l.name ?? `Nivel #${l.id}`}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-status">Estado</Label>
                            <select
                                id="filter-status"
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={filters.status ?? ''}
                                onChange={(e) => {
                                    visitFilters({
                                        ...filters,
                                        status: e.target.value || undefined,
                                    });
                                }}
                            >
                                <option value="">Todos</option>
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div className="grid gap-1.5 md:col-span-2 lg:col-span-1 xl:col-span-2">
                            <Label htmlFor="filter-search">Nombre o correo</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="filter-search"
                                    type="search"
                                    value={searchDraft}
                                    onChange={(e) =>
                                        setSearchDraft(e.target.value)
                                    }
                                    placeholder="Buscar…"
                                    className="h-9"
                                />
                                <Button type="submit" size="sm" className="h-9">
                                    Buscar
                                </Button>
                            </div>
                        </div>
                        <div className="flex items-end gap-2 md:col-span-2 lg:col-span-2 xl:col-span-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-9"
                                onClick={clearFilters}
                            >
                                Limpiar filtros
                            </Button>
                        </div>
                    </form>

                    <p className="text-xs text-muted-foreground">
                        {total === 0
                            ? 'Sin resultados'
                            : `Mostrando ${from ?? 0}–${to ?? 0} de ${total}`}
                    </p>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[760px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Alumno
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Correo
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Curso
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Nivel
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        Estado
                                    </th>
                                    <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {enrollments.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            No hay matrículas con estos filtros.
                                        </td>
                                    </tr>
                                ) : (
                                    enrollments.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle font-medium">
                                                {row.student_name || '—'}
                                            </td>
                                            <td className="max-w-[220px] truncate px-3 py-3 align-middle text-muted-foreground">
                                                {row.student_email || '—'}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {row.course_label}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {row.level_name || '—'}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {row.status === 'active' ? (
                                                    <Badge>
                                                        {row.status_label}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        {row.status_label}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right align-middle">
                                                <Link
                                                    href={admin.students.show.url(
                                                        row.student_id,
                                                    )}
                                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                                >
                                                    Ver alumno
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {lastPage > 1 ? (
                        <nav
                            className="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 pt-4"
                            aria-label="Paginación"
                        >
                            {links.map((link, i) => {
                                const label = paginationLabel(link.label);

                                if (link.url === null) {
                                    return (
                                        <span
                                            key={i}
                                            className={cn(
                                                'inline-flex min-h-9 min-w-9 items-center justify-center px-2 text-sm opacity-40',
                                                link.active &&
                                                    'rounded-md border border-sidebar-border bg-muted font-medium opacity-100',
                                            )}
                                        >
                                            {label}
                                        </span>
                                    );
                                }

                                return (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveState
                                        preserveScroll
                                        className={cn(
                                            'inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-transparent px-2 text-sm',
                                            link.active
                                                ? 'border-sidebar-border bg-muted font-medium'
                                                : 'text-muted-foreground hover:bg-muted/60',
                                        )}
                                    >
                                        {label}
                                    </Link>
                                );
                            })}
                        </nav>
                    ) : null}
                </div>
            </div>
        </>
    );
}

AdminStudentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Alumnos',
            href: admin.students.index.url(),
        },
    ],
};
