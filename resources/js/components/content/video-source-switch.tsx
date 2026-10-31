import { Film, Youtube } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { VideoSource } from '@/hooks/use-preferred-video-source';
import { useTranslation } from '@/i18n/use-translation';

/**
 * Lets the viewer pick between the YouTube link and the Cubanía MP4 when a
 * figure has both. Render it only in that case.
 */
export default function VideoSourceSwitch({
    value,
    onChange,
}: {
    value: VideoSource;
    onChange: (source: VideoSource) => void;
}) {
    const { t } = useTranslation();

    return (
        <ToggleGroup
            type="single"
            value={value}
            onValueChange={(next) => {
                if (next === 'youtube' || next === 'file') {
                    onChange(next);
                }
            }}
            variant="outline"
            size="sm"
            className="w-full"
            aria-label={t('content.figureVideo.source.label')}
        >
            <ToggleGroupItem value="youtube" className="h-9 flex-1">
                <Youtube className="size-4" aria-hidden />
                {t('content.figureVideo.source.youtube')}
            </ToggleGroupItem>
            <ToggleGroupItem value="file" className="h-9 flex-1">
                <Film className="size-4" aria-hidden />
                {t('content.figureVideo.source.file')}
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
