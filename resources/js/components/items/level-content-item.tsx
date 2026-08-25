import { Pencil, Play, Trash2 } from 'lucide-react';
import {
    SortableHandle,
    type SortableHandleProps,
} from '@/components/content/sortable-list';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';

const rowClassName =
    'flex items-center gap-3 rounded-[4px] border border-sidebar-border/70 bg-card px-3 py-3 shadow-sm dark:border-sidebar-border';

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
}: {
    levelContent: LevelContentItemProps;
    deletingId: number | null;
    onDelete?: (levelContent: LevelContentItemProps) => void;
    setVideoContent: (levelContent: LevelContentItemProps) => void;
    onEdit: (levelContent: LevelContentItemProps) => void;
    handleProps: SortableHandleProps;
    isDragging?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <div
            data-sortable-id={levelContent.id}
            className={cn(rowClassName, isDragging && 'opacity-70')}
        >
            <SortableHandle
                label={`Reordenar ${levelContent.name}`}
                {...handleProps}
            />
            <div className="min-w-0 flex-1">
                <h2 className="truncate font-semibold">{levelContent.name}</h2>
                {levelContent.description ? (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {levelContent.description}
                    </p>
                ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-9 text-muted-foreground"
                    aria-label={t('levels.item.playContent', {
                        name: levelContent.name,
                    })}
                    onClick={() => setVideoContent(levelContent)}
                >
                    <Play className="size-5 fill-current" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-9 text-muted-foreground"
                    aria-label={t('levels.item.editContent', {
                        name: levelContent.name,
                    })}
                    onClick={() => onEdit(levelContent)}
                >
                    <Pencil className="size-5" />
                </Button>
                {onDelete ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-9 text-muted-foreground hover:text-destructive"
                        aria-label={`Eliminar ${levelContent.name}`}
                        disabled={deletingId === levelContent.id}
                        onClick={() => onDelete(levelContent)}
                    >
                        <Trash2 className="size-5" />
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
