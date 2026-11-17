import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEventHandler } from 'react';
import ConfirmDeleteDialog from '@/components/content/confirm-delete-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import admin from '@/routes/admin';

type PromotionStatus = 'active' | 'scheduled' | 'expired' | 'inactive';

type PromotionDisplayMode = 'modal' | 'section' | 'both';

type PromotionOrientation = 'portrait' | 'landscape';

type PromotionRow = {
    id: number;
    title: string;
    image_url: string;
    cta_label: string | null;
    cta_url: string | null;
    display_mode: PromotionDisplayMode;
    modal_orientation: PromotionOrientation;
    is_active: boolean;
    starts_on: string | null;
    ends_on: string | null;
    status: PromotionStatus;
};

type PromotionsIndexProps = {
    promotions: PromotionRow[];
};

type PromotionFormFields = {
    title: string;
    image: File | null;
    cta_label: string;
    cta_url: string;
    display_mode: PromotionDisplayMode;
    modal_orientation: PromotionOrientation;
    is_active: boolean;
    starts_on: string;
    ends_on: string;
};

const EMPTY_FORM: PromotionFormFields = {
    title: '',
    image: null,
    cta_label: '',
    cta_url: '',
    display_mode: 'both',
    modal_orientation: 'portrait',
    is_active: true,
    starts_on: '',
    ends_on: '',
};

const STATUS_BADGE_VARIANT: Record<
    PromotionStatus,
    'default' | 'secondary' | 'outline'
> = {
    active: 'default',
    scheduled: 'secondary',
    expired: 'outline',
    inactive: 'outline',
};

