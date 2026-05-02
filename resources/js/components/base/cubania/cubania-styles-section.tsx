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
    const [activeVideo, setActiveVideo] = useState<ActiveVideo | null>(null);

    const openVideo = (title: string, watchUrl: string): void => {
        setActiveVideo({
            title,
            embedUrl: youtubeWatchUrlToEmbedUrl(watchUrl),
        });
    };

    return (
        <section className="cubania-styles" id="estilos">
            <div className="cubania-section-header__label">Lo que enseñamos</div>
            <h2 className="cubania-section-header__title">
                Nuestros
                <br />
                estilos
            </h2>

            <div className="cubania-styles__grid">
                <CubaniaReveal delayMs={0}>
                    <CubaniaStyleCard
                        highlight
                        icon="💃"
                        name="Salsa Casino"
                        description="El corazón cubano. Ritmo, conexión y arte en cada vuelta."
                        onActivate={() =>
                            openVideo('Salsa Casino', SALSA_CASINO_VIDEO)
                        }
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={100}>
                    <CubaniaStyleCard
                        bgGradient="linear-gradient(135deg, #1A0330 0%, #350A6A 100%)"
                        icon="🕺"
                        name="Bachata"
                        description="Sensualidad y melodía. El lenguaje universal del cuerpo."
                        onActivate={() => openVideo('Bachata', BACHATA_VIDEO)}
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={200}>
                    <CubaniaStyleCard
                        bgGradient="linear-gradient(135deg, #0A001A 0%, #250860 100%)"
                        icon="⭕"
                        name="Rueda de Casino"
                        description="Sincronía grupal. La magia de bailar en comunidad."
                        onActivate={() =>
                            openVideo('Rueda de Casino', RUEDA_VIDEO)
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
