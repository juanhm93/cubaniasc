import { router } from '@inertiajs/react';
import { ArrowRight, Trash2 } from 'lucide-react';
import {
    SortableHandle,
    type SortableHandleProps,
} from '@/components/content/sortable-list';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show as contentLevelShow } from '@/routes/content/levels';

type LevelItemProps = {
    id: number;
    name: string;
    description: string | null;
    figuresCount?: number;
};

export default function LevelItem({
    level,
    danceTypeId,
    deletingId,
    onDelete,
    handleProps,
    isDragging = false,
}: {
    level: LevelItemProps;
    danceTypeId: number;
    deletingId?: number | null;
    onDelete?: (level: LevelItemProps) => void;
    handleProps: SortableHandleProps;
    isDragging?: boolean;
}) {
    const figuresLabel =
        level.figuresCount === undefined
            ? null
            : `${level.figuresCount} figura${level.figuresCount === 1 ? '' : 's'}`;

    return (
        <div
            data-sortable-id={level.id}
            className={cn(
                'flex items-center gap-3 rounded-[4px] border border-sidebar-border/70 bg-card px-3 py-3 shadow-sm dark:border-sidebar-border',
                isDragging && 'opacity-70',
            )}
        >
            <SortableHandle
                label={`Reordenar ${level.name}`}
                {...handleProps}
            />
            <div className="min-w-0 flex-1">
                <h2 className="truncate font-semibold">{level.name}</h2>
                {figuresLabel ? (
                    <p className="text-sm text-muted-foreground">
                        {figuresLabel}
                    </p>
                ) : null}
                {level.description ? (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {level.description}
                    </p>
                ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-9 shrink-0 text-muted-foreground"
                    aria-label={`Abrir ${level.name}`}
                    onClick={() =>
                        router.visit(
                            contentLevelShow.url({
                                danceType: danceTypeId,
                                level: level.id,
                            }),
                        )
                    }
                >
                    <ArrowRight className="size-5" />
                </Button>
                {onDelete ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                        aria-label={`Eliminar ${level.name}`}
                        disabled={deletingId === level.id}
                        onClick={() => onDelete(level)}
                    >
                        <Trash2 className="size-5" />
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
