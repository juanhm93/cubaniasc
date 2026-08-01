import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ReviewApiError,
  clearReviewAuth,
  completeReviewSession,
  createOrResumeReviewSession,
  fetchFigureOptions,
  fetchNextQuizQuestion,
  fetchReviewSession,
  fetchSessionSongs,
  identifyStudent,
  loadReviewAuth,
  logoutReview,
  recordFigureView,
  storeSelectedFigures,
  submitQuizAnswer,
} from '@/lib/review-service';
import type {
  QuizFeedback,
  ReviewAuth,
  ReviewLevelContent,
  ReviewQuizItem,
  ReviewSession,
  ReviewSong,
  ReviewStep,
  ReviewStreak,
} from '@/types/review';

function formatTimer(seconds: number): string {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;

  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export function useReviewPanel() {
  const [auth, setAuth] = useState<ReviewAuth | null>(() => loadReviewAuth());
  const [step, setStep] = useState<ReviewStep>(() => (loadReviewAuth() ? 'figures-select' : 'identify'));
  const [session, setSession] = useState<ReviewSession | null>(null);
  const [figureOptions, setFigureOptions] = useState<ReviewLevelContent[]>([]);
  const [selectedFigureIds, setSelectedFigureIds] = useState<number[]>([]);
  const [reviewFigureIndex, setReviewFigureIndex] = useState(0);
  const [selectedFigures, setSelectedFigures] = useState<ReviewLevelContent[]>([]);
  const [quizItem, setQuizItem] = useState<ReviewQuizItem | null>(null);
  const [quizFeedback, setQuizFeedback] = useState<QuizFeedback | null>(null);
  const [selectedOptionId, setSelectedOptionId] = useState<number | null>(null);
  const [songs, setSongs] = useState<ReviewSong[]>([]);
  const [streak, setStreak] = useState<ReviewStreak | null>(null);
  const [remainingSeconds, setRemainingSeconds] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [dailyLocked, setDailyLocked] = useState(false);

  const isExpired = Boolean(session?.expired) || remainingSeconds <= 0;

  const selectedFiguresForReview = useMemo(
    () => figureOptions.filter((figure) => selectedFigureIds.includes(figure.id)),
    [figureOptions, selectedFigureIds],
  );

  const resetPanelState = useCallback(() => {
    setSession(null);
    setFigureOptions([]);
    setSelectedFigureIds([]);
    setSelectedFigures([]);
    setReviewFigureIndex(0);
    setQuizItem(null);
    setQuizFeedback(null);
    setSelectedOptionId(null);
    setSongs([]);
    setStreak(null);
    setRemainingSeconds(0);
  }, []);

  const handleApiError = useCallback((err: unknown) => {
    if (err instanceof ReviewApiError) {
      if (err.status === 401) {
        clearReviewAuth();
        setAuth(null);
        resetPanelState();
        setDailyLocked(false);
        setStep('identify');
      }

      if (err.status === 409) {
        setDailyLocked(true);
        setStep('identify');
        resetPanelState();
      }

      setError(err.message);

      return;
    }

    setError('Ocurrió un error inesperado. Intenta de nuevo.');
  }, [resetPanelState]);

  const bootstrapSession = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const created = await createOrResumeReviewSession();
      setSession(created);
      setRemainingSeconds(created.remaining_seconds);
      setDailyLocked(false);

      const options = await fetchFigureOptions(created.id);
      setFigureOptions(options);
      setStep('figures-select');
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  }, [handleApiError]);

  useEffect(() => {
    if (auth && !session && step !== 'identify' && !dailyLocked) {
      void bootstrapSession();
    }
  }, [auth, session, step, dailyLocked, bootstrapSession]);

  useEffect(() => {
    if (!session || isExpired) {
      return;
    }

    const interval = window.setInterval(() => {
      setRemainingSeconds((current) => {
        if (current <= 1) {
          void fetchReviewSession(session.id)
            .then((fresh) => {
              setSession(fresh);
              setRemainingSeconds(fresh.remaining_seconds);
            })
            .catch(() => undefined);

          return 0;
        }

        return current - 1;
      });
    }, 1000);

    return () => window.clearInterval(interval);
  }, [session, isExpired]);

  const identify = async (payload: { email?: string; dni?: string }) => {
    setLoading(true);
    setError(null);
    setDailyLocked(false);

    try {
      const nextAuth = await identifyStudent(payload);
      setAuth(nextAuth);
      setStep('figures-select');
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  };

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

  const confirmFigureSelection = async () => {
    if (!session || selectedFigureIds.length !== 2) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await storeSelectedFigures(session.id, selectedFigureIds);
      const figures = figureOptions.filter((figure) => selectedFigureIds.includes(figure.id));
      setSelectedFigures(figures);
      setReviewFigureIndex(0);
      setStep('figures-review');
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  };

  const goToSongs = async () => {
    if (!session) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const sessionSongs = await fetchSessionSongs(session.id);
      setSongs(sessionSongs);
      setStep('songs');
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  };

  const continueFigureReview = async () => {
    if (!session) {
      return;
    }

    const currentFigure = selectedFigures[reviewFigureIndex];

    setLoading(true);
    setError(null);

    try {
      await recordFigureView(session.id, currentFigure.id);

      if (reviewFigureIndex < selectedFigures.length - 1) {
        setReviewFigureIndex((index) => index + 1);
      } else {
        const question = await fetchNextQuizQuestion(session.id);

        if (question === null) {
          await goToSongs();

          return;
        }

        setQuizItem(question);
        setStep('quiz');
      }
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  };

  const answerQuiz = async (optionId: number) => {
    if (!session || !quizItem || quizFeedback) {
      return;
    }

    setSelectedOptionId(optionId);
    setLoading(true);
    setError(null);

    try {
      const feedback = await submitQuizAnswer(session.id, quizItem.id, optionId);
      setQuizFeedback(feedback);
    } catch (err) {
      handleApiError(err);
      setSelectedOptionId(null);
    } finally {
      setLoading(false);
    }
  };

  const continueQuiz = async () => {
    if (!session) {
      return;
    }

    if (isExpired) {
      await goToSongs();

      return;
    }

    setLoading(true);
    setError(null);
    setQuizFeedback(null);
    setSelectedOptionId(null);

    try {
      const question = await fetchNextQuizQuestion(session.id);

      if (question === null) {
        await goToSongs();

        return;
      }

      setQuizItem(question);
    } catch (err) {
      if (err instanceof ReviewApiError && (err.status === 410 || err.status === 404)) {
        await goToSongs();

        return;
      }

      handleApiError(err);
    } finally {
      setLoading(false);
    }
  };

  const finishSession = async () => {
    if (!session) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const result = await completeReviewSession(session.id);
      setSession(result.session);
      setStreak(result.streak);
      setStep('complete');
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  };

  const signOut = async () => {
    setLoading(true);

    try {
      await logoutReview();
    } finally {
      setAuth(null);
      resetPanelState();
      setDailyLocked(false);
      setStep('identify');
      setLoading(false);
      setError(null);
    }
  };

  const restart = () => {
    resetPanelState();
    setDailyLocked(false);
    setStep('figures-select');
    void bootstrapSession();
  };

  return {
    auth,
    step,
    session,
    figureOptions,
    selectedFigureIds,
    selectedFiguresForReview,
    reviewFigureIndex,
    quizItem,
    quizFeedback,
    selectedOptionId,
    songs,
    streak,
    remainingSeconds,
    formattedTimer: formatTimer(Math.max(0, remainingSeconds)),
    isExpired,
    loading,
    error,
    dailyLocked,
    identify,
    toggleFigure,
    confirmFigureSelection,
    continueFigureReview,
    answerQuiz,
    continueQuiz,
    goToSongs,
    finishSession,
    signOut,
    restart,
    setStep,
  };
}
