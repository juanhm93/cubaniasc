import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import preRegistration from '@/routes/pre-registration';

const WHATSAPP_URL = 'https://wa.me/+584122801334';

/**
 * Full-width yellow CTA strip above testimonials.
 */
export function CubaniaCtaBand(): ReactNode {
    return (
        <div className="cubania-cta-band" id="inscripcion">
            <div className="cubania-cta-band__text">
                <div className="cubania-cta-band__title">
                    ¿Listo para
                    <br />
                    moverte?
                </div>
                <p className="cubania-cta-band__sub">
                    Primera clase sin compromiso. Solo trae tus ganas de bailar.
                </p>
            </div>
            <div className="cubania-cta-band__actions">
                <Link
                    href={preRegistration.create.url()}
                    className="cubania-btn cubania-btn--outline-dark"
                    data-cubania-cursor="interactive"
                >
                    Preinscripción web
                </Link>
                <a
                    href={WHATSAPP_URL}
                    target="_blank"
                    rel="noreferrer"
                    className="cubania-btn cubania-btn--dark"
                    data-cubania-cursor="interactive"
                >
                    Contáctanos por WhatsApp
                </a>
            </div>
        </div>
    );
}
