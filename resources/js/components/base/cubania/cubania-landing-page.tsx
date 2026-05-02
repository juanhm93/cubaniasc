import type { ReactNode } from 'react';
import { CubaniaCtaBand } from '@/components/base/cubania/cubania-cta-band';
import { CubaniaCursor } from '@/components/base/cubania/cubania-cursor';
import { CubaniaFooter } from '@/components/base/cubania/cubania-footer';
import { CubaniaHero } from '@/components/base/cubania/cubania-hero';
import { CubaniaNav } from '@/components/base/cubania/cubania-nav';
import { CubaniaStylesSection } from '@/components/base/cubania/cubania-styles-section';
import { CubaniaTestimonialsSection } from '@/components/base/cubania/cubania-testimonials-section';
import { useCubaniaSmoothScroll } from '@/components/base/cubania/use-cubania-smooth-scroll';

import '../../../../css/landing/cubania-landing.css';

export type CubaniaLandingPageProps = {
    isAuthenticated: boolean;
    canRegister: boolean;
    /** When `true`, hides the system cursor and shows the Cubanía dot + ring. */
    customCursor?: boolean;
};

/**
 * Composes the full Cubanía marketing landing (nav, hero, sections, footer).
 */
export function CubaniaLandingPage({
    isAuthenticated,
    canRegister,
    customCursor = false,
}: CubaniaLandingPageProps): ReactNode {
    useCubaniaSmoothScroll();

    return (
        <div
            className={`cubania-landing${customCursor ? ' cubania-landing--custom-cursor' : ''}`.trim()}
        >
            {customCursor ? <CubaniaCursor /> : null}
            <CubaniaNav
                isAuthenticated={isAuthenticated}
                canRegister={canRegister}
            />
            <CubaniaHero />
            <CubaniaStylesSection />
            <section
                id="horarios"
                className="cubania-section-anchor"
                aria-label="Horarios"
            />
            <CubaniaCtaBand />
            <CubaniaTestimonialsSection />
            <CubaniaFooter />
        </div>
    );
}
