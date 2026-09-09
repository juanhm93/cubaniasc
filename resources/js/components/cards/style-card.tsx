import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n/use-translation';

type CubaniaStyleCardProps = {
    highlight?: boolean;
    image: string;
    name: string;
    description: string;
    /** When set, the card is a button and opens the video modal (parent handles the player). */
    onActivate?: () => void;
};

/**
 * Large tile for the “Nuestros estilos” grid.
 */
export function CubaniaStyleCard({
    highlight = false,
    image,
    name,
    description,
    onActivate,
}: CubaniaStyleCardProps): ReactNode {
    const { t } = useTranslation();
    const className =
        `cubania-style-card ${highlight ? 'cubania-style-card--highlight' : ''}`.trim();

    const body = (
        <>
            <img
                className="cubania-style-card__media"
                src={image}
                alt=""
                loading="lazy"
            />
            <div className="cubania-style-card__tint" />
            <div className="cubania-style-card__overlay" />
            <div className="cubania-style-card__content">
                <div className="cubania-style-card__name">{name}</div>
                <p className="cubania-style-card__desc">{description}</p>
            </div>
            <div className="cubania-style-card__arrow" aria-hidden>
                →
            </div>
        </>
    );

    if (onActivate) {
        return (
            <button
                type="button"
                className={className}
                data-cubania-cursor="interactive"
                onClick={onActivate}
                aria-label={t('landing.styleCard.watchVideo', { name })}
            >
                {body}
            </button>
        );
    }

    return (
        <article className={className} data-cubania-cursor="interactive">
            {body}
        </article>
    );
}
