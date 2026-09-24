import { FileText, Video } from 'lucide-react';
import { useState } from 'react';
import LevelVideoPreview from '@/components/content/level-video-preview';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/i18n/use-translation';
import { figureMissingDescription } from '@/lib/content-help';
import type { FigureItem } from '@/types/content';

type PreviewTab = 'video' | 'description';

export default function FigurePreviewDialog({
    figure,
    onClose,
}: {
    figure: FigureItem | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog
            open={figure !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="pr-6 break-words">
                        {figure?.name ??
                            t('content.figurePreview.fallbackTitle')}
                    </DialogTitle>
                </DialogHeader>
                {figure ? (
                    <FigurePreviewBody key={figure.id} figure={figure} />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function FigurePreviewBody({ figure }: { figure: FigureItem }) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<PreviewTab>('video');

    return (
        <div className="grid gap-4">
            <ToggleGroup
                type="single"
                value={tab}
                onValueChange={(value) => {
                    if (value === 'video' || value === 'description') {
                        setTab(value);
                    }
                }}
                variant="outline"
                className="w-full"
                aria-label={t('content.figurePreview.switchLabel')}
            >
                <ToggleGroupItem value="video" className="h-10 flex-1">
                    <Video className="size-4" aria-hidden />
                    {t('content.figurePreview.video')}
                </ToggleGroupItem>
                <ToggleGroupItem value="description" className="h-10 flex-1">
                    <FileText className="size-4" aria-hidden />
                    {t('content.figurePreview.description')}
                </ToggleGroupItem>
            </ToggleGroup>

            {tab === 'video' ? (
                <LevelVideoPreview url={figure.video_url} />
            ) : figureMissingDescription(figure) ? (
                <div
                    className="flex aspect-video items-center justify-center rounded-md border bg-muted px-4 text-center text-sm text-muted-foreground"
                    role="status"
                >
                    {t('content.figurePreview.noDescription')}
                </div>
            ) : (
                <div className="max-h-[60vh] min-h-40 overflow-y-auto rounded-md border bg-muted/40 px-4 py-3 text-base leading-relaxed break-words whitespace-pre-line">
                    {figure.description}
                </div>
            )}
        </div>
    );
}
