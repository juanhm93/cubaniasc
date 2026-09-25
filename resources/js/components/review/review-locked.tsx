import { useEffect, useState } from 'react';
import { ReviewStreakCard } from '@/components/review/review-streak-card';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewStreak } from '@/types/review';

function secondsUntil(targetIso: string | undefined): number {
    if (!targetIso) {
        return 0;
    }

    return Math.max(0, Math.round((Date.parse(targetIso) - Date.now()) / 1000));
}

function formatCountdown(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [hours, minutes, seconds]
        .map((part) => part.toString().padStart(2, '0'))
        .join(':');
}

type ReviewLockedProps = {
    streak: ReviewStreak | null;
    onUnlocked: () => void;
};

/**
 * Shown when today's review was already used: streak plus a countdown to the next
 * local day, when the panel checks the server again.
 */
export function ReviewLocked({ streak, onUnlocked }: ReviewLockedProps) {
    const { t } = useTranslation();
    const target = streak?.next_day_starts_at;
    const [remaining, setRemaining] = useState(() => secondsUntil(target));

    useEffect(() => {
        if (!target) {
            return;
        }

        const interval = window.setInterval(() => {
            const seconds = secondsUntil(target);
            setRemaining(seconds);

            if (seconds === 0) {
                window.clearInterval(interval);
                onUnlocked();
            }
        }, 1000);

        return () => window.clearInterval(interval);
    }, [target, onUnlocked]);

    return (
        <section className="cubania-review__dashboard">
            <div className="cubania-review__dashboard-intro">
                <h2 className="cubania-review__dashboard-title">
                    {t('review.locked.title')}
                </h2>
                <p className="cubania-review__panel-lead">
                    {t('review.locked.lead')}
                </p>
                <p
                    className="cubania-review__countdown"
                    role="timer"
                    aria-live="off"
                >
                    {formatCountdown(remaining)}
                </p>
                <p className="cubania-review__muted">
                    {t('review.locked.streakHint')}
                </p>
            </div>

            <ReviewStreakCard streak={streak} />
        </section>
    );
}
