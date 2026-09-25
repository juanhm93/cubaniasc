import { formatTimer } from '@/hooks/use-review-panel';
import { useTranslation } from '@/i18n/use-translation';

type ReviewTimerRingProps = {
    remainingSeconds: number;
    totalSeconds: number;
};

const RADIUS = 26;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * Circular countdown: the ring empties as the session window runs out and turns red
 * during the last minute.
 */
export function ReviewTimerRing({
    remainingSeconds,
    totalSeconds,
}: ReviewTimerRingProps) {
    const { t } = useTranslation();
    const progress =
        totalSeconds > 0
            ? Math.min(1, Math.max(0, remainingSeconds / totalSeconds))
            : 0;
    const urgent = remainingSeconds <= 60;

    return (
        <div
            className={`cubania-review__timer-ring ${urgent ? 'cubania-review__timer-ring--urgent' : ''}`}
            role="timer"
            aria-label={t('review.timer.label')}
        >
            <svg viewBox="0 0 64 64" aria-hidden>
                <circle
                    className="cubania-review__timer-track"
                    cx="32"
                    cy="32"
                    r={RADIUS}
                />
                <circle
                    className="cubania-review__timer-progress"
                    cx="32"
                    cy="32"
                    r={RADIUS}
                    strokeDasharray={CIRCUMFERENCE}
                    strokeDashoffset={CIRCUMFERENCE * (1 - progress)}
                />
            </svg>
            <span className="cubania-review__timer-value">
                {formatTimer(remainingSeconds)}
            </span>
        </div>
    );
}
