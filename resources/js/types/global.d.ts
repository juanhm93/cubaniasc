import type { Auth } from '@/types/auth';
import type { CubaniaShared } from '@/types/cubania';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            canLogin: boolean;
            canRegister: boolean;
            cubania: CubaniaShared;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
