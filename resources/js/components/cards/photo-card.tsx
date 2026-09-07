import type { ReactNode } from 'react';

type PhotoVariant = 'a' | 'b' | 'c';

type CubaniaPhotoCardProps = {
    variant: PhotoVariant;
    gradient: string;
    image: string;
    tag: string;
};

const variantClass: Record<PhotoVariant, string> = {
    a: 'cubania-photo-card--a',
    b: 'cubania-photo-card--b',
    c: 'cubania-photo-card--c',
};

/**
 * Floating instructor tile used in the hero collage.
 */
export function CubaniaPhotoCard({
    variant,
    gradient,
    image,
    tag,
}: CubaniaPhotoCardProps): ReactNode {
    return (
        <div
            className={`cubania-photo-card ${variantClass[variant]}`}
            data-cubania-cursor="interactive"
        >
            <img
                className="cubania-photo-card__media"
                src={image}
                alt=""
                loading="lazy"
            />
            <div
                className="cubania-photo-card__tint"
                style={{ background: gradient }}
            />
            <div className="cubania-photo-card__stripes" aria-hidden />
            <div className="cubania-photo-card__lines" aria-hidden />
            <div className="cubania-photo-card__wash" />
            <span className="cubania-photo-card__tag">{tag}</span>
        </div>
    );
}
