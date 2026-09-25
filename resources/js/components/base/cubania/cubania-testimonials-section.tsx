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
                        quote={t('landing.testimonials.maria.quote')}
                        authorName={t('landing.testimonials.maria.authorName')}
                        authorRole={t('landing.testimonials.maria.authorRole')}
                        avatarLetter="M"
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={100}>
                    <CubaniaTestimonialCard
                        quote={t('landing.testimonials.rafael.quote')}
                        authorName={t('landing.testimonials.rafael.authorName')}
                        authorRole={t('landing.testimonials.rafael.authorRole')}
                        avatarLetter="R"
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={200}>
                    <CubaniaTestimonialCard
                        quote={t('landing.testimonials.laura.quote')}
                        authorName={t('landing.testimonials.laura.authorName')}
                        authorRole={t('landing.testimonials.laura.authorRole')}
                        avatarLetter="L"
                    />
                </CubaniaReveal>
            </div>
        </section>
    );
}
