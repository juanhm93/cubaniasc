import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import admin from '@/routes/admin';
import enrollRoutes from '@/routes/admin/pre-registrations/enroll';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CourseOption = {
    id: number;
    label: string;
};

type PreRegistrationRef = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
};

type StudentDraft = {
    name: string;
    email: string;
    phone: string;
    dni: string;
    birthday: string;
    address: string;
    city: string;
    state: string;
    zip: string;
    country: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    course_id: number | null;
};

type EnrollPageProps = {
    preRegistration: PreRegistrationRef;
    courses: CourseOption[];
    studentDraft: StudentDraft;
};

export default function AdminPreRegistrationEnroll({
    preRegistration,
    courses,
    studentDraft,
}: EnrollPageProps) {
    const form = useForm({
        name: studentDraft.name,
        email: studentDraft.email,
        phone: studentDraft.phone,
        dni: studentDraft.dni,
        birthday: studentDraft.birthday,
        address: studentDraft.address,
        city: studentDraft.city,
        state: studentDraft.state,
        zip: studentDraft.zip,
        country: studentDraft.country,
        emergency_contact_name: studentDraft.emergency_contact_name,
        emergency_contact_phone: studentDraft.emergency_contact_phone,
        course_id: studentDraft.course_id != null ? String(studentDraft.course_id) : '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        form.post(enrollRoutes.store.url({ preRegistration: preRegistration.id }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Inscribir desde preinscripción" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Convertir preinscripción en alumno
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Completa los datos adicionales del alumno. Los campos
                            vacíos puedes rellenarlos ahora o después desde la
                            ficha.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.payments.index.url()}>Volver a pagos</Link>
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="mb-2 text-sm font-medium text-muted-foreground">
                        Preinscripción (referencia)
                    </h2>
                    <dl className="grid gap-2 text-sm sm:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Nombre
                            </dt>
                            <dd>{preRegistration.name}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Correo
                            </dt>
                            <dd>{preRegistration.email}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Teléfono
                            </dt>
                            <dd>{preRegistration.phone ?? '—'}</dd>
                        </div>
                    </dl>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 className="mb-4 text-lg font-medium">Datos del alumno</h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="stu-name">Nombre completo</Label>
                            <Input
                                id="stu-name"
                                name="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                                autoComplete="name"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-email">Correo</Label>
                            <Input
                                id="stu-email"
                                type="email"
                                name="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                required
                                autoComplete="email"
                            />
                            <InputError message={form.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-phone">Teléfono</Label>
                            <Input
                                id="stu-phone"
                                name="phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                autoComplete="tel"
                            />
                            <InputError message={form.errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-dni">DNI / documento</Label>
                            <Input
                                id="stu-dni"
                                name="dni"
                                value={form.data.dni}
                                onChange={(e) =>
                                    form.setData('dni', e.target.value)
                                }
                            />
                            <InputError message={form.errors.dni} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-birthday">
                                Fecha de nacimiento
                            </Label>
                            <Input
                                id="stu-birthday"
                                type="date"
                                name="birthday"
                                value={form.data.birthday}
                                onChange={(e) =>
                                    form.setData('birthday', e.target.value)
                                }
                            />
                            <InputError message={form.errors.birthday} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="stu-address">Dirección</Label>
                            <Input
                                id="stu-address"
                                name="address"
                                value={form.data.address}
                                onChange={(e) =>
                                    form.setData('address', e.target.value)
                                }
                                autoComplete="street-address"
                            />
                            <InputError message={form.errors.address} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-city">Ciudad</Label>
                            <Input
                                id="stu-city"
                                name="city"
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                            />
                            <InputError message={form.errors.city} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-state">Estado / provincia</Label>
                            <Input
                                id="stu-state"
                                name="state"
                                value={form.data.state}
                                onChange={(e) =>
                                    form.setData('state', e.target.value)
                                }
                            />
                            <InputError message={form.errors.state} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-zip">Código postal</Label>
                            <Input
                                id="stu-zip"
                                name="zip"
                                value={form.data.zip}
                                onChange={(e) =>
                                    form.setData('zip', e.target.value)
                                }
                            />
                            <InputError message={form.errors.zip} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-country">País</Label>
                            <Input
                                id="stu-country"
                                name="country"
                                value={form.data.country}
                                onChange={(e) =>
                                    form.setData('country', e.target.value)
                                }
                            />
                            <InputError message={form.errors.country} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-emergency-name">
                                Contacto emergencia (nombre)
                            </Label>
                            <Input
                                id="stu-emergency-name"
                                name="emergency_contact_name"
                                value={form.data.emergency_contact_name}
                                onChange={(e) =>
                                    form.setData(
                                        'emergency_contact_name',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.emergency_contact_name}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stu-emergency-phone">
                                Contacto emergencia (teléfono)
                            </Label>
                            <Input
                                id="stu-emergency-phone"
                                name="emergency_contact_phone"
                                value={form.data.emergency_contact_phone}
                                onChange={(e) =>
                                    form.setData(
                                        'emergency_contact_phone',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.emergency_contact_phone}
                            />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="stu-course">
                                Inscribir en curso (opcional)
                            </Label>
                            <select
                                id="stu-course"
                                name="course_id"
                                className="h-9 w-full max-w-md rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.course_id}
                                onChange={(e) =>
                                    form.setData('course_id', e.target.value)
                                }
                            >
                                <option value="">
                                    — Sin curso por ahora —
                                </option>
                                {courses.map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.course_id} />
                        </div>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Guardando…'
                                : 'Crear alumno'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={admin.payments.index.url()}>
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AdminPreRegistrationEnroll.layout = {
    breadcrumbs: [
        {
            title: 'Pagos',
            href: admin.payments.index.url(),
        },
        {
            title: 'Inscribir',
            href: '#',
        },
    ],
};
