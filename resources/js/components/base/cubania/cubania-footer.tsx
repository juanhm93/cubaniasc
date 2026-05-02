import type { ReactNode } from 'react';

const WHATSAPP_URL = 'https://wa.me/+584122801334';
const INSTAGRAM_URL = 'https://www.instagram.com/cubania.sc';

/**
 * Site footer with columns and social links.
 */
export function CubaniaFooter(): ReactNode {
    return (
        <footer className="cubania-footer" id="nosotros">
            <div className="cubania-footer__top">
                <div>
                    <div className="cubania-footer__logo">Cubanía</div>
                    <p className="cubania-footer__tagline">
                        Sigue bailando, cree en ti.
                    </p>
                </div>
                <div className="cubania-footer__columns">
                    <div>
                        <h3 className="cubania-footer__col-title">Academia</h3>
                        <ul className="cubania-footer__list">
                            <li>
                                <a
                                    href="#clases"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Clases
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#horarios"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Horarios
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Precios
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Instructores
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 className="cubania-footer__col-title">Comunidad</h3>
                        <ul className="cubania-footer__list">
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Eventos
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Competencias
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Blog
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Galería
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 className="cubania-footer__col-title">Contacto</h3>
                        <ul className="cubania-footer__list">
                            <li>
                                <a
                                    href={WHATSAPP_URL}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    WhatsApp
                                </a>
                            </li>
                            <li>
                                <a
                                    href={INSTAGRAM_URL}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Instagram
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    Ubicación
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div className="cubania-footer__bottom">
                <span>
                    © {new Date().getFullYear()} Cubanía · Academia de Salsa
                    Casino y Bachata
                </span>
                <div className="cubania-footer__social">
                    <a
                        href={INSTAGRAM_URL}
                        target="_blank"
                        rel="noreferrer"
                        className="cubania-footer__social-link"
                        data-cubania-cursor="interactive"
                    >
                        Instagram
                    </a>
                    <a
                        href={WHATSAPP_URL}
                        target="_blank"
                        rel="noreferrer"
                        className="cubania-footer__social-link"
                        data-cubania-cursor="interactive"
                    >
                        WhatsApp
                    </a>
                </div>
            </div>
        </footer>
    );
}
