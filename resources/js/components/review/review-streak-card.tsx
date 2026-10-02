import { Flame } from 'lucide-react';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewStreak } from '@/types/review';

const weekdayFormatter = new Intl.DateTimeFormat('es', {
    weekday: 'narrow',
    timeZone: 'UTC',
});

type ReviewStreakCardProps = {
    streak: ReviewStreak | null;
    /** Plays the "+1" celebration when the streak just grew. */
    celebrate?: boolean;
};

/**
 * Big flame with the current streak and the last seven days as dots.
 */
export function ReviewStreakCard({
    streak,
    celebrate = false,
}: ReviewStreakCardProps) {
    const { t } = useTranslation();
    const count = streak?.current_streak ?? 0;

    return (
        <div
            className={`cubania-review__streak-card ${count > 0 ? 'cubania-review__streak-card--alive' : ''}`}
        >
            <div className="cubania-review__streak-main">
                <Flame className="cubania-review__streak-flame" aria-hidden />
                <span
                    key={count}
                    className={`cubania-review__streak-count ${celebrate ? 'cubania-review__streak-count--pop' : ''}`}
                >
                    {count}
                </span>
                {celebrate ? (
                    <span className="cubania-review__streak-plus" aria-hidden>
                        +1
                    </span>
                ) : null}
            </div>
            <p className="cubania-review__streak-label">
                {count > 0
                    ? t('review.dashboard.streakLabel', { count })
                    : t('review.dashboard.streakEmpty')}
            </p>

            {streak && streak.recent_days.length > 0 ? (
                <div className="cubania-review__week">
                    <p className="cubania-review__week-title">
                        {t('review.dashboard.week')}
                    </p>
                    <ol className="cubania-review__week-days">
                        {streak.recent_days.map((day) => (
                            <li
                                key={day.date}
                                className={`cubania-review__week-day ${day.completed ? 'cubania-review__week-day--done' : ''}`}
                            >
                                <span
                                    className="cubania-review__week-dot"
                                    aria-hidden
                                />
                                <span className="cubania-review__week-name">
                                    {weekdayFormatter.format(
                                        new Date(`${day.date}T12:00:00Z`),
                                    )}
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            ) : null}
        </div>
    );
}
