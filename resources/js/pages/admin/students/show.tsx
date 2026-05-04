import { Head, Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';

type LevelRef = {
    id: number;
    name: string;
};

type CourseRef = {
    id: number;
    level?: LevelRef | null;
};

type EnrollmentRow = {
    id: number;
    status: string;
    course?: CourseRef | null;
};

type StudentShowProps = {
    student: {
        id: number;
        name: string;
        dni: string | null;
        email: string;
        birthday: string | null;
        phone: string | null;
        address: string | null;
        city: string | null;
        state: string | null;
        zip: string | null;
        country: string | null;
        emergency_contact_name: string | null;
        emergency_contact_phone: string | null;
        enrollments?: EnrollmentRow[];
    };
};

function Field({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm">{value && value !== '' ? value : '—'}</dd>
        </div>
    );
}

export default function AdminStudentShow({ student }: StudentShowProps) {
    return (
        <>
            <Head title={`Alumno: ${student.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{student.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            Ficha del alumno (solo lectura)
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.students.index.url()}>
                            Lista de alumnos
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="mb-4 text-lg font-medium">Datos generales</h2>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Field label="Correo" value={student.email} />
                        <Field label="DNI" value={student.dni} />
                        <Field label="Teléfono" value={student.phone} />
                        <Field
                            label="Fecha de nacimiento"
                            value={
                                student.birthday
                                    ? new Date(
                                          student.birthday,
                                      ).toLocaleDateString('es')
                                    : null
                            }
                        />
                        <Field label="Dirección" value={student.address} />
                        <Field label="Ciudad" value={student.city} />
                        <Field label="Estado / provincia" value={student.state} />
                        <Field label="Código postal" value={student.zip} />
                        <Field label="País" value={student.country} />
                        <Field
                            label="Contacto de emergencia"
                            value={student.emergency_contact_name}
                        />
                        <Field
                            label="Teléfono de emergencia"
                            value={student.emergency_contact_phone}
                        />
                    </dl>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="mb-4 text-lg font-medium">Inscripciones</h2>
                    {student.enrollments && student.enrollments.length > 0 ? (
                        <ul className="space-y-2">
                            {student.enrollments.map((enrollment) => (
                                <li
                                    key={enrollment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-sidebar-border/60 px-3 py-2 text-sm"
                                >
                                    <span>
                                        {enrollment.course?.level?.name ??
                                            `Curso #${enrollment.course?.id ?? ''}`}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {enrollment.status}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Sin inscripciones cargadas.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

AdminStudentShow.layout = {
    breadcrumbs: [
        {
            title: 'Pagos',
            href: admin.payments.index.url(),
        },
        {
            title: 'Alumno',
            href: '#',
        },
    ],
};
