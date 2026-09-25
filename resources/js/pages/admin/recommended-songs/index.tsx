import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
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

type LevelOption = {
    id: number;
    name: string;
};

type DanceTypeOption = {
    id: number;
    name: string;
    levels: LevelOption[];
};

type SongRow = {
    id: number;
    title: string;
    artist: string;
    audio_or_link_url: string;
    is_active: boolean;
    levels: LevelOption[];
};

type RecommendedSongsIndexProps = {
    songs: SongRow[];
    danceTypes: DanceTypeOption[];
};

type SongFormData = {
    title: string;
    artist: string;
    audio_or_link_url: string;
    is_active: boolean;
    level_ids: number[];
};

const emptyForm: SongFormData = {
    title: '',
    artist: '',
    audio_or_link_url: '',
    is_active: true,
    level_ids: [],
};

export default function AdminRecommendedSongsIndex({
    songs,
    danceTypes,
}: RecommendedSongsIndexProps) {
    const { t } = useTranslation();
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingSong, setEditingSong] = useState<SongRow | null>(null);
    const [songToDelete, setSongToDelete] = useState<SongRow | null>(null);
    const [deletingSong, setDeletingSong] = useState(false);

    const form = useForm<SongFormData>(emptyForm);

    const openCreateDialog = (): void => {
        setEditingSong(null);
        form.clearErrors();
        form.setData(emptyForm);
        setFormDialogOpen(true);
    };

    const openEditDialog = (song: SongRow): void => {
        setEditingSong(song);
        form.clearErrors();
        form.setData({
            title: song.title,
            artist: song.artist,
            audio_or_link_url: song.audio_or_link_url,
            is_active: song.is_active,
            level_ids: song.levels.map((level) => level.id),
        });
        setFormDialogOpen(true);
    };

    const toggleLevel = (levelId: number, checked: boolean): void => {
        form.setData(
            'level_ids',
            checked
                ? [...form.data.level_ids, levelId]
                : form.data.level_ids.filter((id) => id !== levelId),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setFormDialogOpen(false),
        };

        if (editingSong === null) {
            form.post(admin.recommendedSongs.store.url(), options);

            return;
        }

        form.patch(admin.recommendedSongs.update.url(editingSong.id), options);
    };

    const confirmDeleteSong = (): void => {
        if (songToDelete === null) {
            return;
        }

        router.delete(admin.recommendedSongs.destroy.url(songToDelete.id), {
            preserveScroll: true,
            onStart: () => setDeletingSong(true),
            onSuccess: () => setSongToDelete(null),
            onFinish: () => setDeletingSong(false),
        });
    };

    const levelErrors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('level_ids'))
        .map(([, message]) => message);

    return (
        <>
            <Head title={t('admin.recommendedSongs.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {t('admin.recommendedSongs.title')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.recommendedSongs.description')}
                            </p>
                        </div>
                        <Button type="button" onClick={openCreateDialog}>
                            {t('admin.recommendedSongs.createSong')}
                        </Button>
                    </div>

                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <table className="w-full min-w-[720px] caption-bottom border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70">
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.recommendedSongs.songTitle')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('admin.recommendedSongs.artist')}
                                    </th>
                                    <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                        {t('navigation.levels')}
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
                                {songs.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            {t(
                                                'admin.recommendedSongs.noSongs',
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    songs.map((song) => (
                                        <tr
                                            key={song.id}
                                            className="border-b border-sidebar-border/70 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-middle font-medium">
                                                <a
                                                    href={
                                                        song.audio_or_link_url
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {song.title}
                                                </a>
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                {song.artist}
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                <div className="flex flex-wrap gap-1">
                                                    {song.levels.map(
                                                        (level) => (
                                                            <Badge
                                                                key={level.id}
                                                                variant="secondary"
                                                            >
                                                                {level.name}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 align-middle">
                                                <Badge
                                                    variant={
                                                        song.is_active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {song.is_active
                                                        ? t(
                                                              'common.activeFemale',
                                                          )
                                                        : t(
                                                              'common.inactiveFemale',
                                                          )}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3 text-right align-middle">
                                                <div className="flex items-center justify-end gap-3">
                                                    <button
                                                        type="button"
                                                        className="text-sm text-primary underline-offset-4 hover:underline"
                                                        onClick={() =>
                                                            openEditDialog(song)
                                                        }
                                                    >
                                                        {t('common.edit')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="text-sm text-destructive underline-offset-4 hover:underline"
                                                        onClick={() =>
                                                            setSongToDelete(
                                                                song,
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
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editingSong === null
                                    ? t('admin.recommendedSongs.newSong')
                                    : t('admin.recommendedSongs.editSong')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('admin.recommendedSongs.formDescription')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="song-title">
                                {t('admin.recommendedSongs.songTitle')}
                            </Label>
                            <Input
                                id="song-title"
                                name="title"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                required
                                autoFocus
                            />
                            <InputError message={form.errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="song-artist">
                                {t('admin.recommendedSongs.artist')}
                            </Label>
                            <Input
                                id="song-artist"
                                name="artist"
                                value={form.data.artist}
                                onChange={(e) =>
                                    form.setData('artist', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.artist} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="song-link">
                                {t('admin.recommendedSongs.link')}
                            </Label>
                            <Input
                                id="song-link"
                                name="audio_or_link_url"
                                type="url"
                                placeholder="https://"
                                value={form.data.audio_or_link_url}
                                onChange={(e) =>
                                    form.setData(
                                        'audio_or_link_url',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('admin.recommendedSongs.linkHint')}
                            </p>
                            <InputError
                                message={form.errors.audio_or_link_url}
                            />
                        </div>

                        <fieldset className="grid gap-3">
                            <legend className="mb-1 text-sm font-medium">
                                {t('navigation.levels')}
                            </legend>
                            {danceTypes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.recommendedSongs.noLevels')}
                                </p>
                            ) : (
                                danceTypes.map((danceType) => (
                                    <div
                                        key={danceType.id}
                                        className="grid gap-2"
                                    >
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            {danceType.name}
                                        </p>
                                        <div className="grid grid-cols-2 gap-2">
                                            {danceType.levels.map((level) => (
                                                <label
                                                    key={level.id}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        checked={form.data.level_ids.includes(
                                                            level.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleLevel(
                                                                level.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {level.name}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                            <InputError message={levelErrors[0]} />
                        </fieldset>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            {t('admin.recommendedSongs.activeLabel')}
                        </label>

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
                open={songToDelete !== null}
                title={t('admin.recommendedSongs.deleteSong')}
                description={t('admin.recommendedSongs.deleteConfirm', {
                    name: songToDelete?.title ?? '',
                })}
                confirming={deletingSong}
                onOpenChange={(open) => {
                    if (!open && !deletingSong) {
                        setSongToDelete(null);
                    }
                }}
                onConfirm={confirmDeleteSong}
            />
        </>
    );
}

AdminRecommendedSongsIndex.layout = {
    breadcrumbs: [
        {
            title: 'navigation.recommendedSongs',
            href: admin.recommendedSongs.index.url(),
        },
    ],
};
