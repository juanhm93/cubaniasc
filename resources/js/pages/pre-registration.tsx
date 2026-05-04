import { FormEventHandler } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CubaniaFooter } from '@/components/base/cubania/cubania-footer';
import { CubaniaNav } from '@/components/base/cubania/cubania-nav';
import preRegistration from '@/routes/pre-registration';

import '../../css/landing/cubania-landing.css';

type PreRegistrationPageProps = {
    canRegister?: boolean;
    status?: string | null;
};

export default function PreRegistrationPage({
    canRegister = true,
    status = null,
}: PreRegistrationPageProps) {
    const { auth } = usePage().props;

    const form = useForm({
        name: '',
        email: '',
        phone: '',
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
            <Head title="Preinscripción">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;700;800&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap"
                />
            </Head>
            <div className="cubania-landing">
                <CubaniaNav isAuthenticated={Boolean(auth.user)} canRegister={canRegister} />
                <main className="cubania-pre-reg">
                    <h1 className="cubania-pre-reg__title">Preinscripción</h1>
                    <p className="cubania-pre-reg__lead">
                        Déjanos tus datos y nos pondremos en contacto contigo. No necesitas cuenta para
                        completar este formulario.
                    </p>
                    {status === 'pre-registration-created' ? (
                        <p className="cubania-pre-reg__success" role="status">
                            ¡Listo! Hemos recibido tu preinscripción. Te contactaremos pronto.
                        </p>
                    ) : null}
                    <form onSubmit={submit} noValidate>
                        <div className="cubania-pre-reg__field">
                            <label className="cubania-pre-reg__label" htmlFor="pre-reg-name">
                                Nombre completo
                            </label>
                            <input
                                id="pre-reg-name"
                                className="cubania-pre-reg__input"
                                type="text"
                                name="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                autoComplete="name"
                                required
                            />
                            {form.errors.name ? (
                                <p className="cubania-pre-reg__error">{form.errors.name}</p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label className="cubania-pre-reg__label" htmlFor="pre-reg-email">
                                Correo electrónico
                            </label>
                            <input
                                id="pre-reg-email"
                                className="cubania-pre-reg__input"
                                type="email"
                                name="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                autoComplete="email"
                                required
                            />
                            {form.errors.email ? (
                                <p className="cubania-pre-reg__error">{form.errors.email}</p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label className="cubania-pre-reg__label" htmlFor="pre-reg-phone">
                                Teléfono{' '}
                                <span className="cubania-pre-reg__label-note">(opcional)</span>
                            </label>
                            <input
                                id="pre-reg-phone"
                                className="cubania-pre-reg__input"
                                type="tel"
                                name="phone"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                                autoComplete="tel"
                            />
                            {form.errors.phone ? (
                                <p className="cubania-pre-reg__error">{form.errors.phone}</p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <label className="cubania-pre-reg__label" htmlFor="pre-reg-message">
                                ¿Tienes algo que decir? (opcional)
                            </label>
                            <textarea
                                id="pre-reg-message"
                                className="cubania-pre-reg__textarea"
                                name="message"
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                                rows={4}
                                maxLength={5000}
                            />
                            {form.errors.message ? (
                                <p className="cubania-pre-reg__error">{form.errors.message}</p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__field">
                            <div className="cubania-pre-reg__check">
                                <input
                                    id="pre-reg-agree"
                                    type="checkbox"
                                    name="agree"
                                    checked={form.data.agree}
                                    onChange={(e) => form.setData('agree', e.target.checked)}
                                />
                                <label className="cubania-pre-reg__check-label" htmlFor="pre-reg-agree">
                                    Estoy de acuerdo con que Cubanía trate mis datos personales para gestionar mi
                                    preinscripción y contactarme.
                                </label>
                            </div>
                            {form.errors.agree ? (
                                <p className="cubania-pre-reg__error">{form.errors.agree}</p>
                            ) : null}
                        </div>
                        <div className="cubania-pre-reg__submit">
                            <button
                                type="submit"
                                className="cubania-btn cubania-btn--primary"
                                disabled={form.processing}
                                data-cubania-cursor="interactive"
                            >
                                {form.processing ? 'Enviando…' : 'Enviar preinscripción'}
                            </button>
                        </div>
                    </form>
                </main>
                <CubaniaFooter />
            </div>
        </>
    );
}
