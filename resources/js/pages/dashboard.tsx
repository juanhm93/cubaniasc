import { Head, Link } from '@inertiajs/react';
import { BookOpen, UserCog, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { useTranslation } from '@/i18n/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';

type DashboardProps = {
    stats: {
        activeStudents: number;
        activeCourses: number;
    };
    staffExample: {
        professors: number;
        administrativeStaff: number;
        administrators: number;
    };
    canManageCourses: boolean;
};

export default function Dashboard({
    stats,
    staffExample,
    canManageCourses,
}: DashboardProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('dashboard.headTitle')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="text-base font-medium">
                                    {t('dashboard.activeStudents.title')}
                                </CardTitle>
                                <Users
                                    className="size-5 text-muted-foreground"
                                    aria-hidden
                                />
                            </div>
                            <CardDescription>
                                {t('dashboard.activeStudents.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold tabular-nums">
                                {stats.activeStudents}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="text-base font-medium">
                                    {t('dashboard.activeCourses.title')}
                                </CardTitle>
                                <BookOpen
                                    className="size-5 text-muted-foreground"
                                    aria-hidden
                                />
                            </div>
                            <CardDescription>
                                {t('dashboard.activeCourses.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <p className="text-3xl font-semibold tabular-nums">
                                {stats.activeCourses}
                            </p>
                            {canManageCourses ? (
                                <Button variant="secondary" size="sm" asChild>
                                    <Link href={admin.courses.index.url()}>
                                        {t('dashboard.activeCourses.goToCourses')}
                                    </Link>
                                </Button>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="text-base font-medium">
                                    {t('dashboard.staff.title')}
                                </CardTitle>
                                <UserCog
                                    className="size-5 text-muted-foreground"
                                    aria-hidden
                                />
                            </div>
                            <CardDescription>
                                {t('dashboard.staff.description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2 text-sm">
                                <li className="flex justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        {t('dashboard.staff.professors')}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {staffExample.professors}
                                    </span>
                                </li>
                                <li className="flex justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        {t('dashboard.staff.administrativeStaff')}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {staffExample.administrativeStaff}
                                    </span>
                                </li>
                                <li className="flex justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        {t('dashboard.staff.administrators')}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {staffExample.administrators}
                                    </span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'dashboard.breadcrumb',
            href: dashboard(),
        },
    ],
};
