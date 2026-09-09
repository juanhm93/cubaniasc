export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    role?: {
        id: number;
        name: string;
        slug: string;
    } | null;
    [key: string]: unknown;
};

export type AuthAbilities = {
    content: boolean;
    payments: boolean;
    courses: boolean;
    oneTimeSessions: boolean;
    students: boolean;
    adminUsers: boolean;
};

export type Auth = {
    user: User;
    abilities: AuthAbilities;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
