import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n/use-translation';

/** Passes of the item list inside one group, so a group always outruns the viewport. */
const PASSES_PER_GROUP = 2;

const REST_SPEED = 55;
const SCROLL_SPEED = 230;
const REST_TILT = -2.5;

/**
 * Infinite yellow band of dance styles across the bottom of the hero. Scrolling
 * speeds the band up and straightens its tilt to 0°.
 */
export function CubaniaHeroMarquee(): ReactNode {
    const { t } = useTranslation();
    const bandRef = useRef<HTMLDivElement>(null);
    const trackRef = useRef<HTMLDivElement>(null);
    const groupRef = useRef<HTMLDivElement>(null);

    const items = [
        t('landing.hero.tagSalsaCasino'),
        t('landing.hero.tagBachata'),
        t('landing.styles.rueda.name'),
        t('landing.hero.hashtag'),
    ];

    useEffect(() => {
        const band = bandRef.current;
        const track = trackRef.current;
        const group = groupRef.current;

        if (!band || !track || !group) {
            return;
        }

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );

        if (reducedMotion.matches) {
            band.style.setProperty('--cubania-marquee-tilt', `${REST_TILT}deg`);

            return;
        }

        let groupWidth = group.offsetWidth;
        let offset = 0;
        let lastTime = 0;
        let frame = 0;

        const measure = (): void => {
            groupWidth = group.offsetWidth;
        };

        const scrollProgress = (): number => {
            const hero = band.closest('.cubania-hero');
            const height =
                hero instanceof HTMLElement
                    ? hero.offsetHeight
                    : window.innerHeight;

            return Math.min(
                1,
                Math.max(0, window.scrollY / Math.max(height, 1)),
            );
        };

        const tick = (time: number): void => {
            const delta =
                lastTime === 0 ? 0 : Math.min((time - lastTime) / 1000, 0.05);
            lastTime = time;

            const progress = scrollProgress();

            band.style.setProperty(
                '--cubania-marquee-tilt',
                `${(REST_TILT * (1 - progress)).toFixed(2)}deg`,
            );

            offset += (REST_SPEED + progress * SCROLL_SPEED) * delta;

            if (groupWidth > 0 && offset >= groupWidth) {
                offset -= groupWidth;
            }

            track.style.transform = `translate3d(${-offset}px, 0, 0)`;
            frame = window.requestAnimationFrame(tick);
        };

        const start = (): void => {
            if (frame !== 0) {
                return;
            }

            lastTime = 0;
            frame = window.requestAnimationFrame(tick);
        };

        const stop = (): void => {
            if (frame === 0) {
                return;
            }

            window.cancelAnimationFrame(frame);
            frame = 0;
        };

        const observer = new IntersectionObserver(([entry]) => {
            if (entry?.isIntersecting) {
                start();

                return;
            }

            stop();
        });

        observer.observe(band);
        window.addEventListener('resize', measure);
        start();

        return () => {
            stop();
            observer.disconnect();
            window.removeEventListener('resize', measure);
        };
    }, []);

    const groupItems = (prefix: string): ReactNode[] =>
        Array.from({ length: PASSES_PER_GROUP }).flatMap((_, pass) =>
            items.map((item) => (
                <span
                    className="cubania-hero__marquee-item"
                    key={`${prefix}-${pass}-${item}`}
                >
                    {item}
                    <span className="cubania-hero__marquee-star">✳</span>
                </span>
            )),
        );

    return (
        <div className="cubania-hero__marquee" ref={bandRef} aria-hidden>
            <div className="cubania-hero__marquee-track" ref={trackRef}>
                <div className="cubania-hero__marquee-group" ref={groupRef}>
                    {groupItems('a')}
                </div>
                <div className="cubania-hero__marquee-group">
                    {groupItems('b')}
                </div>
            </div>
        </div>
    );
}
