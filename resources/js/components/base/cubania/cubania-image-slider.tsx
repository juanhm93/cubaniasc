import { useCallback, useEffect, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';

export type CubaniaImageSliderProps = {
    images: string[];
    alt?: string;
    ariaLabel?: string;
    autoPlayMs?: number;
    className?: string;
    slideAriaLabel?: (current: number, total: number) => string;
};

/**
 * Reusable image carousel. Pass any list of image URLs via `images`.
 */
export function CubaniaImageSlider({
    images,
    alt = '',
    ariaLabel,
    autoPlayMs = 5000,
    className = '',
    slideAriaLabel,
}: CubaniaImageSliderProps): ReactNode {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const count = images.length;
    const imagesKey = images.join('|');
    const [seenImagesKey, setSeenImagesKey] = useState(imagesKey);

    if (seenImagesKey !== imagesKey) {
        setSeenImagesKey(imagesKey);
        setIndex(0);
    }

    useEffect(() => {
        if (count <= 1 || autoPlayMs <= 0 || paused) {
            return;
        }

        if (
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const timer = window.setInterval(() => {
            setIndex((current) => (current + 1) % count);
        }, autoPlayMs);

        return () => window.clearInterval(timer);
    }, [autoPlayMs, count, paused]);

    const goTo = useCallback(
        (next: number): void => {
            if (count === 0) {
                return;
            }

            setIndex(((next % count) + count) % count);
        },
        [count],
    );

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(index + 1);
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(index - 1);
        }
    };

    if (count === 0) {
        return null;
    }

    return (
        <div
            className={`cubania-image-slider ${className}`.trim()}
            role="region"
            aria-roledescription="carousel"
            aria-label={ariaLabel}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
        >
            <div className="cubania-image-slider__viewport">
                {images.map((src, imageIndex) => (
                    <figure
                        key={src}
                        className={`cubania-image-slider__slide${imageIndex === index ? ' cubania-image-slider__slide--active' : ''}`}
                        aria-hidden={imageIndex !== index}
                    >
                        <img
                            src={src}
                            alt={imageIndex === index ? alt : ''}
                            loading={imageIndex === 0 ? 'eager' : 'lazy'}
                        />
                    </figure>
                ))}
            </div>

            {count > 1 ? (
                <div
                    className="cubania-image-slider__dots"
                    role="tablist"
                    aria-label={ariaLabel}
                >
                    {images.map((src, imageIndex) => {
                        const current = imageIndex + 1;
                        const label = slideAriaLabel
                            ? slideAriaLabel(current, count)
                            : `${current} / ${count}`;

                        return (
                            <button
                                key={src}
                                type="button"
                                role="tab"
                                className={`cubania-image-slider__dot${imageIndex === index ? ' cubania-image-slider__dot--active' : ''}`}
                                aria-label={label}
                                aria-selected={imageIndex === index}
                                onClick={() => goTo(imageIndex)}
                            />
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
