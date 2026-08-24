import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { dashboard, login, register } from '@/routes';
import preRegistration from '@/routes/pre-registration';

type CubaniaNavProps = {
    isAuthenticated?: boolean;
    canRegister?: boolean;
    variant?: 'full' | 'minimal';
};

/**
 * Fixed top navigation for the marketing landing.
 */
export function CubaniaNav({
    isAuthenticated = false,
    canRegister = false,
    variant = 'full',
}: CubaniaNavProps): ReactNode {
    if (variant === 'minimal') {
        return (
            <nav className="cubania-nav cubania-nav--minimal">
                <Link
                    href="/"
                    className="cubania-nav__logo"
                    data-cubania-cursor="interactive"
                >
                    Cub<span className="cubania-nav__logo-accent">anía</span>
                </Link>

                <Link
                    href="/"
                    className="cubania-nav__link cubania-nav__link--back"
                    data-cubania-cursor="interactive"
                >
                    Volver a la página principal
                </Link>
            </nav>
        );
    }

    return (
        <nav className="cubania-nav">
            <Link
                href="/"
                className="cubania-nav__logo"
                data-cubania-cursor="interactive"
            >
                Cub<span className="cubania-nav__logo-accent">anía</span>
            </Link>

            <ul className="cubania-nav__list cubania-nav__list--primary">
                <li>
                    <a
                        href="#clases"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        Clases
                    </a>
                </li>
                <li>
                    <a
                        href="#estilos"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        Estilos
                    </a>
                </li>
                <li>
                    <a
                        href="#horarios"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        Horarios
                    </a>
                </li>
                <li>
                    <a
                        href="#nosotros"
                        className="cubania-nav__link"
                        data-cubania-cursor="interactive"
                    >
                        Nosotros
                    </a>
                </li>
                <li>
                    <Link
                        href="/repaso"
                        className="cubania-nav__link cubania-nav__link--cta"
                        data-cubania-cursor="interactive"
                    >
                        Repaso
                    </Link>
                </li>
                <li>
                    <Link
                        href={preRegistration.create.url()}
                        className="cubania-nav__link cubania-nav__link--cta"
                        data-cubania-cursor="interactive"
                    >
                        Inscríbete
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
                            Panel
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
                                Entrar
                            </Link>
                        </li>
                        {canRegister && (
                            <li>
                                <Link
                                    href={register()}
                                    className="cubania-nav__link cubania-nav__link--cta cubania-nav__link--compact"
                                    data-cubania-cursor="interactive"
                                >
                                    Registro
                                </Link>
                            </li>
                        )}
                    </>
                )}
            </ul>
        </nav>
    );
}
