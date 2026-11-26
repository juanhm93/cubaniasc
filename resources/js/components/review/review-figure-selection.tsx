import { Check } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewLevelContent } from '@/types/review';

type ReviewFigureSelectionProps = {
    figures: ReviewLevelContent[];
    selectedIds: number[];
    currentLevelId: number | null;
    /** Figures the student must choose, set by their course. */
    selectionCount: number;
    /** The options come grouped by level: show one tab per level. */
    byLevel: boolean;
    loading: boolean;
    onToggle: (figureId: number) => void;
    onContinue: () => void;
};

type FigureLevelGroup = {
    levelId: number;
    levelName: string;
    figures: ReviewLevelContent[];
};

/**
 * Groups the options by level keeping the order the server sent them in (current level
 * first, then the closest earlier levels).
 */
function groupByLevel(figures: ReviewLevelContent[]): FigureLevelGroup[] {
    const groups = new Map<number, FigureLevelGroup>();

    for (const figure of figures) {
        const group = groups.get(figure.level_id);

        if (group) {
            group.figures.push(figure);
        } else {
            groups.set(figure.level_id, {
                levelId: figure.level_id,
                levelName: figure.level_name ?? '',
                figures: [figure],
            });
        }
    }

    return [...groups.values()];
}

export function ReviewFigureSelection({
    figures,
    selectedIds,
    currentLevelId,
    selectionCount,
    byLevel,
    loading,
    onToggle,
    onContinue,
}: ReviewFigureSelectionProps) {
    const { t } = useTranslation();
    const requiredCount = Math.min(selectionCount, figures.length);
    const levelGroups = useMemo(() => groupByLevel(figures), [figures]);
    const showTabs = byLevel && levelGroups.length > 1;
    const [activeLevelId, setActiveLevelId] = useState<number | null>(null);
    const activeGroup =
        levelGroups.find((group) => group.levelId === activeLevelId) ??
        levelGroups[0];
    const visibleFigures = showTabs ? (activeGroup?.figures ?? []) : figures;

    return (
        <section className="cubania-review__panel">
            <h2 className="cubania-review__panel-title">
                {t('review.figureSelection.title', {
                    count: Math.max(requiredCount, 1),
                })}
            </h2>
            <p className="cubania-review__panel-lead">
                {showTabs
                    ? t('review.figureSelection.leadByLevel')
                    : t('review.figureSelection.lead')}
            </p>

            {figures.length === 0 ? (
                <p className="cubania-review__muted">
                    {t('review.figureSelection.empty')}
                </p>
            ) : null}

            {showTabs ? (
                <div
                    className="cubania-review__tabs"
                    role="tablist"
                    aria-label={t('review.figureSelection.levelTabs')}
                >
                    {levelGroups.map((group) => {
                        const active = group.levelId === activeGroup?.levelId;
                        const selectedInLevel = group.figures.filter((figure) =>
                            selectedIds.includes(figure.id),
                        ).length;

                        return (
                            <button
                                key={group.levelId}
                                type="button"
                                role="tab"
                                id={`review-level-tab-${group.levelId}`}
                                aria-selected={active}
                                aria-controls="review-level-panel"
                                className={`cubania-review__tab ${active ? 'cubania-review__tab--active' : ''}`}
                                onClick={() => setActiveLevelId(group.levelId)}
                                data-cubania-cursor="interactive"
                            >
                                <span>{group.levelName}</span>
                                {group.levelId === currentLevelId ? (
                                    <span className="cubania-review__tab-note">
                                        {t('review.figureSelection.yourLevel')}
                                    </span>
                                ) : null}
                                {selectedInLevel > 0 ? (
                                    <span className="cubania-review__tab-count">
                                        {selectedInLevel}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </div>
            ) : null}

            <div
                className="cubania-review__grid"
                {...(showTabs
                    ? {
                          id: 'review-level-panel',
                          role: 'tabpanel',
                          'aria-labelledby': `review-level-tab-${activeGroup?.levelId}`,
                      }
                    : {})}
            >
                {visibleFigures.map((figure) => {
                    const selected = selectedIds.includes(figure.id);
                    const fromEarlierLevel =
                        !showTabs &&
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
