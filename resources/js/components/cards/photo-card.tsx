import type { ReactNode } from 'react';

type PhotoVariant = 'a' | 'b' | 'c';

type CubaniaPhotoCardProps = {
    variant: PhotoVariant;
    gradient: string;
    emoji: string;
    tag: string;
};

const emojiClass: Record<PhotoVariant, string> = {
    a: 'cubania-photo-card__emoji',
    b: 'cubania-photo-card__emoji cubania-photo-card__emoji--sm',
    c: 'cubania-photo-card__emoji cubania-photo-card__emoji--xs',
};

const variantClass: Record<PhotoVariant, string> = {
    a: 'cubania-photo-card--a',
    b: 'cubania-photo-card--b',
    c: 'cubania-photo-card--c',
};

/**
 * Floating gradient “photo” tile used in the hero collage.
 */
export function CubaniaPhotoCard({
    variant,
    gradient,
    emoji,
    tag,
}: CubaniaPhotoCardProps): ReactNode {
    return (
        <div
            className={`cubania-photo-card ${variantClass[variant]}`}
            style={{ background: gradient }}
            data-cubania-cursor="interactive"
        >
            <span className={emojiClass[variant]} aria-hidden>
                {emoji}
            </span>
            <div className="cubania-photo-card__wash" />
            <span className="cubania-photo-card__tag">{tag}</span>
        </div>
    );
}
