import { Head, Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/i18n/use-translation';

type CourseRow = {
    id: number;
    is_active: boolean;
    price: string;
    level_name: string;
    place_name: string;
    teacher_name: string;
    schedule_summary: string;
};

type CoursesIndexProps = {
    courses: CourseRow[];
};

export default function AdminCoursesIndex({ courses }: CoursesIndexProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.courses.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {t('admin.courses.title')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.courses.description')}
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={admin.courses.create.url()}>
                                {t('admin.courses.createCourse')}
                            </Link>
                        </Button>
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[720px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.courses.currentLevel')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.place')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.teacher')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.schedule')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.price')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.status')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                        {t('common.action')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {courses.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            {t('admin.courses.noCourses')}
                                        </td>
                                    </tr>
                                ) : (
                                    courses.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle font-medium">
                                                {c.level_name ||
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {c.place_name ||
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {c.teacher_name ||
                                                    t('common.emDash')}
                                            </td>
                                            <td className="max-w-[220px] px-3 py-3 align-middle text-xs leading-snug text-muted-foreground">
                                                {c.schedule_summary ||
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {c.price}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {c.is_active ? (
                                                    <Badge>
                                                        {t('common.active')}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        {t('common.inactive')}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right align-middle">
                                                <Link
                                                    href={admin.courses.show.url(
                                                        c.id,
                                                    )}
                                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                                >
                                                    {t('admin.courses.viewCourse')}
                                                </Link>
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

AdminCoursesIndex.layout = {
    breadcrumbs: [
        {
            title: 'navigation.courses',
            href: admin.courses.index.url(),
        },
    ],
};
