import type { ReactNode } from 'react';
import type { LandingPromotion } from '@/types/cubania';

type CubaniaPromotionCtaProps = {
    promotion: LandingPromotion;
    className: string;
    onClick?: () => void;
};

function isExternalUrl(url: string): boolean {
    if (!/^https?:\/\//i.test(url)) {
        return false;
    }

    if (typeof window === 'undefined') {
        return true;
    }

    try {
        return new URL(url).origin !== window.location.origin;
    } catch {
        return true;
    }
}

/**
 * Action button of a promotion. External links (WhatsApp, Instagram…) open in a new tab.
 * Renders nothing when the promotion has no button.
 */
export function CubaniaPromotionCta({
    promotion,
    className,
    onClick,
}: CubaniaPromotionCtaProps): ReactNode {
    if (promotion.ctaLabel === null || promotion.ctaUrl === null) {
        return null;
    }

    const external = isExternalUrl(promotion.ctaUrl);

    return (
        <a
            href={promotion.ctaUrl}
            className={className}
            data-cubania-cursor="interactive"
            onClick={onClick}
            {...(external
                ? { target: '_blank', rel: 'noopener noreferrer' }
                : {})}
        >
            {promotion.ctaLabel}
        </a>
    );
}
