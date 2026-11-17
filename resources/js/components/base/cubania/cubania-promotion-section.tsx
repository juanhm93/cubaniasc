import type { ReactNode } from 'react';
import { CubaniaPromotionCta } from '@/components/base/cubania/cubania-promotion-cta';
import { CubaniaReveal } from '@/components/base/cubania/cubania-reveal';
import { useTranslation } from '@/i18n/use-translation';
import type { LandingPromotion } from '@/types/cubania';

type CubaniaPromotionSectionProps = {
    promotion: LandingPromotion;
};

/**
 * Fixed landing block with the current promotion and its optional action button. It has no close button.
 */
export function CubaniaPromotionSection({
    promotion,
}: CubaniaPromotionSectionProps): ReactNode {
    const { t } = useTranslation();

    return (
        <section
            className="cubania-promo"
            id="promocion"
            aria-label={t('landing.promotion.sectionAriaLabel')}
        >
            <CubaniaReveal className="cubania-promo__inner">
                <div className="cubania-promo__copy">
                    <span className="cubania-promo__badge">
                        <span className="cubania-promo__badge-burst">
                            <span className="cubania-promo__badge-text">
                                {t('landing.promotion.eyebrow')}
                            </span>
                        </span>
                    </span>
                    <h2 className="cubania-promo__title">{promotion.title}</h2>
                    <CubaniaPromotionCta
                        promotion={promotion}
                        className="cubania-btn cubania-btn--primary"
                    />
                </div>
                <img
                    src={promotion.imageUrl}
                    alt={promotion.title}
                    className="cubania-promo__image"
                    loading="lazy"
                />
            </CubaniaReveal>
        </section>
    );
}
