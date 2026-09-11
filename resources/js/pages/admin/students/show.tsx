import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import admin from '@/routes/admin';

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
    canUpdateEmail?: boolean;
};

function toDateInputValue(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value.slice(0, 10);
}

export default function AdminStudentShow({
    student,
    canUpdateEmail = false,
}: StudentShowProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: student.name ?? '',
        email: student.email ?? '',
        dni: student.dni ?? '',
        birthday: toDateInputValue(student.birthday),
        phone: student.phone ?? '',
        address: student.address ?? '',
        city: student.city ?? '',
        state: student.state ?? '',
        zip: student.zip ?? '',
        country: student.country ?? '',
        emergency_contact_name: student.emergency_contact_name ?? '',
        emergency_contact_phone: student.emergency_contact_phone ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        form.patch(admin.students.update.url(student.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head
                title={t('admin.students.headTitleStudent', {
                    name: student.name,
                })}
            />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {student.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('admin.students.studentEditDescription')}
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.students.index.url()}>
                            {t('admin.students.studentList')}
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 className="mb-4 text-lg font-medium">
                        {t('common.generalData')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="student-name">
                                {t('common.fullName')}
                            </Label>
                            <Input
                                id="student-name"
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
                            <Label htmlFor="student-email">
                                {t('common.email')}
                            </Label>
                            <Input
                                id="student-email"
                                type="email"
                                name="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                required
                                autoComplete="email"
                                disabled={!canUpdateEmail}
                            />
                            {!canUpdateEmail ? (
                                <p className="text-xs text-muted-foreground">
                                    {t('admin.students.emailLockedHint')}
                                </p>
                            ) : null}
                            <InputError message={form.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student-dni">
                                {t('common.dni')}
                            </Label>
                            <Input
                                id="student-dni"
                                name="dni"
                                value={form.data.dni}
                                onChange={(e) =>
                                    form.setData('dni', e.target.value)
                                }
                            />
                            <InputError message={form.errors.dni} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student-phone">
                                {t('common.phone')}
                            </Label>
                            <Input
                                id="student-phone"
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
                            <Label htmlFor="student-birthday">
                                {t('common.birthday')}
                            </Label>
                            <Input
                                id="student-birthday"
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
                            <Label htmlFor="student-address">
                                {t('common.address')}
                            </Label>
                            <Input
                                id="student-address"
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
                            <Label htmlFor="student-city">
                                {t('common.city')}
                            </Label>
                            <Input
                                id="student-city"
                                name="city"
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                            />
                            <InputError message={form.errors.city} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student-state">
                                {t('common.stateProvince')}
                            </Label>
                            <Input
                                id="student-state"
                                name="state"
                                value={form.data.state}
                                onChange={(e) =>
                                    form.setData('state', e.target.value)
                                }
                            />
                            <InputError message={form.errors.state} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student-zip">
                                {t('common.zipCode')}
                            </Label>
                            <Input
                                id="student-zip"
                                name="zip"
                                value={form.data.zip}
                                onChange={(e) =>
                                    form.setData('zip', e.target.value)
                                }
                            />
                            <InputError message={form.errors.zip} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student-country">
                                {t('common.country')}
                            </Label>
                            <Input
                                id="student-country"
                                name="country"
                                value={form.data.country}
                                onChange={(e) =>
                                    form.setData('country', e.target.value)
                                }
                            />
                            <InputError message={form.errors.country} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student-emergency-name">
                                {t('admin.students.emergencyContact')}
                            </Label>
                            <Input
                                id="student-emergency-name"
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
                            <Label htmlFor="student-emergency-phone">
                                {t('admin.students.emergencyPhone')}
                            </Label>
                            <Input
                                id="student-emergency-phone"
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
                    </div>

                    <div className="mt-6 flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? t('common.saving')
                                : t('common.save')}
                        </Button>
                    </div>
                </form>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="mb-4 text-lg font-medium">
                        {t('admin.students.enrollments')}
                    </h2>
                    {student.enrollments && student.enrollments.length > 0 ? (
                        <ul className="space-y-2">
                            {student.enrollments.map((enrollment) => (
                                <li
                                    key={enrollment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-sidebar-border/60 px-3 py-2 text-sm"
                                >
                                    <span>
                                        {enrollment.course?.level?.name ??
                                            t('admin.students.courseNumber', {
                                                id: enrollment.course?.id ?? '',
                                            })}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {enrollment.status}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('admin.students.noEnrollments')}
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
            title: 'navigation.students',
            href: admin.students.index.url(),
        },
        {
            title: 'admin.breadcrumbs.student',
            href: '#',
        },
    ],
};
