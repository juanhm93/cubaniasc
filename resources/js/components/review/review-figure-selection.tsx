import { Check } from 'lucide-react';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewLevelContent } from '@/types/review';

type ReviewFigureSelectionProps = {
    figures: ReviewLevelContent[];
    selectedIds: number[];
    currentLevelId: number | null;
    loading: boolean;
    onToggle: (figureId: number) => void;
    onContinue: () => void;
};

export function ReviewFigureSelection({
    figures,
    selectedIds,
    currentLevelId,
    loading,
    onToggle,
    onContinue,
}: ReviewFigureSelectionProps) {
    const { t } = useTranslation();
    const requiredCount = Math.min(2, figures.length);

    return (
        <section className="cubania-review__panel">
            <h2 className="cubania-review__panel-title">
                {t('review.figureSelection.title', {
                    count: Math.max(requiredCount, 1),
                })}
            </h2>
            <p className="cubania-review__panel-lead">
                {t('review.figureSelection.lead')}
            </p>

            {figures.length === 0 ? (
                <p className="cubania-review__muted">
                    {t('review.figureSelection.empty')}
                </p>
            ) : null}

            <div className="cubania-review__grid">
                {figures.map((figure) => {
                    const selected = selectedIds.includes(figure.id);
                    const fromEarlierLevel =
                        currentLevelId !== null &&
                        figure.level_id !== currentLevelId &&
                        figure.level_name;

                    return (
                        <button
                            key={figure.id}
                            type="button"
                            aria-pressed={selected}
                            className={`cubania-review__card ${selected ? 'cubania-review__card--selected' : ''}`}
                            onClick={() => onToggle(figure.id)}
                            data-cubania-cursor="interactive"
                        >
                            {selected ? (
                                <span
                                    className="cubania-review__card-check"
                                    aria-hidden
                                >
                                    <Check />
                                </span>
                            ) : null}
                            {fromEarlierLevel ? (
                                <span className="cubania-review__card-tag">
                                    {t('review.figureSelection.fromLevel', {
                                        level: figure.level_name,
                                    })}
                                </span>
                            ) : null}
                            <h3 className="cubania-review__card-name">
                                {figure.name}
                            </h3>
                            {figure.description ? (
                                <p className="cubania-review__card-desc">
                                    {figure.description}
                                </p>
                            ) : null}
                        </button>
                    );
                })}
            </div>

            <div className="cubania-review__actions cubania-review__actions--spread">
                <p className="cubania-review__muted">
                    {t('review.figureSelection.counter', {
                        count: selectedIds.length,
                        total: requiredCount,
                    })}
                </p>
                <button
                    type="button"
                    className="cubania-btn cubania-btn--primary"
                    disabled={
                        loading ||
                        (requiredCount > 0 &&
                            selectedIds.length !== requiredCount)
                    }
                    onClick={onContinue}
                    data-cubania-cursor="interactive"
                >
                    {loading
                        ? t('review.figureSelection.saving')
                        : requiredCount === 0
                          ? t('review.figureSelection.skip')
                          : t('review.figureSelection.continue')}
                </button>
            </div>
        </section>
    );
}
