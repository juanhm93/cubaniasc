import { Head, usePage } from '@inertiajs/react';
import { CubaniaLandingPage } from '@/components/base/cubania';
import { useTranslation } from '@/i18n/use-translation';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('landing.welcome.headTitle')}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;700;800&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap"
                />
            </Head>
            <CubaniaLandingPage
                isAuthenticated={Boolean(auth.user)}
                canRegister={canRegister}
            />
        </>
    );
}
