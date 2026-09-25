import { useState } from 'react';
import type { ReactNode } from 'react';
import { CubaniaHeroMarquee } from '@/components/base/cubania/cubania-hero-marquee';
import { CubaniaHeroTitle } from '@/components/base/cubania/cubania-hero-title';
import { CubaniaHeroVideo } from '@/components/base/cubania/cubania-hero-video';
import { CubaniaNextClassCard } from '@/components/base/cubania/cubania-next-class-card';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { useTranslation } from '@/i18n/use-translation';

/**
 * “La Pista”: full-bleed class footage, solid headline, live marquee of styles
 * and the next real class on the timetable.
 */
export function CubaniaHero(): ReactNode {
    const { t } = useTranslation();
    const { hero } = useCubaniaConfig();
    const [reelPaused, setReelPaused] = useState(false);

    return (
        <section className="cubania-hero" id="clases">
            <div className="cubania-hero__bg" aria-hidden>
                {hero.youtubeId ? (
                    <CubaniaHeroVideo
                        videoId={hero.youtubeId}
                        playbackRate={hero.playbackRate}
                        showControls={false}
                        paused={reelPaused}
                    />
                ) : null}
                <div className="cubania-hero__scrim" />
                <div className="cubania-hero__grain" />
            </div>

            <div className="cubania-hero__stage">
                <div className="cubania-hero__main">
                    <p className="cubania-hero__eyebrow">
                        <span className="cubania-hero__live" aria-hidden />
                        {t('landing.hero.eyebrow')}
                    </p>

                    <CubaniaHeroTitle
                        lead={t('landing.hero.titleLead')}
                        accent={t('landing.hero.titleAccent')}
                    />

                    <p className="cubania-hero__subtitle">
                        {t('landing.hero.subtitle')}{' '}
                        <strong className="cubania-hero__subtitle-strong">
                            {t('landing.hero.subtitleHighlight')}
                        </strong>
                    </p>
                </div>

                <div className="cubania-hero__aside">
                    {hero.youtubeId ? (
                        <button
                            type="button"
                            className="cubania-hero__reel"
                            onClick={() => setReelPaused((paused) => !paused)}
                            aria-pressed={reelPaused}
                            data-cubania-cursor="interactive"
                        >
                            <span
                                className={[
                                    'cubania-hero__reel-disc',
                                    reelPaused
                                        ? ''
                                        : 'cubania-hero__reel-disc--playing',
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                            >
                                <span
                                    className="cubania-hero__reel-ring"
                                    aria-hidden
                                />
                                {reelPaused ? (
                                    <svg
                                        width="18"
                                        height="20"
                                        viewBox="0 0 18 20"
                                        aria-hidden
                                    >
                                        <path
                                            d="M2 1.8 16 10 2 18.2Z"
                                            fill="currentColor"
                                            stroke="currentColor"
                                            strokeWidth="2.5"
                                            strokeLinejoin="round"
                                        />
                                    </svg>
                                ) : (
                                    <svg
                                        width="18"
                                        height="20"
                                        viewBox="0 0 18 20"
                                        aria-hidden
                                    >
                                        <rect
                                            x="2.5"
                                            y="2"
                                            width="4.5"
                                            height="16"
                                            rx="1"
                                            fill="currentColor"
                                        />
                                        <rect
                                            x="11"
                                            y="2"
                                            width="4.5"
                                            height="16"
                                            rx="1"
                                            fill="currentColor"
                                        />
                                    </svg>
                                )}
                            </span>
                            <span className="cubania-hero__reel-copy">
                                <span className="cubania-hero__reel-label">
                                    {reelPaused
                                        ? t('landing.hero.reelLabel')
                                        : t('landing.hero.reelPauseLabel')}
                                </span>
                                <span className="cubania-hero__reel-hint">
                                    {reelPaused
                                        ? t('landing.hero.reelHint')
                                        : t('landing.hero.reelPauseHint')}
                                </span>
                            </span>
                        </button>
                    ) : null}

                    <CubaniaNextClassCard />
                </div>
            </div>

            <CubaniaHeroMarquee />
        </section>
    );
}
