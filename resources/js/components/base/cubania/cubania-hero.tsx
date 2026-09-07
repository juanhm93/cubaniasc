import type { ReactNode } from 'react';
import { CubaniaHeroVideo } from '@/components/base/cubania/cubania-hero-video';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { CubaniaPhotoCard } from '@/components/cards/photo-card';
import { useTranslation } from '@/i18n/use-translation';

const instructorCards = [
    {
        variant: 'a' as const,
        gradient:
            'linear-gradient(135deg, #3D1080 0%, #8B31F0 40%, #1A0540 100%)',
        tagKey: 'landing.hero.instructorA',
    },
    {
        variant: 'b' as const,
        gradient:
            'linear-gradient(135deg, #0D0A2E 0%, #2A1060 60%, #6B21C8 100%)',
        tagKey: 'landing.hero.instructorB',
    },
    {
        variant: 'c' as const,
        gradient:
            'linear-gradient(135deg, #1A0525 0%, #5E18C0 60%, #F5C842 100%)',
        tagKey: 'landing.hero.instructorC',
    },
] as const;

/**
 * Full-viewport hero: headline, CTAs, floating instructor cards, stats, scroll hint.
 */
export function CubaniaHero(): ReactNode {
    const { t } = useTranslation();
    const { hero, instructors } = useCubaniaConfig();

    return (
        <section className="cubania-hero" id="clases">
            <div className="cubania-hero__bg">
                {hero.youtubeId ? (
                    <CubaniaHeroVideo
                        videoId={hero.youtubeId}
                        playbackRate={hero.playbackRate}
                        showControls={false}
                    />
                ) : null}
                <div className="cubania-hero__overlay" />
                <div className="cubania-hero__orb cubania-hero__orb--1" />
                <div className="cubania-hero__orb cubania-hero__orb--2" />
                <div className="cubania-hero__orb cubania-hero__orb--3" />
            </div>
            <div className="cubania-hero__dots" aria-hidden />

            <div className="cubania-hero__content">
                <div className="cubania-hero__eyebrow">
                    {t('landing.hero.eyebrow')}
                </div>

                <h1 className="cubania-hero__title">
                    <span className="cubania-hero__title-outline">
                        {t('landing.hero.titleOutline')}
                    </span>
                    <span className="cubania-hero__title-accent">
                        {t('landing.hero.titleAccent')}
                    </span>
                </h1>

                <p className="cubania-hero__subtitle">
                    {t('landing.hero.subtitle')}
                </p>

                <div className="cubania-hero__actions">
                    <a
                        href="#inscripcion"
                        className="cubania-btn cubania-btn--primary"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.hero.startNow')}
                    </a>
                    <a
                        href="#estilos"
                        className="cubania-btn cubania-btn--secondary"
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.hero.viewClasses')}
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden
                        >
                            <path
                                d="M3 8H13M13 8L9 4M13 8L9 12"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </a>
                </div>
                <div className="cubania-hero__stats mt-6">
                    <div className="cubania-stat">
                        <span className="cubania-stat__num">+2100</span>
                        <span className="cubania-stat__label">
                            {t('landing.hero.followers')}
                        </span>
                    </div>
                    <div className="cubania-stat__divider" aria-hidden />
                    <div className="cubania-stat">
                        <span className="cubania-stat__num">+9</span>
                        <span className="cubania-stat__label">
                            {t('landing.hero.yearsPassion')}
                        </span>
                    </div>
                    <div className="cubania-stat__divider" aria-hidden />
                    <div className="cubania-stat">
                        <span className="cubania-stat__num">100%</span>
                        <span className="cubania-stat__label">
                            {t('landing.hero.hashtag')}
                        </span>
                    </div>
                </div>
            </div>

            <div className="cubania-hero__panel" aria-hidden>
                <div className="cubania-hero__panel-inner">
                    {instructorCards.map((card, index) => {
                        const image = instructors[index]?.image;

                        if (!image) {
                            return null;
                        }

                        return (
                            <CubaniaPhotoCard
                                key={card.variant}
                                variant={card.variant}
                                gradient={card.gradient}
                                image={image}
                                tag={t(card.tagKey)}
                            />
                        );
                    })}
                </div>
            </div>

            <div className="cubania-scroll-hint">
                <div className="cubania-scroll-hint__line" />
                {t('landing.hero.scroll')}
            </div>
        </section>
    );
}
