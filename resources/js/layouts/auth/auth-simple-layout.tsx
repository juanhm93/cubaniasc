import { Link } from '@inertiajs/react';
import { AppBrandLogo } from '@/components/app-brand-logo';
import { useTranslation } from '@/i18n/use-translation';
import type { AuthLayoutProps } from '@/types';
import { home } from '@/routes';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();
    const resolvedTitle = t(title);
    const resolvedDescription = t(description);

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <AppBrandLogo className="max-h-14" />
                            <span className="sr-only">{resolvedTitle}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{resolvedTitle}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {resolvedDescription}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
