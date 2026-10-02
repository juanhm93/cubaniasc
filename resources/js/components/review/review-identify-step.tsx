import { CircleHelp, Clock, Footprints, Music } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from '@/i18n/use-translation';

type IdentifyMode = 'email' | 'dni';

type ReviewIdentifyStepProps = {
    loading: boolean;
    onSubmit: (payload: { email?: string; dni?: string }) => void;
};

export function ReviewIdentifyStep({
    loading,
    onSubmit,
}: ReviewIdentifyStepProps) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<IdentifyMode>('email');
    const [email, setEmail] = useState('');
    const [dni, setDni] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit(mode === 'email' ? { email } : { dni });
    };

    return (
        <section className="cubania-review__panel cubania-review__panel--identify">
            <ul className="cubania-review__features">
                <li>
                    <Clock aria-hidden />
                    {t('review.features.minutes', { count: 30 })}
                </li>
                <li>
                    <Footprints aria-hidden />
                    {t('review.features.figures')}
                </li>
                <li>
                    <CircleHelp aria-hidden />
                    {t('review.features.quiz')}
                </li>
                <li>
                    <Music aria-hidden />
                    {t('review.features.songs')}
                </li>
            </ul>

            <h2 className="cubania-review__panel-title">
                {t('review.identify.title')}
            </h2>
            <p className="cubania-review__panel-lead">
                {t('review.identify.lead')}
            </p>

            <div className="cubania-review__toggle" role="tablist">
                <button
                    type="button"
                    role="tab"
                    aria-selected={mode === 'email'}
                    className={`cubania-review__toggle-btn ${mode === 'email' ? 'cubania-review__toggle-btn--active' : ''}`}
                    onClick={() => setMode('email')}
                >
                    {t('review.identify.email')}
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={mode === 'dni'}
                    className={`cubania-review__toggle-btn ${mode === 'dni' ? 'cubania-review__toggle-btn--active' : ''}`}
                    onClick={() => setMode('dni')}
                >
                    {t('review.identify.dni')}
                </button>
            </div>

            <form onSubmit={submit} className="cubania-review__identify-form">
                {mode === 'email' ? (
                    <div className="cubania-pre-reg__field">
                        <label
                            className="cubania-pre-reg__label"
                            htmlFor="review-email"
                        >
                            {t('review.identify.emailLabel')}
                        </label>
                        <input
                            id="review-email"
                            className="cubania-pre-reg__input"
                            type="email"
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            autoComplete="email"
                            required
                        />
                    </div>
                ) : (
                    <div className="cubania-pre-reg__field">
                        <label
                            className="cubania-pre-reg__label"
                            htmlFor="review-dni"
                        >
                            {t('review.identify.dniLabel')}
                        </label>
                        <input
                            id="review-dni"
                            className="cubania-pre-reg__input"
                            type="text"
                            inputMode="numeric"
                            value={dni}
                            onChange={(event) => setDni(event.target.value)}
                            required
                        />
                    </div>
                )}

                <button
                    type="submit"
                    className="cubania-btn cubania-btn--primary"
                    disabled={loading}
                    data-cubania-cursor="interactive"
                >
                    {loading
                        ? t('review.identify.submitting')
                        : t('review.identify.submit')}
                </button>
            </form>
        </section>
    );
}
