import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@/i18n/use-translation';
import {
    ReviewApiError,
    clearReviewAuth,
    completeReviewSession,
    fetchCurrentReviewSession,
    fetchFigureOptions,
    fetchNextQuizQuestion,
    fetchSessionSongs,
    fetchStreak,
    identifyStudent,
    loadReviewAuth,
    logoutReview,
    recordFigureView,
    startReviewSession,
    storeSelectedFigures,
    submitQuizAnswer,
} from '@/lib/review-service';
import type {
    CurrentSessionResult,
    QuizFeedback,
    ReviewAuth,
    ReviewLevelContent,
    ReviewQuizItem,
    ReviewSession,
    ReviewSong,
    ReviewStep,
    ReviewStreak,
} from '@/types/review';

/**
 * Steps that run against the clock: when the timer reaches zero the panel moves to `expired`.
 */
export const TIMED_STEPS: ReviewStep[] = [
    'figures-select',
    'figures-review',
    'quiz',
];

export function formatTimer(seconds: number): string {
    const safeSeconds = Math.max(0, seconds);
    const mins = Math.floor(safeSeconds / 60);
    const secs = safeSeconds % 60;

    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export function useReviewPanel() {
    const { t } = useTranslation();
    const [auth, setAuth] = useState<ReviewAuth | null>(() => loadReviewAuth());
    const [step, setStep] = useState<ReviewStep>(() =>
        loadReviewAuth() ? 'dashboard' : 'identify',
    );
    const [syncing, setSyncing] = useState(() => loadReviewAuth() !== null);
    const [session, setSession] = useState<ReviewSession | null>(null);
    const [figureOptions, setFigureOptions] = useState<ReviewLevelContent[]>(
        [],
    );
    const [selectedFigureIds, setSelectedFigureIds] = useState<number[]>([]);
    const [selectedFigures, setSelectedFigures] = useState<
        ReviewLevelContent[]
    >([]);
    const [reviewFigureIndex, setReviewFigureIndex] = useState(0);
    const [quizItem, setQuizItem] = useState<ReviewQuizItem | null>(null);
    const [quizFeedback, setQuizFeedback] = useState<QuizFeedback | null>(null);
    const [selectedOptionId, setSelectedOptionId] = useState<number | null>(
        null,
    );
    const [answeredCount, setAnsweredCount] = useState(0);
    const [songs, setSongs] = useState<ReviewSong[]>([]);
    const [streak, setStreak] = useState<ReviewStreak | null>(null);
    const [previousStreakCount, setPreviousStreakCount] = useState(0);
    const [remainingSeconds, setRemainingSeconds] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const resetPanelState = useCallback(() => {
        setSession(null);
        setFigureOptions([]);
        setSelectedFigureIds([]);
        setSelectedFigures([]);
        setReviewFigureIndex(0);
        setQuizItem(null);
        setQuizFeedback(null);
        setSelectedOptionId(null);
        setAnsweredCount(0);
        setSongs([]);
        setRemainingSeconds(0);
    }, []);

    const handleApiError = useCallback(
        (err: unknown) => {
            if (!(err instanceof ReviewApiError)) {
                setError(t('review.errors.unexpected'));

                return;
            }

            switch (err.status) {
                case 401:
                    clearReviewAuth();
                    setAuth(null);
                    setStreak(null);
                    resetPanelState();
                    setStep('identify');
                    setError(t('review.errors.sessionEnded'));

                    return;
                case 409:
                    if (err.isDailyLock) {
                        resetPanelState();
                        setStep('locked');
                        setError(null);

                        return;
                    }

                    break;
                case 410:
                    setStep('expired');
                    setError(null);

                    return;
                case 422:
                    if (err.hasValidationErrors) {
                        setError(t('review.errors.invalidData'));

                        return;
                    }

                    break;
                case 429:
                    setError(t('review.errors.tooManyAttempts'));

                    return;
            }

            setError(err.message || t('review.errors.unexpected'));
        },
        [resetPanelState, t],
    );

    const applySession = useCallback((fresh: ReviewSession) => {
        setSession(fresh);
        setRemainingSeconds(fresh.remaining_seconds);
        setAnsweredCount(fresh.quiz_answered_count ?? 0);
        setSelectedFigures(fresh.selected_figures ?? []);
    }, []);

    const goToSongs = useCallback(async (sessionId: number) => {
        const sessionSongs = await fetchSessionSongs(sessionId);
        setSongs(sessionSongs);
        setStep('songs');
    }, []);

    const goToNextQuestion = useCallback(
        async (sessionId: number) => {
            const question = await fetchNextQuizQuestion(sessionId);

            setQuizFeedback(null);
            setSelectedOptionId(null);

            if (question === null) {
                await goToSongs(sessionId);

                return;
            }

            setQuizItem(question);
            setStep('quiz');
        },
        [goToSongs],
    );

    /**
     * Puts the panel on the step that matches the server state, so a reload or a second
     * device resumes the same session instead of starting over.
     */
    const resumeSession = useCallback(
        async (active: ReviewSession) => {
            applySession(active);

            if (active.expired) {
                setStep('expired');

                return;
            }

            if ((active.selected_figures ?? []).length === 0) {
                setFigureOptions(await fetchFigureOptions(active.id));
                setStep('figures-select');

                return;
            }

            if ((active.quiz_answered_count ?? 0) > 0) {
                await goToNextQuestion(active.id);

                return;
            }

            setReviewFigureIndex(0);
            setStep('figures-review');
        },
        [applySession, goToNextQuestion],
    );

    /**
     * Applies the server state (current session + streak) to the panel.
     */
    const applyServerState = useCallback(
        async ([current, currentStreak]: [
            CurrentSessionResult,
            ReviewStreak,
        ]) => {
            setStreak(currentStreak);
            setError(null);

            if (current.status === 'locked') {
                resetPanelState();
                setStep('locked');

                return;
            }

            if (current.status === 'none') {
                resetPanelState();
                setStep('dashboard');

                return;
            }

            await resumeSession(current.session);
        },
        [resetPanelState, resumeSession],
    );

    const syncWithServer = useCallback(async () => {
        setSyncing(true);

        try {
            await applyServerState(
                await Promise.all([fetchCurrentReviewSession(), fetchStreak()]),
            );
        } catch (err) {
            handleApiError(err);
        } finally {
            setSyncing(false);
        }
    }, [applyServerState, handleApiError]);

    useEffect(() => {
        if (loadReviewAuth() === null) {
            return;
        }

        Promise.all([fetchCurrentReviewSession(), fetchStreak()])
            .then(applyServerState)
            .catch(handleApiError)
            .finally(() => setSyncing(false));
    }, [applyServerState, handleApiError]);

    const isTimedStep = TIMED_STEPS.includes(step);

    useEffect(() => {
        if (!session || !isTimedStep) {
            return;
        }

        const expiresAt = Date.parse(session.expires_at);
        const tick = (): void => {
            const seconds = Math.max(
                0,
                Math.round((expiresAt - Date.now()) / 1000),
            );

            setRemainingSeconds(seconds);

            if (seconds === 0) {
                setStep('expired');
            }
        };

        const interval = window.setInterval(tick, 1000);

        return () => window.clearInterval(interval);
    }, [session, isTimedStep]);

    const run = async (action: () => Promise<void>): Promise<void> => {
        setLoading(true);
        setError(null);

        try {
            await action();
        } catch (err) {
            handleApiError(err);
        } finally {
            setLoading(false);
        }
    };

    const identify = (payload: { email?: string; dni?: string }) =>
        run(async () => {
            const nextAuth = await identifyStudent(payload);
            setAuth(nextAuth);
            await syncWithServer();
        });

    const startReview = () =>
        run(async () => {
            const created = await startReviewSession();
            await resumeSession(created);
        });

    const toggleFigure = (figureId: number) => {
        setSelectedFigureIds((current) => {
            if (current.includes(figureId)) {
                return current.filter((id) => id !== figureId);
            }

            if (current.length >= 2) {
                return current;
            }

            return [...current, figureId];
        });
    };

    const confirmFigureSelection = () =>
        run(async () => {
            if (!session) {
                return;
            }

            if (figureOptions.length === 0) {
                await goToNextQuestion(session.id);

                return;
            }

            if (
                selectedFigureIds.length !== Math.min(2, figureOptions.length)
            ) {
                return;
            }

            const figures = await storeSelectedFigures(
                session.id,
                selectedFigureIds,
            );
            setSelectedFigures(figures);
            setReviewFigureIndex(0);
            setStep('figures-review');
        });

    const continueFigureReview = () =>
        run(async () => {
            const currentFigure = selectedFigures[reviewFigureIndex];

            if (!session || !currentFigure) {
                return;
            }

            await recordFigureView(session.id, currentFigure.id);

            if (reviewFigureIndex < selectedFigures.length - 1) {
                setReviewFigureIndex((index) => index + 1);

                return;
            }

            await goToNextQuestion(session.id);
        });

    const answerQuiz = (optionId: number) =>
        run(async () => {
            if (!session || !quizItem || quizFeedback) {
                return;
            }

            setSelectedOptionId(optionId);

            try {
                const feedback = await submitQuizAnswer(
                    session.id,
                    quizItem.id,
                    optionId,
                );
                setQuizFeedback(feedback);
                setAnsweredCount((count) => count + 1);
            } catch (err) {
                setSelectedOptionId(null);

                throw err;
            }
        });

    const continueQuiz = () =>
        run(async () => {
            if (!session) {
                return;
            }

            await goToNextQuestion(session.id);
        });

    const showSongs = () =>
        run(async () => {
            if (!session) {
                return;
            }

            await goToSongs(session.id);
        });

    const finishSession = () =>
        run(async () => {
            if (!session) {
                return;
            }

            setPreviousStreakCount(streak?.current_streak ?? 0);

            const result = await completeReviewSession(session.id);
            setSession(result.session);
            setStreak(result.streak);
            setStep('complete');
        });

    const signOut = async () => {
        setLoading(true);

        try {
            await logoutReview();
        } catch {
            // The local token is cleared anyway.
        } finally {
            setAuth(null);
            setStreak(null);
            resetPanelState();
            setStep('identify');
            setLoading(false);
            setError(null);
        }
    };

    return {
        auth,
        step,
        syncing,
        session,
        figureOptions,
        selectedFigureIds,
        selectedFigures,
        reviewFigureIndex,
        quizItem,
        quizFeedback,
        selectedOptionId,
        answeredCount,
        songs,
        streak,
        previousStreakCount,
        remainingSeconds,
        loading,
        error,
        identify,
        startReview,
        toggleFigure,
        confirmFigureSelection,
        continueFigureReview,
        answerQuiz,
        continueQuiz,
        showSongs,
        finishSession,
        signOut,
        refresh: syncWithServer,
    };
}
