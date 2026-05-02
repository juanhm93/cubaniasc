import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { dashboard, login, register } from '@/routes';

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
    return (
        <nav className="cubania-nav">
            <a
                href="#"
                className="cubania-nav__logo"
                data-cubania-cursor="interactive"
            >
                Cub<span className="cubania-nav__logo-accent">anía</span>
            </a>

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
                    <a
                        href="#inscripcion"
                        className="cubania-nav__link cubania-nav__link--cta"
                        data-cubania-cursor="interactive"
                    >
                        Inscríbete
                    </a>
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
