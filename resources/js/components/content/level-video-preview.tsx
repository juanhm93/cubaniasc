import { Download } from 'lucide-react';
import FigureFileVideo from '@/components/content/figure-file-video';
import VideoSourceSwitch from '@/components/content/video-source-switch';
import { Button } from '@/components/ui/button';
import { usePreferredVideoSource } from '@/hooks/use-preferred-video-source';
import { useTranslation } from '@/i18n/use-translation';
import { figureVideoDownloadUrl } from '@/services/figureVideoService';
import type { FigureItem } from '@/types/content';

/**
 * Plays a figure's video. A figure can have a YouTube link, its own MP4 or both;
 * with both, a switch picks the source (YouTube first) and neither is hidden.
 */
export default function LevelVideoPreview({ figure }: { figure: FigureItem }) {
    const { t } = useTranslation();
    const linkUrl = figure.video_url?.trim() ?? '';
    const hasFile = figure.video_file.available;
    const [source, setSource] = usePreferredVideoSource(
        linkUrl !== '',
        hasFile,
    );

    if (source === null) {
        return (
            <div
                className="flex aspect-video items-center justify-center rounded-md border bg-muted px-4 text-center text-sm text-muted-foreground"
                role="status"
            >
                {t('content.figureVideo.player.noVideo')}
            </div>
        );
    }

    return (
        <div className="grid gap-3">
            {linkUrl !== '' && hasFile ? (
                <VideoSourceSwitch value={source} onChange={setSource} />
            ) : null}

            {source === 'file' ? (
                <FigureFileVideo
                    figureId={figure.id}
                    version={figure.video_file.version}
                />
            ) : (
                <LinkedVideo url={linkUrl} />
            )}

            {hasFile ? (
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="justify-self-start"
                >
                    <a href={figureVideoDownloadUrl(figure.id)}>
                        <Download className="size-4" aria-hidden />
                        {t('content.figureVideo.download')}
                    </a>
                </Button>
            ) : null}
        </div>
    );
}

function LinkedVideo({ url }: { url: string }) {
    const { t } = useTranslation();
    const ytMatch = url.match(
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
    );

    if (ytMatch) {
        return (
            <iframe
                title={t('content.figureVideo.player.youtubeTitle')}
                className="aspect-video w-full rounded-md border-0"
                src={`https://www.youtube.com/embed/${ytMatch[1]}`}
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowFullScreen
            />
        );
    }

    return (
        <video
            className="aspect-video w-full rounded-md bg-black"
            controls
            playsInline
            src={url}
        />
    );
}
