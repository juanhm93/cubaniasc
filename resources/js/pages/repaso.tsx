import { Head, usePage } from '@inertiajs/react';
import { CubaniaFooter } from '@/components/base/cubania/cubania-footer';
import { CubaniaNav } from '@/components/base/cubania/cubania-nav';
import { ReviewCompleteStep, ReviewSongsStep } from '@/components/review/review-songs-step';
import { ReviewFigureReview } from '@/components/review/review-figure-review';
import { ReviewFigureSelection } from '@/components/review/review-figure-selection';
import { ReviewIdentifyStep } from '@/components/review/review-identify-step';
import { ReviewQuizStep } from '@/components/review/review-quiz-step';
import { useReviewPanel } from '@/hooks/use-review-panel';
import type { ReviewStep } from '@/types/review';

import '../../css/landing/cubania-landing.css';
import '../../css/review/cubania-review.css';

const STEP_ORDER: ReviewStep[] = [
  'identify',
  'figures-select',
  'figures-review',
  'quiz',
  'songs',
  'complete',
];

export default function RepasoPage() {
  const { auth: inertiaAuth } = usePage().props;
  const panel = useReviewPanel();

  const currentStepIndex = STEP_ORDER.indexOf(panel.step);

  return (
    <>
      <Head title="Repaso">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
          rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;700;800&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap"
        />
      </Head>

      <div className="cubania-landing">
        <CubaniaNav isAuthenticated={Boolean(inertiaAuth.user)} canRegister={false} />

        <main className="cubania-review">
          <header className="cubania-review__header">
            <div>
              <h1 className="cubania-review__title">Panel de repaso</h1>
              {panel.auth ? (
                <p className="cubania-review__subtitle">
                  {panel.auth.student.name} · {panel.auth.level.name}
                </p>
              ) : (
                <p className="cubania-review__subtitle">
                  Sesión corta de figuras, quiz y música
                </p>
              )}
            </div>

            {panel.session && panel.step !== 'identify' && panel.step !== 'complete' ? (
              <div
                className={`cubania-review__timer ${panel.remainingSeconds <= 60 ? 'cubania-review__timer--urgent' : ''}`}
                role="timer"
                aria-live="polite"
              >
                <span aria-hidden>⏱</span>
                {panel.formattedTimer}
              </div>
            ) : null}
          </header>

          {panel.step !== 'identify' ? (
            <div className="cubania-review__steps" aria-hidden>
              {STEP_ORDER.filter((step) => step !== 'identify').map((step, index) => {
                const dotIndex = index + 1;
                const active = dotIndex === currentStepIndex;
                const done = dotIndex < currentStepIndex;

                return (
                  <span
                    key={step}
                    className={`cubania-review__step-dot ${active ? 'cubania-review__step-dot--active' : ''} ${done ? 'cubania-review__step-dot--done' : ''}`}
                  />
                );
              })}
            </div>
          ) : null}

          {panel.error ? (
            <p className="cubania-review__error" role="alert">
              {panel.error}
            </p>
          ) : null}

          {panel.step === 'identify' ? (
            <ReviewIdentifyStep loading={panel.loading} onSubmit={panel.identify} />
          ) : null}

          {panel.step === 'figures-select' && panel.loading && panel.figureOptions.length === 0 ? (
            <section className="cubania-review__panel">
              <p className="cubania-review__panel-lead">Preparando tu sesión de repaso…</p>
            </section>
          ) : null}

          {panel.step === 'figures-select' && (!panel.loading || panel.figureOptions.length > 0) ? (
            <ReviewFigureSelection
              figures={panel.figureOptions}
              selectedIds={panel.selectedFigureIds}
              loading={panel.loading}
              onToggle={panel.toggleFigure}
              onContinue={panel.confirmFigureSelection}
            />
          ) : null}

          {panel.step === 'figures-review' && panel.selectedFiguresForReview[panel.reviewFigureIndex] ? (
            <ReviewFigureReview
              figure={panel.selectedFiguresForReview[panel.reviewFigureIndex]}
              index={panel.reviewFigureIndex}
              total={panel.selectedFiguresForReview.length}
              loading={panel.loading}
              onContinue={panel.continueFigureReview}
            />
          ) : null}

          {panel.step === 'quiz' ? (
            <ReviewQuizStep
              item={panel.quizItem}
              feedback={panel.quizFeedback}
              selectedOptionId={panel.selectedOptionId}
              loading={panel.loading}
              isExpired={panel.isExpired}
              onAnswer={panel.answerQuiz}
              onContinue={panel.continueQuiz}
              onSkipToSongs={panel.goToSongs}
            />
          ) : null}

          {panel.step === 'songs' ? (
            <ReviewSongsStep
              songs={panel.songs}
              loading={panel.loading}
              onComplete={panel.finishSession}
            />
          ) : null}

          {panel.step === 'complete' ? (
            <ReviewCompleteStep
              streak={panel.streak}
              studentName={panel.auth?.student.name ?? 'Alumno'}
              onRestart={panel.restart}
              onSignOut={panel.signOut}
            />
          ) : null}

          {panel.auth && panel.step !== 'identify' && panel.step !== 'complete' ? (
            <div className="cubania-review__actions" style={{ marginTop: 32 }}>
              <button
                type="button"
                className="cubania-btn cubania-btn--secondary"
                onClick={panel.signOut}
                disabled={panel.loading}
                data-cubania-cursor="interactive"
              >
                Cerrar sesión
              </button>
            </div>
          ) : null}
        </main>

        <CubaniaFooter />
      </div>
    </>
  );
}
