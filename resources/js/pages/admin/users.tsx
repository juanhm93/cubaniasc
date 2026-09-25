import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { useTranslation } from '@/i18n/use-translation';

type Role = {
    id: number;
    name: string;
    slug: string;
};

type UserItem = {
    id: number;
    name: string;
    email: string;
    status: string;
    role_id: number | null;
    is_owner?: boolean | number;
    role?: Role | null;
    company?: {
        id: number;
        name: string;
    } | null;
};

type AdminUsersProps = {
    users: UserItem[];
    roles: Role[];
    can_run_owner_maintenance: boolean;
};

const STATUS_VALUES = ['active', 'pending'] as const;

function userIsOwner(user: UserItem): boolean {
    return Boolean(user.is_owner);
}

export default function AdminUsers({
    users,
    roles,
    can_run_owner_maintenance: canRunOwnerMaintenance,
}: AdminUsersProps) {
    const { t } = useTranslation();
    const [savingUserId, setSavingUserId] = useState<number | null>(null);
    const [maintenanceAction, setMaintenanceAction] = useState<
        'cache' | 'migrate' | null
    >(null);

    function statusLabel(status: string): string {
        if (status === 'active' || status === 'pending') {
            return t(`admin.userStatus.${status}`);
        }

        return status;
    }

    function updateRole(userId: number, roleId: number): void {
        setSavingUserId(userId);

        router.patch(
            admin.users.role.update.url(userId),
            { role_id: roleId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(t('admin.users.roleUpdated'));
                },
                onError: () => {
                    toast.error(t('admin.users.roleUpdateFailed'));
                },
                onFinish: () => {
                    setSavingUserId(null);
                },
            },
        );
    }

    function updateStatus(userId: number, status: string): void {
        setSavingUserId(userId);

        router.patch(
            admin.users.status.update.url(userId),
            { status },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(t('admin.users.statusUpdated'));
                },
                onError: () => {
                    toast.error(t('admin.users.statusUpdateFailed'));
                },
                onFinish: () => {
                    setSavingUserId(null);
                },
            },
        );
    }

    function runOwnerMaintenance(kind: 'cache' | 'migrate'): void {
        const url =
            kind === 'cache'
                ? admin.maintenance.cacheClear.url()
                : admin.maintenance.migrate.url();
        const successMessage =
            kind === 'cache'
                ? t('admin.users.cachesCleared')
                : t('admin.users.migrationsFinished');

        setMaintenanceAction(kind);

        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(successMessage);
                },
                onError: () => {
                    toast.error(t('admin.users.actionFailed'));
                },
                onFinish: () => {
                    setMaintenanceAction(null);
                },
            },
        );
    }

    return (
        <>
            <Head title={t('admin.users.title')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-3 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-6">
                        <div className="min-w-0 space-y-1">
                            <h1 className="text-2xl font-semibold">
                                {t('admin.users.title')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.users.description')}
                            </p>
                        </div>
                        {canRunOwnerMaintenance ? (
                            <div className="flex shrink-0 flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={maintenanceAction !== null}
                                    onClick={() =>
                                        runOwnerMaintenance('cache')
                                    }
                                >
                                    {maintenanceAction === 'cache'
                                        ? t('admin.users.clearingCaches')
                                        : t('admin.users.clearApplicationCaches')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={maintenanceAction !== null}
                                    onClick={() =>
                                        runOwnerMaintenance('migrate')
                                    }
                                >
                                    {maintenanceAction === 'migrate'
                                        ? t('admin.users.running')
                                        : t('admin.users.runMigrations')}
                                </Button>
                            </div>
                        ) : null}
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[640px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.users.user')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.users.currentRole')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.users.assignRole')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.status')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => {
                                    const isOwnerUser = userIsOwner(user);

                                    return (
                                        <tr
                                            key={user.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle">
                                                <p className="font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                <span className="inline-flex h-9 max-w-[min(100%,16rem)] items-center truncate rounded-md border border-sidebar-border px-3">
                                                    {savingUserId === user.id
                                                        ? t(
                                                              'admin.users.saving',
                                                          )
                                                        : (user.role?.name ??
                                                          t(
                                                              'admin.users.noRole',
                                                          ))}
                                                </span>
                                            </td>
                                            <td className="w-[min(100%,14rem)] min-w-[12rem] px-3 py-3 align-middle">
                                                {user.role?.slug === 'admin' ||
                                                isOwnerUser ? (
                                                    <span className="text-muted-foreground">
                                                        {t('common.emDash')}
                                                    </span>
                                                ) : (
                                                    <select
                                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                        defaultValue={
                                                            user.role_id ?? ''
                                                        }
                                                        onChange={(event) => {
                                                            const roleId =
                                                                Number(
                                                                    event.target
                                                                        .value,
                                                                );

                                                            if (
                                                                !Number.isNaN(
                                                                    roleId,
                                                                ) &&
                                                                roleId > 0
                                                            ) {
                                                                updateRole(
                                                                    user.id,
                                                                    roleId,
                                                                );
                                                            }
                                                        }}
                                                        disabled={
                                                            savingUserId ===
                                                            user.id
                                                        }
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            {t(
                                                                'admin.users.selectRole',
                                                            )}
                                                        </option>
                                                        {roles.map((role) => (
                                                            <option
                                                                key={role.id}
                                                                value={role.id}
                                                            >
                                                                {role.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                            </td>
                                            <td className="w-36 min-w-[9rem] px-3 py-3 align-middle">
                                                {isOwnerUser ? (
                                                    <span className="inline-flex h-9 w-full items-center rounded-md border border-sidebar-border px-3 capitalize">
                                                        {statusLabel(
                                                            user.status,
                                                        )}
                                                    </span>
                                                ) : (
                                                    <select
                                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm capitalize"
                                                        defaultValue={
                                                            user.status ===
                                                            'pending'
                                                                ? 'pending'
                                                                : 'active'
                                                        }
                                                        onChange={(event) => {
                                                            const status =
                                                                event.target
                                                                    .value;

                                                            if (
                                                                status ===
                                                                    'active' ||
                                                                status ===
                                                                    'pending'
                                                            ) {
                                                                updateStatus(
                                                                    user.id,
                                                                    status,
                                                                );
                                                            }
                                                        }}
                                                        disabled={
                                                            savingUserId ===
                                                            user.id
                                                        }
                                                    >
                                                        {STATUS_VALUES.map(
                                                            (value) => (
                                                                <option
                                                                    key={value}
                                                                    value={value}
                                                                >
                                                                    {t(
                                                                        `admin.userStatus.${value}`,
                                                                    )}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

AdminUsers.layout = {
    breadcrumbs: [
        {
            title: 'navigation.adminUsers',
            href: '/admin/users',
        },
    ],
};
