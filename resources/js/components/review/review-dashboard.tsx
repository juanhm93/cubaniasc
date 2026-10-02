import { Clock, CircleHelp, Footprints, Music } from 'lucide-react';
import { ReviewStreakCard } from '@/components/review/review-streak-card';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewAuth, ReviewStreak } from '@/types/review';

type ReviewDashboardProps = {
    auth: ReviewAuth;
    streak: ReviewStreak | null;
    loading: boolean;
    onStart: () => void;
};

/**
 * Landing screen once the student is identified: streak, what today's session has
 * and the button that actually starts the clock.
 */
export function ReviewDashboard({
    auth,
    streak,
    loading,
    onStart,
}: ReviewDashboardProps) {
    const { t } = useTranslation();
    const minutes = Math.round(auth.level.review_duration_seconds / 60);
    const firstName = auth.student.name.split(' ')[0];

    return (
        <section className="cubania-review__dashboard">
            <div className="cubania-review__dashboard-intro">
                <span className="cubania-review__level-chip">
                    {auth.level.name}
                </span>
                <h2 className="cubania-review__dashboard-title">
                    {t('review.dashboard.greeting', { name: firstName })}
                </h2>
                <p className="cubania-review__panel-lead">
                    {t('review.dashboard.lead', { minutes })}
                </p>

                <ul className="cubania-review__features">
                    <li>
                        <Clock aria-hidden />
                        {t('review.features.minutes', { count: minutes })}
                    </li>
                    <li>
                        <Footprints aria-hidden />
                        {t('review.features.figures')}
                    </li>
                    <li>
                        <CircleHelp aria-hidden />
                        {t('review.features.quiz')}
                    </li>
                    <li>
                        <Music aria-hidden />
                        {t('review.features.songs')}
                    </li>
                </ul>

                <button
                    type="button"
                    className="cubania-btn cubania-btn--primary cubania-review__start"
                    disabled={loading}
                    onClick={onStart}
                    data-cubania-cursor="interactive"
                >
                    {loading
                        ? t('review.dashboard.starting')
                        : t('review.dashboard.start')}
                </button>
            </div>

            <ReviewStreakCard streak={streak} />
        </section>
    );
}
