import type { ReactNode } from 'react';
import { CubaniaReveal } from '@/components/base/cubania/cubania-reveal';
import { CubaniaTestimonialCard } from '@/components/base/cubania/cubania-testimonial-card';
import { useTranslation } from '@/i18n/use-translation';

/**
 * Community quotes section.
 */
export function CubaniaTestimonialsSection(): ReactNode {
    const { t } = useTranslation();

    return (
        <section className="cubania-testimonials" id="testimonios">
            <div className="cubania-section-header__label">
                {t('landing.testimonials.sectionLabel')}
            </div>
            <h2 className="cubania-section-header__title">
                {t('landing.testimonials.sectionTitleLine1')}
                <br />
                {t('landing.testimonials.sectionTitleLine2')}
            </h2>

            <div className="cubania-testimonials__grid">
                <CubaniaReveal delayMs={0}>
                    <CubaniaTestimonialCard
                        quote={t('landing.testimonials.narcy.quote')}
                        authorName={t('landing.testimonials.narcy.authorName')}
                        authorRole={t('landing.testimonials.narcy.authorRole')}
                        avatarLetter="N"
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={100}>
                    <CubaniaTestimonialCard
                        quote={t('landing.testimonials.whitney.quote')}
                        authorName={t(
                            'landing.testimonials.whitney.authorName',
                        )}
                        authorRole={t(
                            'landing.testimonials.whitney.authorRole',
                        )}
                        avatarLetter="W"
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={200}>
                    <CubaniaTestimonialCard
                        quote={t('landing.testimonials.armando.quote')}
                        authorName={t(
                            'landing.testimonials.armando.authorName',
                        )}
                        authorRole={t(
                            'landing.testimonials.armando.authorRole',
                        )}
                        avatarLetter="A"
                    />
                </CubaniaReveal>
            </div>
        </section>
    );
}
