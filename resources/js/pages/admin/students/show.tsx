import { Head, Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/i18n/use-translation';

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
    emptyLabel,
}: {
    label: string;
    value: string | null | undefined;
    emptyLabel: string;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm">
                {value && value !== '' ? value : emptyLabel}
            </dd>
        </div>
    );
}

export default function AdminStudentShow({ student }: StudentShowProps) {
    const { t } = useTranslation();

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
                            {t('admin.students.studentReadOnly')}
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.students.index.url()}>
                            {t('admin.students.studentList')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="mb-4 text-lg font-medium">
                        {t('common.generalData')}
                    </h2>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={t('common.email')}
                            value={student.email}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.dni')}
                            value={student.dni}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.phone')}
                            value={student.phone}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.birthday')}
                            value={
                                student.birthday
                                    ? new Date(
                                          student.birthday,
                                      ).toLocaleDateString('es')
                                    : null
                            }
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.address')}
                            value={student.address}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.city')}
                            value={student.city}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.stateProvince')}
                            value={student.state}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.zipCode')}
                            value={student.zip}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('common.country')}
                            value={student.country}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('admin.students.emergencyContact')}
                            value={student.emergency_contact_name}
                            emptyLabel={t('common.emDash')}
                        />
                        <Field
                            label={t('admin.students.emergencyPhone')}
                            value={student.emergency_contact_phone}
                            emptyLabel={t('common.emDash')}
                        />
                    </dl>
                </div>

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
                                                id:
                                                    enrollment.course?.id ??
                                                    '',
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
            title: 'navigation.payments',
            href: admin.payments.index.url(),
        },
        {
            title: 'admin.breadcrumbs.student',
            href: '#',
        },
    ],
};
