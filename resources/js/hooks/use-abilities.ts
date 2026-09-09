import { usePage } from '@inertiajs/react';
import type { AuthAbilities } from '@/types';

const emptyAbilities: AuthAbilities = {
    content: false,
    payments: false,
    courses: false,
    oneTimeSessions: false,
    students: false,
    adminUsers: false,
};

export function useAbilities(): AuthAbilities {
    const { auth } = usePage().props;

    return auth.abilities ?? emptyAbilities;
}
