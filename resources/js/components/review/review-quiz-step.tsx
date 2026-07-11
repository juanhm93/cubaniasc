import type { QuizFeedback, ReviewQuizItem } from '@/types/review';

type ReviewQuizStepProps = {
  item: ReviewQuizItem | null;
  feedback: QuizFeedback | null;
  selectedOptionId: number | null;
  loading: boolean;
  isExpired: boolean;
  onAnswer: (optionId: number) => void;
  onContinue: () => void;
  onSkipToSongs: () => void;
};

export function ReviewQuizStep({
  item,
  feedback,
  selectedOptionId,
  loading,
  isExpired,
  onAnswer,
  onContinue,
  onSkipToSongs,
}: ReviewQuizStepProps) {
  if (isExpired) {
    return (
      <section className="cubania-review__panel">
        <h2 className="cubania-review__panel-title">¡Se acabó el tiempo!</h2>
        <p className="cubania-review__panel-lead">
          Tu sesión de repaso expiró. Puedes ver las canciones recomendadas y cerrar la sesión.
        </p>
        <div className="cubania-review__actions">
          <button
            type="button"
            className="cubania-btn cubania-btn--primary"
            onClick={onSkipToSongs}
            data-cubania-cursor="interactive"
          >
            Ver canciones
          </button>
        </div>
      </section>
    );
  }

  if (!item) {
    return (
      <section className="cubania-review__panel">
        <h2 className="cubania-review__panel-title">Quiz</h2>
        <p className="cubania-review__panel-lead">
          {loading ? 'Cargando pregunta…' : 'No hay preguntas disponibles por ahora.'}
        </p>
        {!loading ? (
          <div className="cubania-review__actions">
            <button
              type="button"
              className="cubania-btn cubania-btn--primary"
              onClick={onSkipToSongs}
              data-cubania-cursor="interactive"
            >
              Continuar
            </button>
          </div>
        ) : null}
      </section>
    );
  }

  return (
    <section className="cubania-review__panel">
      <p className="cubania-review__muted">
        {item.type === 'fun_fact' ? 'Dato curioso' : 'Figura'}
      </p>
      <h2 className="cubania-review__quiz-prompt">{item.prompt}</h2>

      <div className="cubania-review__quiz-options">
        {item.options.map((option) => {
          let stateClass = '';

          if (feedback && selectedOptionId === option.id) {
            stateClass = feedback.is_correct
              ? 'cubania-review__quiz-option--correct'
              : 'cubania-review__quiz-option--incorrect';
          }

          return (
            <button
              key={option.id}
              type="button"
              className={`cubania-review__quiz-option ${stateClass}`}
              disabled={loading || feedback !== null}
              onClick={() => onAnswer(option.id)}
              data-cubania-cursor="interactive"
            >
              {option.description}
            </button>
          );
        })}
      </div>

      {feedback ? (
        <div className="cubania-review__actions">
          <p className="cubania-review__panel-lead">
            {feedback.is_correct ? '¡Correcto!' : 'Casi — sigue repasando.'}
          </p>
          <button
            type="button"
            className="cubania-btn cubania-btn--primary"
            disabled={loading}
            onClick={onContinue}
            data-cubania-cursor="interactive"
          >
            {loading ? 'Cargando…' : 'Siguiente pregunta'}
          </button>
        </div>
      ) : null}
    </section>
  );
}
