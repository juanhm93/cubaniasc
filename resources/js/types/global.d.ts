import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            canLogin: boolean;
            canRegister: boolean;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
