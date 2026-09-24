import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { CubaniaFooter } from '@/components/base/cubania/cubania-footer';
import { CubaniaNav } from '@/components/base/cubania/cubania-nav';
import { CubaniaWhatsappFloat } from '@/components/base/cubania/cubania-whatsapp-float';
import { useTranslation } from '@/i18n/use-translation';
import preRegistration from '@/routes/pre-registration';

import '../../css/landing/cubania-landing.css';

type PreRegistrationPageProps = {
    canLogin?: boolean;
    canRegister?: boolean;
    status?: string | null;
};

export default function PreRegistrationPage({
    canLogin = true,
    canRegister = true,
    status = null,
}: PreRegistrationPageProps) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    const form = useForm({
        name: '',
        email: '',
        phone: '',
        country: '',
        message: '',
        agree: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(preRegistration.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.clearErrors();
            },
        });
    };

    return (
        <>
            <Head title={t('landing.preRegistration.headTitle')}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap"
                />
            </Head>
            <div className="cubania-landing">
                <CubaniaNav
                    isAuthenticated={Boolean(auth.user)}
                    canLogin={canLogin}
                    canRegister={canRegister}
                />
                <main className="cubania-pre-reg">
                    <h1 className="cubania-pre-reg__title">
                        {t('landing.preRegistration.title')}
                    </h1>
                    <p className="cubania-pre-reg__lead">
                        {t('landing.preRegistration.lead')}
                    </p>
                    {status === 'pre-registration-created' ? (
                        <p className="cubania-pre-reg__success" role="status">
                            {t('landing.preRegistration.success')}
                        </p>
                    ) : null}
                    <form onSubmit={submit} noValidate>
                        <div className="cubania-pre-reg__field">
                            <label
                                className="cubania-pre-reg__label"
                                htmlFor="pre-reg-name"
                            >
                                {t('common.fullName')}
                            </label>
                            <input
                                id="pre-reg-name"
                                className="cubania-pre-reg__input"
                                type="text"
                                name="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                autoComplete="name"
                                required
                            />
                            {form.errors.name ? (
                                <p className="cubania-pre-reg__error">
                                    {form.errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label
                                className="cubania-pre-reg__label"
                                htmlFor="pre-reg-email"
                            >
                                {t('common.emailAddress')}
                            </label>
                            <input
                                id="pre-reg-email"
                                className="cubania-pre-reg__input"
                                type="email"
                                name="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                autoComplete="email"
                                required
                            />
                            {form.errors.email ? (
                                <p className="cubania-pre-reg__error">
                                    {form.errors.email}
                                </p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label
                                className="cubania-pre-reg__label"
                                htmlFor="pre-reg-phone"
                            >
                                {t('common.phone')}{' '}
                                <span className="cubania-pre-reg__label-note">
                                    {t('landing.preRegistration.phoneOptional')}
                                </span>
                            </label>
                            <input
                                id="pre-reg-phone"
                                className="cubania-pre-reg__input"
                                type="tel"
                                name="phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                autoComplete="tel"
                            />
                            {form.errors.phone ? (
                                <p className="cubania-pre-reg__error">
                                    {form.errors.phone}
                                </p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label
                                className="cubania-pre-reg__label"
                                htmlFor="pre-reg-country"
                            >
                                {t('common.country')}{' '}
                                <span className="cubania-pre-reg__label-note">
                                    {t(
                                        'landing.preRegistration.countryOptional',
                                    )}
                                </span>
                            </label>
                            <select
                                id="pre-reg-country"
                                className="cubania-pre-reg__select"
                                name="country"
                                value={form.data.country}
                                onChange={(e) =>
                                    form.setData('country', e.target.value)
                                }
                                autoComplete="country"
                            >
                                <option value="">
                                    {t(
                                        'landing.preRegistration.countryPlaceholder',
                                    )}
                                </option>
                                <option value="VE">
                                    {t('common.countries.VE')}
                                </option>
                                <option value="CO">
                                    {t('common.countries.CO')}
                                </option>
                            </select>
                            {form.errors.country ? (
                                <p className="cubania-pre-reg__error">
                                    {form.errors.country}
                                </p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label
                                className="cubania-pre-reg__label"
                                htmlFor="pre-reg-message"
                            >
                                {t('landing.preRegistration.messageLabel')}
                            </label>
                            <textarea
                                id="pre-reg-message"
                                className="cubania-pre-reg__textarea"
                                name="message"
                                value={form.data.message}
                                onChange={(e) =>
                                    form.setData('message', e.target.value)
                                }
                                rows={4}
                                maxLength={5000}
                            />
                            {form.errors.message ? (
                                <p className="cubania-pre-reg__error">
                                    {form.errors.message}
                                </p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <div className="cubania-pre-reg__check">
                                <input
                                    id="pre-reg-agree"
                                    type="checkbox"
                                    name="agree"
                                    checked={form.data.agree}
                                    onChange={(e) =>
                                        form.setData('agree', e.target.checked)
                                    }
                                />
                                <label
                                    className="cubania-pre-reg__check-label"
                                    htmlFor="pre-reg-agree"
                                >
                                    {t('landing.preRegistration.agreeLabel')}
                                </label>
                            </div>
                            {form.errors.agree ? (
                                <p className="cubania-pre-reg__error">
                                    {form.errors.agree}
                                </p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__submit">
                            <button
                                type="submit"
                                className="cubania-btn cubania-btn--primary"
                                disabled={form.processing}
                                data-cubania-cursor="interactive"
                            >
                                {form.processing
                                    ? t('landing.preRegistration.submitting')
                                    : t('landing.preRegistration.submit')}
                            </button>
                        </div>
                    </form>
                </main>
                <CubaniaFooter />
                <CubaniaWhatsappFloat />
            </div>
        </>
    );
}
