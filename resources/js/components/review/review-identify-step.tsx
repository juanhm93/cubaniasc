import { FormEvent, useState } from 'react';

type IdentifyMode = 'email' | 'dni';

type ReviewIdentifyStepProps = {
  loading: boolean;
  onSubmit: (payload: { email?: string; dni?: string }) => void;
};

export function ReviewIdentifyStep({ loading, onSubmit }: ReviewIdentifyStepProps) {
  const [mode, setMode] = useState<IdentifyMode>('email');
  const [email, setEmail] = useState('');
  const [dni, setDni] = useState('');

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit(mode === 'email' ? { email } : { dni });
  };

  return (
    <section className="cubania-review__panel">
      <h2 className="cubania-review__panel-title">Identifícate para repasar</h2>
      <p className="cubania-review__panel-lead">
        Ingresa el correo o la cédula con la que estás inscrito en Cubanía. No necesitas contraseña.
      </p>

      <div className="cubania-review__toggle">
        <button
          type="button"
          className={`cubania-review__toggle-btn ${mode === 'email' ? 'cubania-review__toggle-btn--active' : ''}`}
          onClick={() => setMode('email')}
        >
          Correo
        </button>
        <button
          type="button"
          className={`cubania-review__toggle-btn ${mode === 'dni' ? 'cubania-review__toggle-btn--active' : ''}`}
          onClick={() => setMode('dni')}
        >
          Cédula
        </button>
      </div>

      <form onSubmit={submit}>
        {mode === 'email' ? (
          <div className="cubania-pre-reg__field">
            <label className="cubania-pre-reg__label" htmlFor="review-email">
              Correo electrónico
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
            <label className="cubania-pre-reg__label" htmlFor="review-dni">
              Cédula
            </label>
            <input
              id="review-dni"
              className="cubania-pre-reg__input"
              type="text"
              value={dni}
              onChange={(event) => setDni(event.target.value)}
              required
            />
          </div>
        )}

        <div className="cubania-review__actions">
          <button
            type="submit"
            className="cubania-btn cubania-btn--primary"
            disabled={loading}
            data-cubania-cursor="interactive"
          >
            {loading ? 'Verificando…' : 'Entrar al repaso'}
          </button>
        </div>
      </form>
    </section>
  );
}
