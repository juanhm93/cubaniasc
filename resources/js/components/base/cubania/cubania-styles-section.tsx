import type { ReactNode } from 'react';
import { useState } from 'react';
import { CubaniaReveal } from '@/components/base/cubania/cubania-reveal';
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

type ActiveVideo = {
    title: string;
    embedUrl: string;
};

/**
 * “Lo que enseñamos” section with three style cards.
 */
export function CubaniaStylesSection(): ReactNode {
    const { t } = useTranslation();
    const [activeVideo, setActiveVideo] = useState<ActiveVideo | null>(null);

    const openVideo = (title: string, watchUrl: string): void => {
        setActiveVideo({
            title,
            embedUrl: youtubeWatchUrlToEmbedUrl(watchUrl),
        });
    };

    return (
        <section className="cubania-styles" id="estilos">
            <div className="cubania-styles__header">
                <div className="cubania-section-header__label">
                    {t('landing.styles.sectionLabel')}
                </div>
                <h2 className="cubania-section-header__title">
                    {t('landing.styles.sectionTitleLine1')}{' '}
                    <span className="cubania-section-header__title-accent">
                        {t('landing.styles.sectionTitleLine2')}
                    </span>
                </h2>
            </div>

            <div className="cubania-styles__grid">
                <CubaniaReveal delayMs={0}>
                    <CubaniaStyleCard
                        highlight
                        image="/cubania-assets/salsa-casino.webp"
                        name={t('landing.styles.salsaCasino.name')}
                        description={t('landing.styles.salsaCasino.description')}
                        onActivate={() =>
                            openVideo(
                                t('landing.styles.salsaCasino.name'),
                                SALSA_CASINO_VIDEO,
                            )
                        }
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={100}>
                    <CubaniaStyleCard
                        image="/cubania-assets/bachata.webp"
                        name={t('landing.styles.bachata.name')}
                        description={t('landing.styles.bachata.description')}
                        onActivate={() =>
                            openVideo(
                                t('landing.styles.bachata.name'),
                                BACHATA_VIDEO,
                            )
                        }
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={200}>
                    <CubaniaStyleCard
                        image="/cubania-assets/rueda-casino.webp"
                        name={t('landing.styles.rueda.name')}
                        description={t('landing.styles.rueda.description')}
                        onActivate={() =>
                            openVideo(
                                t('landing.styles.rueda.name'),
                                RUEDA_VIDEO,
                            )
                        }
                    />
                </CubaniaReveal>
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
                                <DialogTitle className="font-['Syne',sans-serif] text-lg text-[#f9f5ff]">
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
