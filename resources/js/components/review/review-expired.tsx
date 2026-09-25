import { useTranslation } from '@/i18n/use-translation';

type ReviewExpiredProps = {
    hasSelectedFigures: boolean;
    loading: boolean;
    onShowSongs: () => void;
};

/**
 * The 30 minutes are a window, not a hard stop: if the student already chose figures
 * they can still listen to the songs and finish to count the day.
 */
export function ReviewExpired({
    hasSelectedFigures,
    loading,
    onShowSongs,
}: ReviewExpiredProps) {
    const { t } = useTranslation();

    return (
        <section className="cubania-review__panel">
            <h2 className="cubania-review__panel-title">
                {t('review.expired.title')}
            </h2>
            <p className="cubania-review__panel-lead">
                {hasSelectedFigures
                    ? t('review.expired.lead')
                    : t('review.expired.leadNoFigures')}
            </p>

            {hasSelectedFigures ? (
                <div className="cubania-review__actions">
                    <button
                        type="button"
                        className="cubania-btn cubania-btn--primary"
                        disabled={loading}
                        onClick={onShowSongs}
                        data-cubania-cursor="interactive"
                    >
                        {loading
                            ? t('review.loading')
                            : t('review.expired.toSongs')}
                    </button>
                </div>
            ) : null}
        </section>
    );
}
