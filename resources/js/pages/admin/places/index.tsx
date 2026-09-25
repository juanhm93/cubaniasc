import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEventHandler } from 'react';
import ConfirmDeleteDialog from '@/components/content/confirm-delete-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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

type PlaceRow = {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    courses_count: number;
};

type PlacesIndexProps = {
    places: PlaceRow[];
};

export default function AdminPlacesIndex({ places }: PlacesIndexProps) {
    const { t } = useTranslation();
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingPlace, setEditingPlace] = useState<PlaceRow | null>(null);
    const [placeToDelete, setPlaceToDelete] = useState<PlaceRow | null>(null);
    const [deletingPlace, setDeletingPlace] = useState(false);

    const form = useForm({
        name: '',
        address: '',
        phone: '',
    });

    const openCreateDialog = (): void => {
        setEditingPlace(null);
        form.clearErrors();
        form.setData({ name: '', address: '', phone: '' });
        setFormDialogOpen(true);
    };

    const openEditDialog = (place: PlaceRow): void => {
        setEditingPlace(place);
        form.clearErrors();
        form.setData({
            name: place.name,
            address: place.address ?? '',
            phone: place.phone ?? '',
        });
        setFormDialogOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setFormDialogOpen(false),
        };

        if (editingPlace === null) {
            form.post(admin.places.store.url(), options);

            return;
        }

        form.patch(admin.places.update.url(editingPlace.id), options);
    };

    const confirmDeletePlace = (): void => {
        if (placeToDelete === null) {
            return;
        }

        router.delete(admin.places.destroy.url(placeToDelete.id), {
            preserveScroll: true,
            onStart: () => setDeletingPlace(true),
            onSuccess: () => setPlaceToDelete(null),
            onFinish: () => setDeletingPlace(false),
        });
    };

    return (
        <>
            <Head title={t('admin.places.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {t('admin.places.title')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.places.description')}
                            </p>
                        </div>
                        <Button type="button" onClick={openCreateDialog}>
                            {t('admin.places.createPlace')}
                        </Button>
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[640px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.name')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.address')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('common.phone')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.places.courses')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                        {t('common.actions')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {places.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            {t('admin.places.noPlaces')}
                                        </td>
                                    </tr>
                                ) : (
                                    places.map((place) => (
                                        <tr
                                            key={place.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle font-medium">
                                                {place.name}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {place.address ||
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                {place.phone ||
                                                    t('common.emDash')}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {place.courses_count}
                                            </td>
                                            <td className="px-3 py-3 text-right align-middle">
                                                <div className="flex items-center justify-end gap-3">
                                                    <button
                                                        type="button"
                                                        className="text-sm text-primary underline-offset-4 hover:underline"
                                                        onClick={() =>
                                                            openEditDialog(
                                                                place,
                                                            )
                                                        }
                                                    >
                                                        {t('common.edit')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="text-sm text-destructive underline-offset-4 hover:underline disabled:cursor-not-allowed disabled:no-underline disabled:opacity-50"
                                                        disabled={
                                                            place.courses_count >
                                                            0
                                                        }
                                                        title={
                                                            place.courses_count >
                                                            0
                                                                ? t(
                                                                      'admin.places.inUseHint',
                                                                  )
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            setPlaceToDelete(
                                                                place,
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
                </div>
            </div>

            <Dialog open={formDialogOpen} onOpenChange={setFormDialogOpen}>
                <DialogContent>
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editingPlace === null
                                    ? t('admin.places.newPlace')
                                    : t('admin.places.editPlace')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('admin.places.formDescription')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="place-name">
                                {t('common.name')}
                            </Label>
                            <Input
                                id="place-name"
                                name="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                                autoFocus
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="place-address">
                                {t('common.address')}{' '}
                                <span className="text-muted-foreground">
                                    {t('common.optional')}
                                </span>
                            </Label>
                            <Input
                                id="place-address"
                                name="address"
                                value={form.data.address}
                                onChange={(e) =>
                                    form.setData('address', e.target.value)
                                }
                                autoComplete="street-address"
                            />
                            <InputError message={form.errors.address} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="place-phone">
                                {t('common.phone')}{' '}
                                <span className="text-muted-foreground">
                                    {t('common.optional')}
                                </span>
                            </Label>
                            <Input
                                id="place-phone"
                                name="phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                autoComplete="tel"
                            />
                            <InputError message={form.errors.phone} />
                        </div>

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
                open={placeToDelete !== null}
                title={t('admin.places.deletePlace')}
                description={t('admin.places.deleteConfirm', {
                    name: placeToDelete?.name ?? '',
                })}
                confirming={deletingPlace}
                onOpenChange={(open) => {
                    if (!open && !deletingPlace) {
                        setPlaceToDelete(null);
                    }
                }}
                onConfirm={confirmDeletePlace}
            />
        </>
    );
}

AdminPlacesIndex.layout = {
    breadcrumbs: [
        {
            title: 'navigation.places',
            href: admin.places.index.url(),
        },
    ],
};
