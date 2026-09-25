import { usePage } from '@inertiajs/react';

export function useIsAdmin(): boolean {
    const { auth } = usePage().props;

    return auth.user?.role?.slug === 'admin';
}
