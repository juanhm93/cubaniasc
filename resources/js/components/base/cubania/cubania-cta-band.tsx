import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CubaniaWeeklySchedule } from '@/components/base/cubania/cubania-weekly-schedule';
import { useTranslation } from '@/i18n/use-translation';
import preRegistration from '@/routes/pre-registration';

/**
 * Full-width yellow CTA strip above testimonials, with the weekly timetable.
 */
export function CubaniaCtaBand(): ReactNode {
    const { t } = useTranslation();

    return (
        <div className="cubania-cta-band" id="inscripcion">
            <div className="cubania-cta-band__copy">
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
                <Link
                    href={preRegistration.create.url()}
                    className="cubania-btn cubania-btn--outline-dark"
                    data-cubania-cursor="interactive"
                >
                    {t('landing.cta.preRegistration')}
                </Link>
            </div>
            <CubaniaWeeklySchedule />
        </div>
    );
}
