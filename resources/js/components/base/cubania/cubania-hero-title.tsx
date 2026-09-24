import type { ReactNode } from 'react';

type CubaniaHeroTitleProps = {
    lead: string;
    accent: string;
};

/** Delay before the first letter of the lead word rises, in ms. */
const LEAD_START_MS = 420;

/** Stagger between letters, in ms. */
const LEAD_STAGGER_MS = 30;

/**
 * Hero headline: the lead word rises letter by letter, then the accent word
 * drops in on the downbeat with a short overshoot.
 */
export function CubaniaHeroTitle({
    lead,
    accent,
}: CubaniaHeroTitleProps): ReactNode {
    const letters = Array.from(lead);
    const accentDelayMs =
        LEAD_START_MS + letters.length * LEAD_STAGGER_MS + 120;

    return (
        <h1 className="cubania-hero__title" aria-label={`${lead} ${accent}`}>
            <span className="cubania-hero__title-lead" aria-hidden>
                {letters.map((letter, index) => (
                    <span
                        className="cubania-hero__letter"
                        key={`${letter}-${index}`}
                        style={{
                            animationDelay: `${LEAD_START_MS + index * LEAD_STAGGER_MS}ms`,
                        }}
                    >
                        {letter === ' ' ? ' ' : letter}
                    </span>
                ))}
            </span>
            <span
                className="cubania-hero__title-accent"
                style={{ animationDelay: `${accentDelayMs}ms` }}
                aria-hidden
            >
                {accent}
            </span>
        </h1>
    );
}
