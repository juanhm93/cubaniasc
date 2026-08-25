import { router } from '@inertiajs/react';
import { ArrowRight, GripVertical, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/i18n/use-translation';
import { show as levelShow } from '@/routes/levels';

type LevelItemProps = {
    id: number;
    name: string;
    description: string | null;
    dance_type: DanceType;
};

type DanceType = {
    id: number;
    name: string;
};

export default function LevelItem({ level }: { level: LevelItemProps }) {
    const { t } = useTranslation();

    return (
        <div
            key={level.id.toString()}
            className="flex items-center gap-3 rounded-[4px] border border-sidebar-border/70 bg-card px-3 py-3 shadow-sm dark:border-sidebar-border"
        >
            <GripVertical
                className="size-5 shrink-0 text-muted-foreground"
                aria-hidden
            />
            <div className="min-w-0 flex-1">
                <h2 className="truncate font-semibold">{level.name}</h2>
                <p className="text-sm text-muted-foreground">
                    {level.dance_type.name}
                </p>
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
                    aria-label={t('levels.item.openLevel', { name: level.name })}
                    onClick={() => router.visit(levelShow.url(level.id))}
                >
                    <ArrowRight className="size-5" />
                </Button>
                <Trash2 className="size-5 text-muted-foreground" aria-hidden />
            </div>
        </div>
    );
}
