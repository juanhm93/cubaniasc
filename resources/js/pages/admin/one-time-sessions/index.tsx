import { Head, Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/i18n/use-translation';

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

const SESSION_TYPE_KEYS: Record<string, string> = {
    workshop: 'admin.sessionTypes.workshop',
    private_class: 'admin.sessionTypes.privateClass',
    event: 'admin.sessionTypes.event',
};

function formatDateTime(
    value: string | null,
    emptyLabel: string,
): string {
    if (!value) {
        return emptyLabel;
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
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.oneTimeSessions.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {t('admin.oneTimeSessions.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('admin.oneTimeSessions.description')}
                        </p>
                    </div>
                    <div className="flex justify-end">
                        <Button asChild>
                            <Link href={admin.oneTimeSessions.create.url()}>
                                {t('admin.oneTimeSessions.createSession')}
                            </Link>
                        </Button>
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[840px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.name')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.type')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.start')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.end')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.place')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.teacher')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.participants')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.price')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.status')}
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
                                            {t('admin.oneTimeSessions.noSessions')}
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
                                                {SESSION_TYPE_KEYS[session.type]
                                                    ? t(
                                                          SESSION_TYPE_KEYS[
                                                              session.type
                                                          ],
                                                      )
                                                    : session.type}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {formatDateTime(
                                                    session.starts_at,
                                                    t('common.emDash'),
                                                )}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {formatDateTime(
                                                    session.ends_at,
                                                    t('common.emDash'),
                                                )}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.place_name ??
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.teacher_name ??
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.attendees_count}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {session.price ??
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {session.is_active ? (
                                                    <Badge>
                                                        {t('common.activeFemale')}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        {t(
                                                            'common.inactiveFemale',
                                                        )}
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
            title: 'navigation.oneTimeSessions',
            href: admin.oneTimeSessions.index.url(),
        },
    ],
};
