import { Head, usePage } from '@inertiajs/react';
import { CubaniaLandingPage } from '@/components/base/cubania';
import { useTranslation } from '@/i18n/use-translation';
import type { LandingPromotion } from '@/types/cubania';

export default function Welcome({
    canLogin = true,
    canRegister = true,
    promotion = null,
}: {
    canLogin?: boolean;
    canRegister?: boolean;
    promotion?: LandingPromotion | null;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('landing.welcome.headTitle')}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap"
                />
            </Head>
            <CubaniaLandingPage
                isAuthenticated={Boolean(auth.user)}
                canLogin={canLogin}
                canRegister={canRegister}
                promotion={promotion}
            />
        </>
    );
}
