import { Head, Link } from '@inertiajs/react';
import { useTranslation } from '@/i18n/use-translation';
import { logout } from '@/routes';

export default function AccountPending() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('account.pending.headTitle')} />

            <div className="space-y-4 text-center text-sm leading-relaxed text-muted-foreground">
                <p className="text-base font-medium text-foreground">
                    {t('account.pending.created')}
                </p>
                <p>
                    {t('account.pending.statusExplanation')}{' '}
                    <span className="font-medium text-foreground">
                        {t('account.pending.statusPending')}
                    </span>
                    {t('account.pending.statusDetails')}
                </p>
                <p>{t('account.pending.activationInfo')}</p>

                <Link
                    href={logout()}
                    method="post"
                    as="button"
                    className="inline-block pt-2 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    {t('account.pending.logOut')}
                </Link>
            </div>
        </>
    );
}

AccountPending.layout = {
    title: 'account.pending.layoutTitle',
    description: 'account.pending.layoutDescription',
};
