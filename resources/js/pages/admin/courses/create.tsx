import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import admin from '@/routes/admin';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';

type Option = {
    id: number;
    name: string;
};

type SlotDraft = {
    weekday: number;
    starts_at: string;
    ends_at: string;
};

const WEEKDAY_OPTIONS: { value: number; key: string }[] = [
    { value: 1, key: 'monday' },
    { value: 2, key: 'tuesday' },
    { value: 3, key: 'wednesday' },
    { value: 4, key: 'thursday' },
    { value: 5, key: 'friday' },
    { value: 6, key: 'saturday' },
    { value: 7, key: 'sunday' },
];

type CreateCourseProps = {
    levels: Option[];
    places: Option[];
    teachers: Option[];
};

export default function AdminCoursesCreate({
    levels,
    places,
    teachers,
}: CreateCourseProps) {
    const { t } = useTranslation();
    const form = useForm({
        level_id: '',
        place_id: '',
        user_id: '',
        price: '',
        is_active: true as boolean,
        slots: [
            {
                weekday: 1,
                starts_at: '17:00',
                ends_at: '18:00',
            },
        ] as SlotDraft[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        form.post(admin.courses.store.url(), {
            preserveScroll: true,
        });
    };

    function updateSlot(index: number, patch: Partial<SlotDraft>): void {
        const next = form.data.slots.map((row, i) =>
            i === index ? { ...row, ...patch } : row,
        );
        form.setData('slots', next);
    }

    function addSlot(): void {
        form.setData('slots', [
            ...form.data.slots,
            {
                weekday: 5,
                starts_at: '16:00',
                ends_at: '18:00',
            },
        ]);
    }

    function removeSlot(index: number): void {
        form.setData(
            'slots',
            form.data.slots.filter((_, i) => i !== index),
        );
    }

    const placesEmpty = places.length === 0;
    const teachersEmpty = teachers.length === 0;

    return (
        <>
            <Head title={t('admin.courses.newCourse')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {t('admin.courses.newCourse')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.courses.newCourseDescription')}
                            </p>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={admin.courses.index.url()}>
                                {t('common.back')}
                            </Link>
                        </Button>
                    </div>

                    {(placesEmpty || teachersEmpty) && (
                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
                            {placesEmpty
                                ? t('admin.courses.noPlacesInAcademy')
                                : null}{' '}
                            {teachersEmpty
                                ? t('admin.courses.noTeachersInAcademy')
                                : null}{' '}
                            {t('admin.courses.completeDataBeforeCreate')}
                        </p>
                    )}

                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">
                                {t('common.generalData')}
                            </h2>

                            <div className="grid gap-2">
                                <Label htmlFor="level_id">
                                    {t('common.level')}
                                </Label>
                                <select
                                    id="level_id"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.level_id}
                                    onChange={(e) =>
                                        form.setData('level_id', e.target.value)
                                    }
                                    required
                                >
                                    <option value="" disabled>
                                        {t('common.select')}
                                    </option>
                                    {levels.map((l) => (
                                        <option
                                            key={l.id}
                                            value={String(l.id)}
                                        >
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.level_id} />
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="place_id">
                                        {t('common.place')}
                                    </Label>
                                    <select
                                        id="place_id"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.place_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'place_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        disabled={placesEmpty}
                                    >
                                        <option value="" disabled>
                                            {t('common.select')}
                                        </option>
                                        {places.map((p) => (
                                            <option
                                                key={p.id}
                                                value={String(p.id)}
                                            >
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.place_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="user_id">
                                        {t('common.teacher')}
                                    </Label>
                                    <select
                                        id="user_id"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.user_id}
                                        onChange={(e) =>
                                            form.setData('user_id', e.target.value)
                                        }
                                        required
                                        disabled={teachersEmpty}
                                    >
                                        <option value="" disabled>
                                            {t('common.select')}
                                        </option>
                                        {teachers.map((teacher) => (
                                            <option
                                                key={teacher.id}
                                                value={String(teacher.id)}
                                            >
                                                {teacher.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.user_id} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="price">
                                        {t('common.price')}
                                    </Label>
                                    <Input
                                        id="price"
                                        name="price"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min="0"
                                        required
                                        value={form.data.price}
                                        onChange={(e) =>
                                            form.setData('price', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.price} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="is_active">
                                        {t('common.status')}
                                    </Label>
                                    <select
                                        id="is_active"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.is_active ? '1' : '0'}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_active',
                                                e.target.value === '1',
                                            )
                                        }
                                    >
                                        <option value="1">
                                            {t('common.active')}
                                        </option>
                                        <option value="0">
                                            {t('common.inactiveDraft')}
                                        </option>
                                    </select>
                                    <InputError message={form.errors.is_active} />
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        {t('admin.courses.courseSchedules')}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.courses.courseSchedulesHint')}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    onClick={addSlot}
                                >
                                    {t('admin.courses.addSlot')}
                                </Button>
                            </div>

                            <div className="grid gap-3">
                                {form.data.slots.map((slot, index) => (
                                    <div
                                        key={index}
                                        className={cn(
                                            'flex flex-wrap items-end gap-2 rounded-lg border border-sidebar-border/50 p-3',
                                        )}
                                    >
                                        <div className="grid min-w-[10rem] flex-1 gap-1">
                                            <Label
                                                className="text-xs"
                                                htmlFor={`weekday-${index}`}
                                            >
                                                {t('common.day')}
                                            </Label>
                                            <select
                                                id={`weekday-${index}`}
                                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                                value={slot.weekday}
                                                onChange={(e) =>
                                                    updateSlot(index, {
                                                        weekday: Number(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                            >
                                                {WEEKDAY_OPTIONS.map((w) => (
                                                    <option
                                                        key={w.value}
                                                        value={w.value}
                                                    >
                                                        {t(
                                                            `admin.weekdays.${w.key}`,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label
                                                className="text-xs"
                                                htmlFor={`start-${index}`}
                                            >
                                                {t('common.from')}
                                            </Label>
                                            <Input
                                                id={`start-${index}`}
                                                type="time"
                                                value={slot.starts_at}
                                                onChange={(e) =>
                                                    updateSlot(index, {
                                                        starts_at: e.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label
                                                className="text-xs"
                                                htmlFor={`end-${index}`}
                                            >
                                                {t('common.to')}
                                            </Label>
                                            <Input
                                                id={`end-${index}`}
                                                type="time"
                                                value={slot.ends_at}
                                                onChange={(e) =>
                                                    updateSlot(index, {
                                                        ends_at: e.target.value,
                                                    })
                                                }
                                                required
                                            />
                                        </div>
                                        {form.data.slots.length > 1 ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground"
                                                onClick={() => removeSlot(index)}
                                            >
                                                {t('common.remove')}
                                            </Button>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                            <InputError message={form.errors.slots as string} />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    placesEmpty ||
                                    teachersEmpty ||
                                    levels.length === 0
                                }
                            >
                                {form.processing
                                    ? t('common.saving')
                                    : t('admin.courses.createCourse')}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={admin.courses.index.url()}>
                                    {t('common.cancel')}
                                </Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

AdminCoursesCreate.layout = {
    breadcrumbs: [
        {
            title: 'navigation.courses',
            href: admin.courses.index.url(),
        },
        {
            title: 'admin.breadcrumbs.new',
            href: admin.courses.create.url(),
        },
    ],
};
