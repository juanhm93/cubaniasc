import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import admin from '@/routes/admin';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type CourseOption = {
    id: number;
    label: string;
};

type StudentOption = {
    id: number;
    label: string;
};

type EnrollPageProps = {
    courses: CourseOption[];
    students: StudentOption[];
};

export default function AdminStudentsEnroll({
    courses,
    students,
}: EnrollPageProps) {
    const form = useForm({
        course_id: '',
        student_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        form.post(admin.students.enroll.store.url(), {
            preserveScroll: true,
        });
    };

    const coursesEmpty = courses.length === 0;
    const studentsEmpty = students.length === 0;

    return (
        <>
            <Head title="Inscribir alumnos" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mx-auto flex w-full max-w-lg flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Inscribir alumnos
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Asocia un alumno existente a un curso de tu
                                academia.
                            </p>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={admin.students.index.url()}>
                                Volver al listado
                            </Link>
                        </Button>
                    </div>

                    {(coursesEmpty || studentsEmpty) && (
                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
                            {coursesEmpty
                                ? 'No hay cursos en tu academia.'
                                : null}{' '}
                            {studentsEmpty
                                ? 'No hay alumnos registrados aún.'
                                : null}
                        </p>
                    )}

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div className="grid gap-2">
                                <Label htmlFor="course_id">Curso</Label>
                                <select
                                    id="course_id"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.course_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'course_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    disabled={coursesEmpty}
                                >
                                    <option value="" disabled>
                                        Selecciona…
                                    </option>
                                    {courses.map((c) => (
                                        <option key={c.id} value={String(c.id)}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.course_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="student_id">Alumno</Label>
                                <select
                                    id="student_id"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.student_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'student_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    disabled={studentsEmpty}
                                >
                                    <option value="" disabled>
                                        Selecciona…
                                    </option>
                                    {students.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.student_id} />
                            </div>

                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    coursesEmpty ||
                                    studentsEmpty
                                }
                            >
                                Inscribir en el curso
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

AdminStudentsEnroll.layout = {
    breadcrumbs: [
        {
            title: 'Alumnos',
            href: admin.students.index.url(),
        },
        {
            title: 'Inscribir',
            href: admin.students.enroll.url(),
        },
    ],
};
