import type { ReactNode } from 'react';
import { CubaniaImageSlider } from '@/components/base/cubania/cubania-image-slider';
import { CubaniaReveal } from '@/components/base/cubania/cubania-reveal';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { useTranslation } from '@/i18n/use-translation';

/**
 * Purple slanted “Nosotros” band with copy on the left and a reusable image slider.
 */
export function CubaniaAboutSection(): ReactNode {
    const { t } = useTranslation();
    const { sliderImages } = useCubaniaConfig();

    return (
        <section
            className="cubania-about"
            id="nosotros"
            aria-label={t('landing.page.aboutAriaLabel')}
        >
            <div className="cubania-about__band">
                <div className="cubania-about__inner">
                    <CubaniaReveal className="cubania-about__copy">
                        <span className="cubania-about__eyebrow">
                            {t('landing.about.eyebrow')}
                        </span>
                        <h2 className="cubania-about__title">
                            {t('landing.about.title')}
                        </h2>
                        <p className="cubania-about__body">
                            {t('landing.about.body')}
                        </p>
                        <a
                            href="#inscripcion"
                            className="cubania-btn cubania-btn--primary cubania-about__cta"
                            data-cubania-cursor="interactive"
                        >
                            {t('landing.about.cta')}
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
                    </CubaniaReveal>

                    <CubaniaReveal
                        delayMs={120}
                        className="cubania-about__media"
                    >
                        <CubaniaImageSlider
                            images={sliderImages}
                            alt={t('landing.about.sliderAlt')}
                            ariaLabel={t('landing.about.sliderAriaLabel')}
                            slideAriaLabel={(current, total) =>
                                t('landing.about.slideLabel', {
                                    current,
                                    total,
                                })
                            }
                        />
                    </CubaniaReveal>
                </div>
            </div>
        </section>
    );
}
