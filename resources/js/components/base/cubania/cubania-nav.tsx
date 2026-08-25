import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n/use-translation';
import { dashboard, login, register } from '@/routes';
import preRegistration from '@/routes/pre-registration';

type CubaniaNavProps = {
    isAuthenticated: boolean;
    canRegister: boolean;
};

/**
 * Fixed top navigation for the marketing landing.
 */
export function CubaniaNav({
    isAuthenticated,
    canRegister,
}: CubaniaNavProps): ReactNode {
    const { t } = useTranslation();

    return (
        <nav className="cubania-nav">
            <Link
                href="/"
                className="cubania-nav__logo"
                data-cubania-cursor="interactive"
            >
                {t('landing.nav.logoCub')}
                <span className="cubania-nav__logo-accent">
                    {t('landing.nav.logoAnia')}
                </span>
            </Link>

            <ul className="cubania-nav__list cubania-nav__list--primary">
                <li>
                    <a
                        href="#clases"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.nav.classes')}
                    </a>
                </li>
                <li>
                    <a
                        href="#estilos"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.nav.styles')}
                    </a>
                </li>
                <li>
                    <a
                        href="#horarios"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.nav.schedule')}
                    </a>
                </li>
                <li>
                    <a
                        href="#nosotros"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.nav.about')}
                    </a>
                </li>
                <li>
                    <Link
                        href={preRegistration.create.url()}
                        className="cubania-nav__link cubania-nav__link--cta"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.nav.enroll')}
                    </Link>
                </li>
            </ul>

            <ul className="cubania-nav__auth">
                {isAuthenticated ? (
                    <li>
                        <Link
                            href={dashboard()}
                            className="cubania-nav__link cubania-nav__link--cta cubania-nav__link--compact"
                            data-cubania-cursor="interactive"
                        >
                            {t('landing.nav.dashboard')}
                        </Link>
                    </li>
                ) : (
                    <>
                        <li>
                            <Link
                                href={login()}
                                className="cubania-nav__link cubania-nav__link--compact"
                                data-cubania-cursor="interactive"
                            >
                                {t('landing.nav.logIn')}
                            </Link>
                        </li>
                        {canRegister && (
                            <li>
                                <Link
                                    href={register()}
                                    className="cubania-nav__link cubania-nav__link--cta cubania-nav__link--compact"
                                    data-cubania-cursor="interactive"
                                >
                                    {t('landing.nav.register')}
                                </Link>
                            </li>
                        )}
                    </>
                )}
            </ul>
        </nav>
    );
}
