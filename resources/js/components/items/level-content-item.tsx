import { GripVertical, Pencil, Play, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

const rowClassName =
    'flex items-center gap-3 rounded-[4px] border border-sidebar-border/70 bg-card px-3 py-3 shadow-sm dark:border-sidebar-border';

type LevelContentItemProps = {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    video_url: string | null;
};

type deletingIdProps = number | null;
type handleRemoveProps = (id: number) => void;
type setVideoContentProps = (levelContent: LevelContentItemProps) => void;
type onEditProps = (levelContent: LevelContentItemProps) => void;

export default function LevelContentItem({
    levelContent,
    deletingId,
    handleRemove,
    setVideoContent,
    onEdit,
}: {
    levelContent: LevelContentItemProps;
    deletingId: deletingIdProps;
    handleRemove: handleRemoveProps;
    setVideoContent: setVideoContentProps;
    onEdit: onEditProps;
}) {
    return (
        <li key={levelContent.id.toString()} className={rowClassName}>
            <span
                className="inline-flex shrink-0 text-muted-foreground"
                title="Reorder"
                aria-hidden
            >
                <GripVertical className="size-5" />
            </span>
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
                    aria-label={`Play ${levelContent.name}`}
                    onClick={() => setVideoContent(levelContent)}
                >
                    <Play className="size-5 fill-current" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-9 text-muted-foreground"
                    aria-label={`Edit ${levelContent.name}`}
                    onClick={() => onEdit(levelContent)}
                >
                    <Pencil className="size-5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-9 text-muted-foreground hover:text-destructive"
                    aria-label={`Remove ${levelContent.name}`}
                    disabled={deletingId === levelContent.id}
                    onClick={() => void handleRemove(levelContent.id)}
                >
                    <Trash2 className="size-5" />
                </Button>
            </div>
        </li>
    );
}
