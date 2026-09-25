import { Head } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { CubaniaFooter } from '@/components/base/cubania/cubania-footer';
import { CubaniaNavMinimal } from '@/components/base/cubania/cubania-nav';
import { ReviewDashboard } from '@/components/review/review-dashboard';
import { ReviewExpired } from '@/components/review/review-expired';
import { ReviewFigureReview } from '@/components/review/review-figure-review';
import { ReviewFigureSelection } from '@/components/review/review-figure-selection';
import { ReviewIdentifyStep } from '@/components/review/review-identify-step';
import { ReviewLocked } from '@/components/review/review-locked';
import { ReviewQuizStep } from '@/components/review/review-quiz-step';
import {
    ReviewCompleteStep,
    ReviewSongsStep,
} from '@/components/review/review-songs-step';
import { ReviewStepMap } from '@/components/review/review-step-map';
import { ReviewTimerRing } from '@/components/review/review-timer-ring';
import { TIMED_STEPS, useReviewPanel } from '@/hooks/use-review-panel';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewStep } from '@/types/review';

import '../../css/landing/cubania-landing.css';
import '../../css/review/cubania-review.css';

const SESSION_STEPS: ReviewStep[] = [
    'figures-select',
    'figures-review',
    'quiz',
    'songs',
    'expired',
    'complete',
];

export default function RepasoPage() {
    const { t } = useTranslation();
    const panel = useReviewPanel();
    const inSession = SESSION_STEPS.includes(panel.step);
    const showTimer =
        panel.session !== null && TIMED_STEPS.includes(panel.step);
    const reviewFigure = panel.selectedFigures[panel.reviewFigureIndex];

    return (
        <>
            <Head title={t('review.pageTitle')}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;700;800&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap"
                />
            </Head>

            <div className="cubania-landing">
                <CubaniaNavMinimal />

                <main className="cubania-review">
                    {panel.step === 'identify' ? (
                        <header className="cubania-review__hero">
                            <h1 className="cubania-review__hero-title">
                                <span className="cubania-review__hero-lead">
                                    {t('review.title')}
                                </span>
                                <span className="cubania-review__hero-accent">
                                    {t('review.titleAccent')}
                                </span>
                            </h1>
                            <p className="cubania-review__hero-subtitle">
                                {t('review.subtitle')}
                            </p>
                        </header>
                    ) : (
                        <header className="cubania-review__topbar">
                            <div>
                                <p className="cubania-review__eyebrow">
                                    {t('review.title')}{' '}
                                    {t('review.titleAccent')}
                                </p>
                                {panel.auth ? (
                                    <p className="cubania-review__subtitle">
                                        {panel.auth.student.name} ·{' '}
                                        {panel.auth.level.name}
                                    </p>
                                ) : null}
                            </div>

                            {showTimer && panel.session ? (
                                <ReviewTimerRing
                                    remainingSeconds={panel.remainingSeconds}
                                    totalSeconds={
                                        panel.session.duration_seconds
                                    }
                                />
                            ) : null}
                        </header>
                    )}

                    {inSession ? <ReviewStepMap step={panel.step} /> : null}

                    {panel.error ? (
                        <p className="cubania-review__error" role="alert">
                            {panel.error}
                        </p>
                    ) : null}

                    {panel.syncing ? (
                        <section
                            className="cubania-review__panel cubania-review__panel--loading"
                            aria-busy
                        >
                            <span className="cubania-review__skeleton" />
                            <span className="cubania-review__skeleton cubania-review__skeleton--short" />
                        </section>
                    ) : (
                        <>
                            {panel.step === 'identify' ? (
                                <ReviewIdentifyStep
                                    loading={panel.loading}
                                    onSubmit={panel.identify}
                                />
                            ) : null}

                            {panel.step === 'dashboard' && panel.auth ? (
                                <ReviewDashboard
                                    auth={panel.auth}
                                    streak={panel.streak}
                                    loading={panel.loading}
                                    onStart={panel.startReview}
                                />
                            ) : null}

                            {panel.step === 'locked' ? (
                                <ReviewLocked
                                    streak={panel.streak}
                                    onUnlocked={panel.refresh}
                                />
                            ) : null}

                            {panel.step === 'figures-select' ? (
                                <ReviewFigureSelection
                                    figures={panel.figureOptions}
                                    selectedIds={panel.selectedFigureIds}
                                    currentLevelId={
                                        panel.session?.level_id ?? null
                                    }
                                    loading={panel.loading}
                                    onToggle={panel.toggleFigure}
                                    onContinue={panel.confirmFigureSelection}
                                />
                            ) : null}

                            {panel.step === 'figures-review' && reviewFigure ? (
                                <ReviewFigureReview
                                    figure={reviewFigure}
                                    index={panel.reviewFigureIndex}
                                    total={panel.selectedFigures.length}
                                    loading={panel.loading}
                                    onContinue={panel.continueFigureReview}
                                />
                            ) : null}

                            {panel.step === 'quiz' ? (
                                <ReviewQuizStep
                                    item={panel.quizItem}
                                    feedback={panel.quizFeedback}
                                    selectedOptionId={panel.selectedOptionId}
                                    answeredCount={panel.answeredCount}
                                    totalQuestions={
                                        panel.session?.quiz_max ?? 5
                                    }
                                    loading={panel.loading}
                                    onAnswer={panel.answerQuiz}
                                    onContinue={panel.continueQuiz}
                                />
                            ) : null}

                            {panel.step === 'expired' ? (
                                <ReviewExpired
                                    hasSelectedFigures={
                                        panel.selectedFigures.length > 0
                                    }
                                    loading={panel.loading}
                                    onShowSongs={panel.showSongs}
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
                                    streakGrew={
                                        (panel.streak?.current_streak ?? 0) >
                                        panel.previousStreakCount
                                    }
                                    studentName={
                                        panel.auth?.student.name.split(
                                            ' ',
                                        )[0] ?? ''
                                    }
                                    onSignOut={panel.signOut}
                                />
                            ) : null}
                        </>
                    )}

                    {panel.auth && panel.step !== 'complete' ? (
                        <div className="cubania-review__signout">
                            <button
                                type="button"
                                className="cubania-review__signout-btn"
                                onClick={panel.signOut}
                                disabled={panel.loading}
                                data-cubania-cursor="interactive"
                            >
                                <LogOut aria-hidden />
                                {t('review.signOut')}
                            </button>
                        </div>
                    ) : null}
                </main>

                <CubaniaFooter />
            </div>
        </>
    );
}
