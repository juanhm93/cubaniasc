import { youtubeWatchUrlToEmbedUrl } from '@/components/base/cubania/youtube-embed-url';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewLevelContent } from '@/types/review';

type ReviewFigureReviewProps = {
    figure: ReviewLevelContent;
    index: number;
    total: number;
    loading: boolean;
    onContinue: () => void;
};

export function ReviewFigureReview({
    figure,
    index,
    total,
    loading,
    onContinue,
}: ReviewFigureReviewProps) {
    const { t } = useTranslation();
    const embedUrl =
        figure.video_url && figure.video_url.includes('youtu')
            ? youtubeWatchUrlToEmbedUrl(figure.video_url)
            : null;

    return (
        <section className="cubania-review__panel">
            <p className="cubania-review__muted">
                {t('review.figureReview.counter', {
                    current: index + 1,
                    total,
                })}
                {figure.level_name ? ` · ${figure.level_name}` : ''}
            </p>
            <h2 className="cubania-review__panel-title">{figure.name}</h2>
            {figure.description ? (
                <p className="cubania-review__panel-lead">
                    {figure.description}
                </p>
            ) : null}

            {embedUrl ? (
                <div className="cubania-review__video">
                    <iframe
                        src={embedUrl}
                        title={figure.name}
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                    />
                </div>
            ) : null}

            <div className="cubania-review__actions">
                <button
                    type="button"
                    className="cubania-btn cubania-btn--primary"
                    disabled={loading}
                    onClick={onContinue}
                    data-cubania-cursor="interactive"
                >
                    {loading
                        ? t('review.figureReview.saving')
                        : index < total - 1
                          ? t('review.figureReview.next')
                          : t('review.figureReview.toQuiz')}
                </button>
            </div>
        </section>
    );
}
