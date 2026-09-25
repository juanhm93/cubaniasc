import type { KeyboardEvent, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { CubaniaReveal } from '@/components/base/cubania/cubania-reveal';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { youtubeWatchUrlToEmbedUrl } from '@/components/base/cubania/youtube-embed-url';
import { CubaniaStyleCard } from '@/components/cards/style-card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/i18n/use-translation';

const SALSA_CASINO_VIDEO = 'https://www.youtube.com/watch?v=s4DT0BFxDEk';
const BACHATA_VIDEO = 'https://www.youtube.com/watch?v=2Fdz_9UI3Oo';
const RUEDA_VIDEO = 'https://www.youtube.com/watch?v=3FZ3_lzhbHI';

const TABS = ['styles', 'teachers'] as const;

type ShowcaseTab = (typeof TABS)[number];

type SwitchDirection = 'forward' | 'backward';

const styleCards = [
    {
        key: 'salsaCasino',
        image: '/cubania-assets/salsa-casino.webp',
        video: SALSA_CASINO_VIDEO,
        highlight: true,
    },
    {
        key: 'bachata',
        image: '/cubania-assets/bachata.webp',
        video: BACHATA_VIDEO,
        highlight: false,
    },
    {
        key: 'rueda',
        image: '/cubania-assets/rueda-casino.webp',
        video: RUEDA_VIDEO,
        highlight: false,
    },
] as const;

const teacherKeys = ['juan', 'mare', 'javier'] as const;

/** Delay between cards entering, in ms. */
const CARD_STAGGER_MS = 90;

const PANEL_ID = 'cubania-styles-panel';

type ActiveVideo = {
    title: string;
    embedUrl: string;
};

type ShowcaseCard = {
    key: string;
    node: ReactNode;
};

/**
 * “Lo que enseñamos” section: one grid that switches between the dance styles
 * and the teachers through a pill toggle.
 */
export function CubaniaStylesSection(): ReactNode {
    const { t } = useTranslation();
    const { instructors } = useCubaniaConfig();
    const [activeTab, setActiveTab] = useState<ShowcaseTab>('styles');
    const [direction, setDirection] = useState<SwitchDirection | null>(null);
    const [activeVideo, setActiveVideo] = useState<ActiveVideo | null>(null);
    const tabRefs = useRef<Record<ShowcaseTab, HTMLButtonElement | null>>({
        styles: null,
        teachers: null,
    });

    const openVideo = (title: string, watchUrl: string): void => {
        setActiveVideo({
            title,
            embedUrl: youtubeWatchUrlToEmbedUrl(watchUrl),
        });
    };

    const selectTab = (tab: ShowcaseTab): void => {
        if (tab === activeTab) {
            return;
        }

        setDirection(
            TABS.indexOf(tab) > TABS.indexOf(activeTab)
                ? 'forward'
                : 'backward',
        );
        setActiveTab(tab);
    };

    const onSwitchKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        const currentIndex = TABS.indexOf(activeTab);
        const nextIndexByKey: Record<string, number> = {
            ArrowLeft: (currentIndex - 1 + TABS.length) % TABS.length,
            ArrowRight: (currentIndex + 1) % TABS.length,
            Home: 0,
            End: TABS.length - 1,
        };
        const nextIndex = nextIndexByKey[event.key];

        if (nextIndex === undefined) {
            return;
        }

        event.preventDefault();

        const nextTab = TABS[nextIndex] ?? 'styles';
        selectTab(nextTab);
        tabRefs.current[nextTab]?.focus();
    };

    const cards: ShowcaseCard[] =
        activeTab === 'styles'
            ? styleCards.map((card) => {
                  const name = t(`landing.styles.${card.key}.name`);

                  return {
                      key: card.key,
                      node: (
                          <CubaniaStyleCard
                              highlight={card.highlight}
                              image={card.image}
                              name={name}
                              description={t(
                                  `landing.styles.${card.key}.description`,
                              )}
                              onActivate={() => openVideo(name, card.video)}
                          />
                      ),
                  };
              })
            : teacherKeys.flatMap((key, index) => {
                  const image = instructors[index]?.image;

                  if (!image) {
                      return [];
                  }

                  return [
                      {
                          key,
                          node: (
                              <CubaniaStyleCard
                                  portrait
                                  image={image}
                                  name={t(`landing.instructors.${key}.name`)}
                                  description={t(
                                      `landing.instructors.${key}.role`,
                                  )}
                              />
                          ),
                      },
                  ];
              });

    const headingKeys =
        activeTab === 'styles'
            ? {
                  label: 'landing.styles.sectionLabel',
                  line1: 'landing.styles.sectionTitleLine1',
                  line2: 'landing.styles.sectionTitleLine2',
              }
            : {
                  label: 'landing.instructors.sectionLabel',
                  line1: 'landing.instructors.sectionTitleLine1',
                  line2: 'landing.instructors.sectionTitleLine2',
              };

    return (
        <section className="cubania-styles" id="estilos">
            <div className="cubania-styles__header">
                <div
                    key={activeTab}
                    className={[
                        'cubania-styles__heading',
                        direction ? 'cubania-styles__heading--swap' : '',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                >
                    <div className="cubania-section-header__label">
                        {t(headingKeys.label)}
                    </div>
                    <h2 className="cubania-section-header__title">
                        {t(headingKeys.line1)}{' '}
                        <span className="cubania-section-header__title-accent">
                            {t(headingKeys.line2)}
                        </span>
                    </h2>
                </div>

                <div
                    className="cubania-styles__switch"
                    role="tablist"
                    aria-label={t('landing.styles.tabsAriaLabel')}
                    data-active={activeTab}
                    onKeyDown={onSwitchKeyDown}
                >
                    <span
                        className="cubania-styles__switch-thumb"
                        aria-hidden
                    />
                    {TABS.map((tab) => (
                        <button
                            key={tab}
                            ref={(element) => {
                                tabRefs.current[tab] = element;
                            }}
                            type="button"
                            role="tab"
                            id={`cubania-styles-tab-${tab}`}
                            aria-selected={activeTab === tab}
                            aria-controls={PANEL_ID}
                            tabIndex={activeTab === tab ? 0 : -1}
                            className="cubania-styles__switch-option"
                            onClick={() => selectTab(tab)}
                            data-cubania-cursor="interactive"
                        >
                            {t(`landing.styles.tabs.${tab}`)}
                        </button>
                    ))}
                </div>
            </div>

            <div
                key={activeTab}
                className="cubania-styles__grid"
                id={PANEL_ID}
                role="tabpanel"
                aria-labelledby={`cubania-styles-tab-${activeTab}`}
                data-direction={direction ?? undefined}
            >
                {cards.map((card, index) => (
                    <CubaniaReveal
                        key={card.key}
                        delayMs={
                            (direction === 'backward'
                                ? cards.length - 1 - index
                                : index) * CARD_STAGGER_MS
                        }
                    >
                        {card.node}
                    </CubaniaReveal>
                ))}
            </div>

            <Dialog
                open={activeVideo !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActiveVideo(null);
                    }
                }}
            >
                <DialogContent className="max-w-[min(100vw-2rem,56rem)] border-white/10 bg-[#1a1025] p-0 text-[#f9f5ff]">
                    {activeVideo ? (
                        <>
                            <DialogHeader className="px-6 pt-6 pb-0">
                                <DialogTitle className="font-['Archivo',sans-serif] text-lg text-[#f9f5ff]">
                                    {activeVideo.title}
                                </DialogTitle>
                            </DialogHeader>
                            <div className="p-6 pt-4">
                                <div
                                    className="relative w-full overflow-hidden rounded-sm bg-black pt-[56.25%]"
                                    key={activeVideo.embedUrl}
                                >
                                    <iframe
                                        className="absolute inset-0 h-full w-full"
                                        src={`${activeVideo.embedUrl}?rel=0`}
                                        title={activeVideo.title}
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowFullScreen
                                        loading="lazy"
                                    />
                                </div>
                            </div>
                        </>
                    ) : null}
                </DialogContent>
            </Dialog>
        </section>
    );
}
