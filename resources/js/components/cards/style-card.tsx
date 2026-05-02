import type { ReactNode } from 'react';

type CubaniaStyleCardProps = {
    highlight?: boolean;
    bgGradient?: string;
    icon: string;
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
    bgGradient,
    icon,
    name,
    description,
    onActivate,
}: CubaniaStyleCardProps): ReactNode {
    const className = `cubania-style-card ${highlight ? 'cubania-style-card--highlight' : ''}`.trim();

    const body = (
        <>
            <div
                className="cubania-style-card__bg"
                style={bgGradient ? { background: bgGradient } : undefined}
            />
            <div className="cubania-style-card__overlay" />
            <div className="cubania-style-card__content">
                <span className="cubania-style-card__icon">{icon}</span>
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
                aria-label={`Ver video: ${name}`}
            >
                {body}
            </button>
        );
    }

    return (
        <article
            className={className}
            data-cubania-cursor="interactive"
        >
            {body}
        </article>
    );
}
