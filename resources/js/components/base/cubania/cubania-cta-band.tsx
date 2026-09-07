import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { useTranslation } from '@/i18n/use-translation';
import preRegistration from '@/routes/pre-registration';

/**
 * Full-width yellow CTA strip above testimonials.
 */
export function CubaniaCtaBand(): ReactNode {
    const { t } = useTranslation();
    const { social } = useCubaniaConfig();

    return (
        <div className="cubania-cta-band" id="inscripcion">
            <div className="cubania-cta-band__text">
                <div className="cubania-cta-band__title">
                    {t('landing.cta.titleLine1')}
                    <br />
                    {t('landing.cta.titleLine2')}
                </div>
                <p className="cubania-cta-band__sub">
                    {t('landing.cta.subtitle')}
                </p>
            </div>
            <div className="cubania-cta-band__actions">
                <Link
                    href={preRegistration.create.url()}
                    className="cubania-btn cubania-btn--outline-dark"
                    data-cubania-cursor="interactive"
                >
                    {t('landing.cta.preRegistration')}
                </Link>
                {social.whatsapp ? (
                    <a
                        href={social.whatsapp}
                        target="_blank"
                        rel="noreferrer"
                        className="cubania-btn cubania-btn--dark"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.cta.whatsapp')}
                    </a>
                ) : null}
            </div>
        </div>
    );
}