function formatDay(day: string): string {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(year, month - 1, date).toLocaleDateString('es', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function AdminPromotionsIndex({
    promotions,
}: PromotionsIndexProps) {
    const { t } = useTranslation();
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingPromotion, setEditingPromotion] =
        useState<PromotionRow | null>(null);
    const [promotionToDelete, setPromotionToDelete] =
        useState<PromotionRow | null>(null);
    const [deletingPromotion, setDeletingPromotion] = useState(false);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const imageInputRef = useRef<HTMLInputElement>(null);

    const form = useForm<PromotionFormFields>(EMPTY_FORM);

    const statusLabels: Record<PromotionStatus, string> = {
        active: t('admin.promotions.statusActive'),
        scheduled: t('admin.promotions.statusScheduled'),
        expired: t('admin.promotions.statusExpired'),
        inactive: t('admin.promotions.statusInactive'),
    };

    const displayModeLabels: Record<PromotionDisplayMode, string> = {
        modal: t('admin.promotions.displayModeModal'),
        section: t('admin.promotions.displayModeSection'),
        both: t('admin.promotions.displayModeBoth'),
    };

    const orientationLabels: Record<PromotionOrientation, string> = {
        portrait: t('admin.promotions.orientationPortrait'),
        landscape: t('admin.promotions.orientationLandscape'),
    };

    useEffect(() => {
        return () => {
            if (imagePreview?.startsWith('blob:')) {
                URL.revokeObjectURL(imagePreview);
            }
        };
    }, [imagePreview]);

    const resetImageInput = (): void => {
        setImagePreview(null);

        if (imageInputRef.current) {
            imageInputRef.current.value = '';
        }
    };

    const openCreateDialog = (): void => {
        setEditingPromotion(null);
        form.clearErrors();
        form.setData(EMPTY_FORM);
        resetImageInput();
        setFormDialogOpen(true);
    };

    const openEditDialog = (promotion: PromotionRow): void => {
        setEditingPromotion(promotion);
        form.clearErrors();
        form.setData({
            title: promotion.title,
            image: null,
            cta_label: promotion.cta_label ?? '',
            cta_url: promotion.cta_url ?? '',
            display_mode: promotion.display_mode,
            modal_orientation: promotion.modal_orientation,
            is_active: promotion.is_active,
            starts_on: promotion.starts_on ?? '',
            ends_on: promotion.ends_on ?? '',
        });
        resetImageInput();
        setFormDialogOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setFormDialogOpen(false);
                resetImageInput();
            },
        };

        if (editingPromotion === null) {
            form.transform((data) => data);
            form.post(admin.promotions.store.url(), options);

            return;
        }

        // Multipart PATCH bodies are not parsed by PHP, so spoof the method over POST.
        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post(admin.promotions.update.url(editingPromotion.id), options);
    };

    const confirmDeletePromotion = (): void => {
        if (promotionToDelete === null) {
            return;
        }

        router.delete(admin.promotions.destroy.url(promotionToDelete.id), {
            preserveScroll: true,
            onStart: () => setDeletingPromotion(true),
            onSuccess: () => setPromotionToDelete(null),
            onFinish: () => setDeletingPromotion(false),
        });
    };

    const formatDateRange = (promotion: PromotionRow): string => {
        if (promotion.starts_on === null && promotion.ends_on === null) {
            return t('admin.promotions.noDates');
        }

        const from =
            promotion.starts_on !== null
                ? formatDay(promotion.starts_on)
                : t('common.emDash');
        const to =
            promotion.ends_on !== null
                ? formatDay(promotion.ends_on)
                : t('common.emDash');

        return `${from} → ${to}`;
    };

    const currentImage = imagePreview ?? editingPromotion?.image_url ?? null;

    return (
        <>
            <Head title={t('admin.promotions.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {t('admin.promotions.title')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.promotions.description')}
                            </p>
                        </div>
                        <Button type="button" onClick={openCreateDialog}>
                            {t('admin.promotions.createPromotion')}
                        </Button>
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[720px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.promotions.image')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.promotions.promotionTitle')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.promotions.dates')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.status')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                        {t('common.actions')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {promotions.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            {t('admin.promotions.noPromotions')}
                                        </td>
                                    </tr>
                                ) : (
                                    promotions.map((promotion) => (
                                        <tr
                                            key={promotion.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle">
                                                <img
                                                    src={promotion.image_url}
                                                    alt=""
                                                    className="h-16 w-16 rounded-md border object-cover"
                                                />
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                <div className="font-medium">
                                                    {promotion.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {
                                                        displayModeLabels[
                                                            promotion
                                                                .display_mode
                                                        ]
                                                    }
                                                    {promotion.display_mode !==
                                                    'section'
                                                        ? ` · ${orientationLabels[promotion.modal_orientation]}`
                                                        : null}
                                                </div>
                                                {promotion.cta_label ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        {t(
                                                            'admin.promotions.buttonSummary',
                                                            {
                                                                label: promotion.cta_label,
                                                            },
                                                        )}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {formatDateRange(promotion)}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                <Badge
                                                    variant={
                                                        STATUS_BADGE_VARIANT[
                                                            promotion.status
                                                        ]
                                                    }
                                                >
                                                    {
                                                        statusLabels[
                                                            promotion.status
                                                        ]
                                                    }
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3 text-right align-middle">
                                                <div className="flex items-center justify-end gap-3">
                                                    <button
                                                        type="button"
                                                        className="text-sm text-primary underline-offset-4 hover:underline"
                                                        onClick={() =>
                                                            openEditDialog(
                                                                promotion,
                                                            )
                                                        }
                                                    >
                                                        {t('common.edit')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="text-sm text-destructive underline-offset-4 hover:underline"
                                                        onClick={() =>
                                                            setPromotionToDelete(
                                                                promotion,
                                                            )
                                                        }
                                                    >
                                                        {t('common.delete')}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        {t('admin.promotions.visibilityHint')}
                    </p>
                </div>
            </div>

            <Dialog open={formDialogOpen} onOpenChange={setFormDialogOpen}>
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editingPromotion === null
                                    ? t('admin.promotions.newPromotion')
                                    : t('admin.promotions.editPromotion')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('admin.promotions.formDescription')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="promotion-title">
                                {t('admin.promotions.promotionTitle')}
                            </Label>
                            <Input
                                id="promotion-title"
                                name="title"
                                value={form.data.title}
                                maxLength={120}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                required
                                autoFocus
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('admin.promotions.titleHint')}
                            </p>
                            <InputError message={form.errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="promotion-image">
                                {t('admin.promotions.image')}
                                {editingPromotion !== null ? (
                                    <>
                                        {' '}
                                        <span className="text-muted-foreground">
                                            {t('common.optional')}
                                        </span>
                                    </>
                                ) : null}
                            </Label>
                            {currentImage ? (
                                <img
                                    src={currentImage}
                                    alt=""
                                    className="max-h-48 w-auto max-w-full rounded-md border object-contain"
                                />
                            ) : null}
                            <Input
                                ref={imageInputRef}
                                id="promotion-image"
                                name="image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                required={editingPromotion === null}
                                className="cursor-pointer"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;
                                    form.setData('image', file);
                                    setImagePreview(
                                        file ? URL.createObjectURL(file) : null,
                                    );
                                }}
                            />
                            <p className="text-xs text-muted-foreground">
                                {editingPromotion === null
                                    ? t('admin.promotions.imageHint')
                                    : t('admin.promotions.imageReplaceHint')}
                            </p>
                            <InputError message={form.errors.image} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="promotion-cta-label">
                                    {t('admin.promotions.ctaLabel')}{' '}
                                    <span className="text-muted-foreground">
                                        {t('common.optional')}
                                    </span>
                                </Label>
                                <Input
                                    id="promotion-cta-label"
                                    name="cta_label"
                                    value={form.data.cta_label}
                                    maxLength={40}
                                    placeholder={t(
                                        'admin.promotions.ctaLabelPlaceholder',
                                    )}
                                    onChange={(e) =>
                                        form.setData(
                                            'cta_label',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.cta_label} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="promotion-cta-url">
                                    {t('admin.promotions.ctaUrl')}{' '}
                                    <span className="text-muted-foreground">
                                        {t('common.optional')}
                                    </span>
                                </Label>
                                <Input
                                    id="promotion-cta-url"
                                    name="cta_url"
                                    type="url"
                                    value={form.data.cta_url}
                                    placeholder="https://"
                                    onChange={(e) =>
                                        form.setData('cta_url', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.cta_url} />
                            </div>
                        </div>
                        <p className="-mt-2 text-xs text-muted-foreground">
                            {t('admin.promotions.ctaHint')}
                        </p>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="promotion-starts-on">
                                    {t('admin.promotions.startsOn')}{' '}
                                    <span className="text-muted-foreground">
                                        {t('common.optional')}
                                    </span>
                                </Label>
                                <Input
                                    id="promotion-starts-on"
                                    name="starts_on"
                                    type="date"
                                    value={form.data.starts_on}
                                    onChange={(e) =>
                                        form.setData(
                                            'starts_on',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.starts_on} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="promotion-ends-on">
                                    {t('admin.promotions.endsOn')}{' '}
                                    <span className="text-muted-foreground">
                                        {t('common.optional')}
                                    </span>
                                </Label>
                                <Input
                                    id="promotion-ends-on"
                                    name="ends_on"
                                    type="date"
                                    value={form.data.ends_on}
                                    min={form.data.starts_on || undefined}
                                    onChange={(e) =>
                                        form.setData('ends_on', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.ends_on} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="promotion-display-mode">
                                    {t('admin.promotions.displayMode')}
                                </Label>
                                <select
                                    id="promotion-display-mode"
                                    name="display_mode"
                                    value={form.data.display_mode}
                                    onChange={(e) =>
                                        form.setData(
                                            'display_mode',
                                            e.target
                                                .value as PromotionDisplayMode,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {(
                                        Object.keys(
                                            displayModeLabels,
                                        ) as PromotionDisplayMode[]
                                    ).map((mode) => (
                                        <option key={mode} value={mode}>
                                            {displayModeLabels[mode]}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.display_mode}
                                />
                            </div>

                            {form.data.display_mode !== 'section' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="promotion-modal-orientation">
                                        {t('admin.promotions.modalOrientation')}
                                    </Label>
                                    <select
                                        id="promotion-modal-orientation"
                                        name="modal_orientation"
                                        value={form.data.modal_orientation}
                                        onChange={(e) =>
                                            form.setData(
                                                'modal_orientation',
                                                e.target
                                                    .value as PromotionOrientation,
                                            )
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        {(
                                            Object.keys(
                                                orientationLabels,
                                            ) as PromotionOrientation[]
                                        ).map((orientation) => (
                                            <option
                                                key={orientation}
                                                value={orientation}
                                            >
                                                {orientationLabels[orientation]}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.modal_orientation}
                                    />
                                </div>
                            ) : null}
                        </div>
                        <p className="-mt-2 text-xs text-muted-foreground">
                            {t('admin.promotions.displayModeHint')}
                            {form.data.display_mode !== 'section'
                                ? ` ${t('admin.promotions.orientationHint')}`
                                : null}
                        </p>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            {t('admin.promotions.activeLabel')}
                        </label>
                        <InputError message={form.errors.is_active} />

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setFormDialogOpen(false)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? t('common.saving')
                                    : t('common.save')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={promotionToDelete !== null}
                title={t('admin.promotions.deletePromotion')}
                description={t('admin.promotions.deleteConfirm', {
                    name: promotionToDelete?.title ?? '',
                })}
                confirming={deletingPromotion}
                onOpenChange={(open) => {
                    if (!open && !deletingPromotion) {
                        setPromotionToDelete(null);
                    }
                }}
                onConfirm={confirmDeletePromotion}
            />
        </>
    );
}

AdminPromotionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'navigation.promotions',
            href: admin.promotions.index.url(),
        },
    ],
};
