import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslation } from '@/i18n/use-translation';

export default function AppLogo() {
    const { t } = useTranslation();

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {t('navigation.appName')}
                </span>
            </div>
        </>
    );
}
