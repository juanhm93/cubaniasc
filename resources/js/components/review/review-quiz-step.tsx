import { useTranslation } from '@/i18n/use-translation';
import type { QuizFeedback, ReviewQuizItem } from '@/types/review';

type ReviewQuizStepProps = {
    item: ReviewQuizItem | null;
    feedback: QuizFeedback | null;
    selectedOptionId: number | null;
    answeredCount: number;
    totalQuestions: number;
    loading: boolean;
    onAnswer: (optionId: number) => void;
    onContinue: () => void;
};

export function ReviewQuizStep({
    item,
    feedback,
    selectedOptionId,
    answeredCount,
    totalQuestions,
    loading,
    onAnswer,
    onContinue,
}: ReviewQuizStepProps) {
    const { t } = useTranslation();

    if (!item) {
        return (
            <section className="cubania-review__panel">
                <p className="cubania-review__panel-lead">
                    {t('review.quiz.loading')}
                </p>
            </section>
        );
    }

    const questionNumber = Math.min(
        feedback ? answeredCount : answeredCount + 1,
        totalQuestions,
    );
    const progressPercent =
        totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;

    const optionState = (optionId: number): string => {
        if (!feedback) {
            return '';
        }

        if (optionId === feedback.correct_option_id) {
            return 'cubania-review__quiz-option--correct';
        }

        if (optionId === selectedOptionId && !feedback.is_correct) {
            return 'cubania-review__quiz-option--incorrect';
        }

        return 'cubania-review__quiz-option--dimmed';
    };

    return (
        <section className="cubania-review__panel">
            <div className="cubania-review__quiz-header">
                <p className="cubania-review__muted">
                    {item.type === 'fun_fact'
                        ? t('review.quiz.funFact')
                        : t('review.quiz.figure')}
                </p>
                <p className="cubania-review__muted">
                    {t('review.quiz.counter', {
                        current: questionNumber,
                        total: totalQuestions,
                    })}
                </p>
            </div>
            <div className="cubania-review__quiz-progress" aria-hidden>
                <span style={{ width: `${progressPercent}%` }} />
            </div>

            <h2 className="cubania-review__quiz-prompt">{item.prompt}</h2>

            <div className="cubania-review__quiz-options">
                {item.options.map((option) => (
                    <button
                        key={option.id}
                        type="button"
                        className={`cubania-review__quiz-option ${optionState(option.id)}`}
                        disabled={loading || feedback !== null}
                        onClick={() => onAnswer(option.id)}
                        data-cubania-cursor="interactive"
                    >
                        {option.description}
                    </button>
                ))}
            </div>

            {feedback ? (
                <div
                    className={`cubania-review__quiz-feedback ${feedback.is_correct ? 'cubania-review__quiz-feedback--correct' : 'cubania-review__quiz-feedback--incorrect'}`}
                    role="status"
                >
                    <p>
                        {feedback.is_correct
                            ? t('review.quiz.correct')
                            : t('review.quiz.incorrect')}
                    </p>
                    <button
                        type="button"
                        className="cubania-btn cubania-btn--primary"
                        disabled={loading}
                        onClick={onContinue}
                        data-cubania-cursor="interactive"
                    >
                        {loading ? t('review.loading') : t('review.quiz.next')}
                    </button>
                </div>
            ) : null}
        </section>
    );
}
