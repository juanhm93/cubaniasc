import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useAbilities } from '@/hooks/use-abilities';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import {
    getNotifications,
    markAllNotificationsAsRead,
    markNotificationAsRead,
} from '@/services/notificationService';
import type { AppNotification } from '@/types/notifications';

type PanelStatus = 'idle' | 'loading' | 'success' | 'error';

function formatRelativeEs(iso: string | null, emptyLabel: string): string {
    if (!iso) {
        return emptyLabel;
    }

    try {
        return new Date(iso).toLocaleDateString('es', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return emptyLabel;
    }
}

export function NotificationBell() {
    const { t } = useTranslation();
    const abilities = useAbilities();
    const sharedUnreadCount = usePage().props.notifications?.unreadCount ?? 0;

    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState<PanelStatus>('idle');
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [unreadCount, setUnreadCount] = useState(sharedUnreadCount);
    const [lastSharedCount, setLastSharedCount] = useState(sharedUnreadCount);

    // Re-sync with the server count whenever Inertia hands us a fresh one,
    // while still letting local reads lower the badge without a navigation.
    if (lastSharedCount !== sharedUnreadCount) {
        setLastSharedCount(sharedUnreadCount);
        setUnreadCount(sharedUnreadCount);
    }

    const load = useCallback(async (): Promise<void> => {
        setStatus('loading');

        try {
            const payload = await getNotifications();

            setNotifications(payload.notifications);
            setUnreadCount(payload.unread_count);
            setStatus('success');
        } catch {
            setStatus('error');
        }
    }, []);

    function handleOpenChange(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (nextOpen) {
            void load();
        }
    }

    async function handleMarkAllAsRead(): Promise<void> {
        const previous = notifications;

        setNotifications((current) =>
            current.map((item) => ({
                ...item,
                read_at: item.read_at ?? new Date().toISOString(),
            })),
        );
        setUnreadCount(0);

        try {
            await markAllNotificationsAsRead();
        } catch {
            setNotifications(previous);
            void load();
        }
    }

    async function handleSelect(notification: AppNotification): Promise<void> {
        if (notification.read_at === null) {
            setNotifications((current) =>
                current.map((item) =>
                    item.id === notification.id
                        ? { ...item, read_at: new Date().toISOString() }
                        : item,
                ),
            );
            setUnreadCount((current) => Math.max(0, current - 1));

            try {
                await markNotificationAsRead(notification.id);
            } catch {
                void load();
            }
        }

        if (!abilities.payments) {
            return;
        }

        setOpen(false);
        router.visit(
            admin.payments.index.url({
                query: {
                    tab: 'mas',
                    preRegistration: notification.data.pre_registration_id,
                },
            }),
        );
    }

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9"
                    aria-label={t('notifications.title')}
                >
                    <Bell className="!size-5 opacity-80" />
                    {unreadCount > 0 ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-medium text-white">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    ) : null}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-96 w-80 overflow-y-auto p-0"
            >
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="text-sm font-medium">
                        {t('notifications.title')}
                    </span>
                    {unreadCount > 0 ? (
                        <button
                            type="button"
                            className="cursor-pointer text-xs text-muted-foreground hover:text-foreground"
                            onClick={() => void handleMarkAllAsRead()}
                        >
                            {t('notifications.markAllAsRead')}
                        </button>
                    ) : null}
                </div>

                {status === 'loading' ? (
                    <div className="space-y-3 p-3">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                ) : null}

                {status === 'error' ? (
                    <div className="p-4 text-center">
                        <p className="text-sm text-muted-foreground">
                            {t('notifications.loadError')}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="mt-2"
                            onClick={() => void load()}
                        >
                            {t('notifications.retry')}
                        </Button>
                    </div>
                ) : null}

                {status === 'success' && notifications.length === 0 ? (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        {t('notifications.empty')}
                    </p>
                ) : null}

                {status === 'success' && notifications.length > 0 ? (
                    <ul className="divide-y">
                        {notifications.map((notification) => (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        void handleSelect(notification)
                                    }
                                    className={cn(
                                        'w-full px-3 py-3 text-left transition-colors hover:bg-accent',
                                        abilities.payments
                                            ? 'cursor-pointer'
                                            : 'cursor-default',
                                        notification.read_at === null &&
                                            'bg-accent/40',
                                    )}
                                >
                                    <p className="text-sm font-medium">
                                        {t('notifications.newPreRegistration', {
                                            name: notification.data.name,
                                        })}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {notification.data.email}
                                        {notification.data.country_label
                                            ? ` · ${notification.data.country_label}`
                                            : ''}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        {formatRelativeEs(
                                            notification.created_at,
                                            t('common.emDash'),
                                        )}
                                    </p>
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default NotificationBell;
