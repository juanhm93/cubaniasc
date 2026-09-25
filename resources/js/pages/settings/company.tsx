import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import CompanySettingsController, {
    edit as companySettingsEdit,
} from '@/actions/App/Http/Controllers/Settings/CompanySettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';

type CompanyProps = {
    id: number;
    rif: string | null;
    name: string;
    slug: string;
    email: string;
    phone: string | null;
    address: string | null;
    logo: string | null;
    logo_url: string | null;
    website: string | null;
    is_active: boolean;
};

type PageProps = {
    company: CompanyProps | null;
    canEdit: boolean;
};

type CompanyFormFields = {
    name: string;
    slug: string;
    email: string;
    rif: string;
    phone: string;
    address: string;
    website: string;
    is_active: boolean;
    logo: File | null;
};

type FormEventHandler = React.FormEventHandler<HTMLFormElement> & {
    (event: React.FormEvent<HTMLFormElement>): void;
};

function textDefaults(
    company: CompanyProps | null,
): Omit<CompanyFormFields, 'logo'> {
    return {
        name: company?.name ?? '',
        slug: company?.slug ?? '',
        email: company?.email ?? '',
        rif: company?.rif ?? '',
        phone: company?.phone ?? '',
        address: company?.address ?? '',
        website: company?.website ?? '',
        is_active: company?.is_active ?? true,
    };
}

function buildFormData(company: CompanyProps | null): CompanyFormFields {
    return {
        ...textDefaults(company),
        logo: null,
    };
}

