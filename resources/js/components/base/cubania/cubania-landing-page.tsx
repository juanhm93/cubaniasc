import type { ReactNode } from 'react';
import { CubaniaAboutSection } from '@/components/base/cubania/cubania-about-section';
import { CubaniaCtaBand } from '@/components/base/cubania/cubania-cta-band';
import { CubaniaCursor } from '@/components/base/cubania/cubania-cursor';
import { CubaniaFooter } from '@/components/base/cubania/cubania-footer';
import { CubaniaHero } from '@/components/base/cubania/cubania-hero';
import { CubaniaNav } from '@/components/base/cubania/cubania-nav';
import { CubaniaPromotionModal } from '@/components/base/cubania/cubania-promotion-modal';
import { CubaniaPromotionSection } from '@/components/base/cubania/cubania-promotion-section';
import { CubaniaStylesSection } from '@/components/base/cubania/cubania-styles-section';
import { CubaniaTestimonialsSection } from '@/components/base/cubania/cubania-testimonials-section';
import { CubaniaWhatsappFloat } from '@/components/base/cubania/cubania-whatsapp-float';
import { useCubaniaSmoothScroll } from '@/components/base/cubania/use-cubania-smooth-scroll';
import type { LandingPromotion } from '@/types/cubania';

import '../../../../css/landing/cubania-landing.css';

export type CubaniaLandingPageProps = {
    isAuthenticated: boolean;
    canLogin: boolean;
    canRegister: boolean;
    /** When `true`, hides the system cursor and shows the Cubanía dot + ring. */
    customCursor?: boolean;
    /** Today's promotion: entry modal, section after the hero, or both, as staff chose. */
    promotion?: LandingPromotion | null;
};

/**
 * Composes the full Cubanía marketing landing (nav, hero, sections, footer).
 */
export function CubaniaLandingPage({
    isAuthenticated,
    canLogin,
    canRegister,
    customCursor = false,
    promotion = null,
}: CubaniaLandingPageProps): ReactNode {
    useCubaniaSmoothScroll();

    return (
        <div
            className={[
                'cubania-landing',
                customCursor ? 'cubania-landing--custom-cursor' : '',
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {customCursor ? <CubaniaCursor /> : null}
            <CubaniaNav
                isAuthenticated={isAuthenticated}
                canLogin={canLogin}
                canRegister={canRegister}
            />
            <CubaniaHero />
            {promotion?.showSection ? (
                <CubaniaPromotionSection promotion={promotion} />
            ) : null}
            <CubaniaStylesSection />
            <CubaniaCtaBand />
            <CubaniaAboutSection />
            <CubaniaTestimonialsSection />
            <CubaniaFooter />
            <CubaniaWhatsappFloat />
            {promotion?.showModal ? (
                <CubaniaPromotionModal
                    key={`${promotion.id}-${promotion.version}`}
                    promotion={promotion}
                />
            ) : null}
        </div>
    );
}
