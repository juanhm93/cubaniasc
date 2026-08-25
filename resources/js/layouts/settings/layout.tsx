import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/i18n/use-translation';
import { cn, toUrl } from '@/lib/utils';
import { edit as companySettingsEdit } from '@/actions/App/Http/Controllers/Settings/CompanySettingsController';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const { auth } = usePage().props as {
        auth?: {
            user?: {
                role?: { slug?: string } | null;
                is_owner?: number | boolean;
            } | null;
        };
    };

    const showCompanySettings =
        auth?.user?.role?.slug === 'admin' ||
        auth?.user?.is_owner === 1 ||
        auth?.user?.is_owner === true;

    const sidebarNavItems: NavItem[] = [
        {
            title: 'navigation.profile',
            href: edit(),
            icon: null,
        },
        ...(showCompanySettings
            ? [
                  {
                      title: 'navigation.company',
                      href: companySettingsEdit.url(),
                      icon: null,
                  },
              ]
            : []),
        {
            title: 'navigation.security',
            href: editSecurity(),
            icon: null,
        },
        {
            title: 'navigation.appearance',
            href: editAppearance(),
            icon: null,
        },
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('navigation.settingsTitle')}
                description={t('navigation.settingsDescription')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label={t('navigation.settingsNavAriaLabel')}
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {t(item.title)}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