export default function CompanySettings({ company, canEdit }: PageProps) {
    const { t } = useTranslation();
    const hasCompany = company !== null;
    const [editing, setEditing] = useState(!hasCompany);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const logoFileRef = useRef<HTMLInputElement>(null);

    const defaults = useMemo(() => buildFormData(company), [company]);

    const form = useForm<CompanyFormFields>(defaults);

    useEffect(() => {
        return () => {
            if (logoPreview?.startsWith('blob:')) {
                URL.revokeObjectURL(logoPreview);
            }
        };
    }, [logoPreview]);

    const fieldsEnabled = !canEdit ? false : !hasCompany ? true : editing;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (!canEdit || !fieldsEnabled) {
            return;
        }

        form.patch(CompanySettingsController.update.url(), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    const companyDescription = canEdit
        ? hasCompany
            ? t('settings.companyDescriptionEdit')
            : t('settings.companyDescriptionCreate')
        : t('settings.companyDescriptionReadOnly');

    return (
        <>
            <Head title={t('settings.companySettings')} />

            <h1 className="sr-only">{t('settings.companySettings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('navigation.company')}
                    description={companyDescription}
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="flex flex-wrap items-center gap-2">
                        {canEdit && hasCompany && !editing ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setEditing(true)}
                            >
                                {t('common.edit')}
                            </Button>
                        ) : null}
                        {canEdit && hasCompany && editing ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    form.reset();
                                    setLogoPreview(null);

                                    if (logoFileRef.current) {
                                        logoFileRef.current.value = '';
                                    }

                                    setEditing(false);
                                }}
                                disabled={form.processing}
                            >
                                {t('common.cancel')}
                            </Button>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-name">
                            {t('common.name')}
                        </Label>
                        <Input
                            id="company-name"
                            name="name"
                            value={String(form.data.name)}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            disabled={!fieldsEnabled || form.processing}
                            autoComplete="organization"
                            className={cn(!fieldsEnabled && 'opacity-80')}
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-slug">
                            {t('common.slug')}
                        </Label>
                        <Input
                            id="company-slug"
                            name="slug"
                            value={String(form.data.slug)}
                            onChange={(e) =>
                                form.setData('slug', e.target.value)
                            }
                            placeholder={t('settings.slugPlaceholder')}
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(!fieldsEnabled && 'opacity-80')}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('settings.slugHelp')}
                        </p>
                        <InputError message={form.errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-email">
                            {t('common.email')}
                        </Label>
                        <Input
                            id="company-email"
                            type="email"
                            name="email"
                            value={String(form.data.email)}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(!fieldsEnabled && 'opacity-80')}
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-rif">
                            {t('settings.rif')}
                        </Label>
                        <Input
                            id="company-rif"
                            name="rif"
                            value={String(form.data.rif)}
                            onChange={(e) =>
                                form.setData('rif', e.target.value)
                            }
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(!fieldsEnabled && 'opacity-80')}
                        />
                        <InputError message={form.errors.rif} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-phone">
                            {t('common.phone')}
                        </Label>
                        <Input
                            id="company-phone"
                            name="phone"
                            type="tel"
                            value={String(form.data.phone)}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(!fieldsEnabled && 'opacity-80')}
                        />
                        <InputError message={form.errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-address">
                            {t('common.address')}
                        </Label>
                        <textarea
                            id="company-address"
                            name="address"
                            value={String(form.data.address)}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            rows={3}
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(
                                'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none md:text-sm',
                                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                'disabled:cursor-not-allowed disabled:opacity-50',
                                !fieldsEnabled && 'opacity-80',
                            )}
                        />
                        <InputError message={form.errors.address} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-logo-file">
                            {t('common.logo')}
                        </Label>
                        {(logoPreview ?? company?.logo_url) ? (
                            <div className="flex max-w-xs flex-col gap-2">
                                <img
                                    src={logoPreview ?? company?.logo_url ?? ''}
                                    alt=""
                                    className="h-24 w-auto max-w-full rounded-md border object-contain"
                                />
                            </div>
                        ) : null}
                        <Input
                            ref={logoFileRef}
                            id="company-logo-file"
                            name="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(
                                'cursor-pointer disabled:cursor-not-allowed',
                                !fieldsEnabled && 'opacity-80',
                            )}
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                form.setData('logo', file);
                                setLogoPreview((prev) => {
                                    if (prev?.startsWith('blob:')) {
                                        URL.revokeObjectURL(prev);
                                    }

                                    return file
                                        ? URL.createObjectURL(file)
                                        : null;
                                });
                            }}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('settings.logoHelp')}
                        </p>
                        <InputError message={form.errors.logo} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-website">
                            {t('common.website')}
                        </Label>
                        <Input
                            id="company-website"
                            name="website"
                            type="url"
                            value={String(form.data.website)}
                            onChange={(e) =>
                                form.setData('website', e.target.value)
                            }
                            placeholder="https://"
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(!fieldsEnabled && 'opacity-80')}
                        />
                        <InputError message={form.errors.website} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company-active">
                            {t('common.status')}
                        </Label>
                        <select
                            id="company-active"
                            name="is_active"
                            value={form.data.is_active ? '1' : '0'}
                            onChange={(e) =>
                                form.setData(
                                    'is_active',
                                    e.target.value === '1',
                                )
                            }
                            disabled={!fieldsEnabled || form.processing}
                            className={cn(
                                'h-9 w-full max-w-xs rounded-md border border-input bg-background px-3 text-sm',
                                !fieldsEnabled && 'opacity-80',
                            )}
                        >
                            <option value="1">
                                {t('settings.statusActive')}
                            </option>
                            <option value="0">
                                {t('settings.statusInactive')}
                            </option>
                        </select>
                        <InputError message={form.errors.is_active} />
                    </div>

                    {canEdit && fieldsEnabled ? (
                        <div className="flex items-center gap-4">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="save-company-button"
                            >
                                {form.processing
                                    ? t('common.saving')
                                    : t('common.save')}
                            </Button>
                        </div>
                    ) : null}
                </form>
            </div>
        </>
    );
}

CompanySettings.layout = {
    breadcrumbs: [
        {
            title: 'navigation.company',
            href: companySettingsEdit.url(),
        },
    ],
};
