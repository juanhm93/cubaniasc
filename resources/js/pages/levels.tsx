import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import LevelItem from '@/components/items/level-item';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';
import { createLevel, getLevels } from '@/services/levelService';
import { levels as levelsIndexRoute } from '@/routes';

type LevelItem = {
    id: number;
    name: string;
    description: string | null;
    dance_type: DanceType;
};

type DanceType = {
    id: number;
    name: string;
};

function mapValidationErrors(data: unknown): Record<string, string> | null {
    if (!data || typeof data !== 'object' || !('errors' in data)) {
        return null;
    }

    const raw = (data as { errors?: Record<string, string[]> }).errors;

    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const out: Record<string, string> = {};

    for (const [key, messages] of Object.entries(raw)) {
        if (Array.isArray(messages) && messages[0]) {
            out[key] = messages[0];
        }
    }

    return Object.keys(out).length ? out : null;
}

export default function Levels({
    danceTypes,
    levels,
}: {
    danceTypes: DanceType[];
    levels: LevelItem[];
}) {
    const { t } = useTranslation();
    const [levelItems, setLevelItems] = useState<LevelItem[]>(() => levels);
    const [addOpen, setAddOpen] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [filterDanceTypeId, setFilterDanceTypeId] = useState<
        number | undefined
    >(undefined);

    async function refreshLevels(): Promise<void> {
        try {
            const data = await getLevels();
            setLevelItems(Array.isArray(data) ? data : []);
        } catch {
            setLevelItems([]);
        }
    }

    useEffect(() => {
        let cancelled = false;

        getLevels()
            .then((data) => {
                if (!cancelled) {
                    setLevelItems(Array.isArray(data) ? data : []);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLevelItems([]);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const displayLevels = useMemo(() => {
        if (filterDanceTypeId == null) {
            return levelItems;
        }

        return levelItems.filter(
            (level) => level.dance_type.id === filterDanceTypeId,
        );
    }, [levelItems, filterDanceTypeId]);

    async function handleCreateLevel(e: React.FormEvent) {
        e.preventDefault();
        setFormErrors({});
        setSubmitting(true);

        try {
            await createLevel({
                name,
                description: description || null,
            });
            toast.success(t('levels.index.levelCreated'));
            setAddOpen(false);
            await refreshLevels();
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const mapped = mapValidationErrors(error.response.data);

                if (mapped) {
                    setFormErrors(mapped);
                } else {
                    toast.error(t('levels.index.couldNotCreate'));
                }
            } else {
                toast.error(t('levels.index.couldNotCreate'));
            }
        } finally {
            setSubmitting(false);
        }
    }

    function handleFilterDanceType(e: React.ChangeEvent<HTMLSelectElement>) {
        setFilterDanceTypeId(
            e.target.value ? Number(e.target.value) : undefined,
        );
    }

    return (
        <>
            <Head title={t('levels.index.headTitle')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <p className="text-sm text-muted-foreground">
                    {t('levels.index.loadedCount', { count: levelItems.length })}
                </p>
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-2 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="grid gap-1.5">
                        <p>{t('levels.index.danceTypes')}</p>
                        <select
                            id="filter-dance-type"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            value={
                                filterDanceTypeId != null
                                    ? String(filterDanceTypeId)
                                    : ''
                            }
                            onChange={handleFilterDanceType}
                        >
                            <option value="">
                                {t('levels.index.allDanceTypes')}
                            </option>
                            {danceTypes.map((danceType) => (
                                <option key={danceType.id} value={danceType.id}>
                                    {danceType.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Dialog
                        open={addOpen}
                        onOpenChange={(open) => {
                            setAddOpen(open);

                            if (open) {
                                setName('');
                                setDescription('');
                                setFormErrors({});
                            }
                        }}
                    >
                        <DialogTrigger asChild>
                            <button
                                type="button"
                                className={cn(
                                    'flex min-h-[72px] w-full items-center justify-center rounded-[4px] border border-dashed border-sidebar-border/70 bg-card/50 py-6 shadow-sm transition-colors',
                                    'hover:border-sidebar-border hover:bg-accent/30 dark:border-sidebar-border',
                                )}
                            >
                                <Plus
                                    className="size-8 text-muted-foreground"
                                    strokeWidth={1.5}
                                    aria-hidden
                                />
                                <span className="sr-only">
                                    {t('levels.index.addLevelSr')}
                                </span>
                            </button>
                        </DialogTrigger>
                        <DialogContent>
                            <form onSubmit={handleCreateLevel}>
                                <DialogHeader>
                                    <DialogTitle>
                                        {t('levels.index.newLevel')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('levels.index.newLevelDescription')}
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4 py-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="level-name">
                                            {t('common.name')}
                                        </Label>
                                        <Input
                                            id="level-name"
                                            name="name"
                                            value={name}
                                            onChange={(ev) =>
                                                setName(ev.target.value)
                                            }
                                            placeholder={t(
                                                'levels.index.namePlaceholder',
                                            )}
                                            required
                                            autoComplete="off"
                                            disabled={submitting}
                                            maxLength={120}
                                            aria-invalid={
                                                formErrors.name
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        <InputError message={formErrors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="level-description">
                                            {t('common.descriptionOptional')}
                                        </Label>
                                        <textarea
                                            id="level-description"
                                            name="description"
                                            value={description}
                                            onChange={(ev) =>
                                                setDescription(ev.target.value)
                                            }
                                            placeholder={t(
                                                'levels.index.descriptionPlaceholder',
                                            )}
                                            rows={3}
                                            disabled={submitting}
                                            maxLength={500}
                                            className={cn(
                                                'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                'disabled:cursor-not-allowed disabled:opacity-50',
                                                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                                            )}
                                            aria-invalid={
                                                formErrors.description
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            message={formErrors.description}
                                        />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={submitting}
                                        onClick={() => setAddOpen(false)}
                                    >
                                        {t('common.cancel')}
                                    </Button>
                                    <Button type="submit" disabled={submitting}>
                                        {submitting
                                            ? t('common.saving')
                                            : t('common.create')}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>

                    {displayLevels.map((level) => (
                        <LevelItem
                            key={level.id.toString()}
                            level={level}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

Levels.layout = {
    breadcrumbs: [
        {
            title: 'levels.index.breadcrumb',
            href: levelsIndexRoute.url(),
        },
    ],
};
