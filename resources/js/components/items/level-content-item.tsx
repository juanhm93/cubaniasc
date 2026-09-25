import { Pencil, Play, Trash2 } from 'lucide-react';
import { ContentHelpBadge } from '@/components/content/content-help';
import {
    SortableHandle,
    type SortableHandleProps,
} from '@/components/content/sortable-list';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/i18n/use-translation';
import {
    figureMissingDescription,
    figureMissingVideo,
} from '@/lib/content-help';
import { cn } from '@/lib/utils';

const rowClassName =
    'flex flex-col gap-3 rounded-[4px] border border-sidebar-border/70 bg-card px-3 py-3 shadow-sm sm:flex-row sm:items-center dark:border-sidebar-border';

const actionClassName =
    'h-11 flex-1 gap-2 text-muted-foreground sm:size-9 sm:flex-none sm:gap-0 sm:px-0 sm:has-[>svg]:px-0';

type LevelContentItemProps = {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    video_url: string | null;
};

export default function LevelContentItem({
    levelContent,
    deletingId,
    onDelete,
    setVideoContent,
    onEdit,
    handleProps,
    isDragging = false,
    showContentHelp = false,
}: {
    levelContent: LevelContentItemProps;
    deletingId: number | null;
    onDelete?: (levelContent: LevelContentItemProps) => void;
    setVideoContent: (levelContent: LevelContentItemProps) => void;
    onEdit: (levelContent: LevelContentItemProps) => void;
    handleProps: SortableHandleProps;
    isDragging?: boolean;
    showContentHelp?: boolean;
}) {
    const { t } = useTranslation();
    const missingVideo = showContentHelp && figureMissingVideo(levelContent);
    const missingDescription =
        showContentHelp && figureMissingDescription(levelContent);

    return (
        <div
            data-sortable-id={levelContent.id}
            className={cn(rowClassName, isDragging && 'opacity-70')}
        >
            <div className="flex min-w-0 flex-1 items-start gap-3 sm:items-center">
                <SortableHandle
                    label={`Reordenar ${levelContent.name}`}
                    {...handleProps}
                />
                <div className="min-w-0 flex-1">
                    <h2 className="leading-snug font-semibold break-words">
                        {levelContent.name}
                    </h2>
                    {levelContent.description ? (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {levelContent.description}
                        </p>
                    ) : null}
                    {missingVideo || missingDescription ? (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {missingVideo ? (
                                <ContentHelpBadge>
                                    {t('content.help.missingVideo')}
                                </ContentHelpBadge>
                            ) : null}
                            {missingDescription ? (
                                <ContentHelpBadge>
                                    {t('content.help.missingDescription')}
                                </ContentHelpBadge>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
            <div className="flex items-center gap-2 border-t border-sidebar-border/70 pt-3 sm:shrink-0 sm:gap-1 sm:border-0 sm:pt-0 dark:border-sidebar-border">
                <Button
                    type="button"
                    variant="ghost"
                    className={actionClassName}
                    aria-label={t('levels.item.playContent', {
                        name: levelContent.name,
                    })}
                    onClick={() => setVideoContent(levelContent)}
                >
                    <Play className="size-5 fill-current" />
                    <span className="sm:sr-only">{t('levels.item.play')}</span>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    className={actionClassName}
                    aria-label={t('levels.item.editContent', {
                        name: levelContent.name,
                    })}
                    onClick={() => onEdit(levelContent)}
                >
                    <Pencil className="size-5" />
                    <span className="sm:sr-only">{t('levels.item.edit')}</span>
                </Button>
                {onDelete ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn(
                            actionClassName,
                            'hover:text-destructive',
                        )}
                        aria-label={t('levels.item.removeContent', {
                            name: levelContent.name,
                        })}
                        disabled={deletingId === levelContent.id}
                        onClick={() => onDelete(levelContent)}
                    >
                        <Trash2 className="size-5" />
                        <span className="sm:sr-only">
                            {t('levels.item.remove')}
                        </span>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
