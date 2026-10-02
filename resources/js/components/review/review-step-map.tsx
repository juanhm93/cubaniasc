import { CircleHelp, Footprints, Music, Trophy } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/i18n/use-translation';
import type { ReviewStep } from '@/types/review';

type MapStep = {
    key: string;
    label: string;
    icon: LucideIcon;
    steps: ReviewStep[];
};

const MAP_STEPS: MapStep[] = [
    {
        key: 'figures',
        label: 'review.steps.figures',
        icon: Footprints,
        steps: ['figures-select', 'figures-review'],
    },
    {
        key: 'quiz',
        label: 'review.steps.quiz',
        icon: CircleHelp,
        steps: ['quiz'],
    },
    {
        key: 'songs',
        label: 'review.steps.songs',
        icon: Music,
        steps: ['songs', 'expired'],
    },
    {
        key: 'done',
        label: 'review.steps.done',
        icon: Trophy,
        steps: ['complete'],
    },
];

type ReviewStepMapProps = {
    step: ReviewStep;
};

/**
 * Path of the session (figures → quiz → music → done) with the current stop highlighted.
 */
export function ReviewStepMap({ step }: ReviewStepMapProps) {
    const { t } = useTranslation();
    const currentIndex = Math.max(
        0,
        MAP_STEPS.findIndex((mapStep) => mapStep.steps.includes(step)),
    );

    return (
        <ol className="cubania-review__step-map">
            {MAP_STEPS.map((mapStep, index) => {
                const Icon = mapStep.icon;
                const state =
                    index < currentIndex
                        ? 'done'
                        : index === currentIndex
                          ? 'active'
                          : 'pending';

                return (
                    <li
                        key={mapStep.key}
                        className={`cubania-review__step-map-item cubania-review__step-map-item--${state}`}
                        aria-current={state === 'active' ? 'step' : undefined}
                    >
                        <span className="cubania-review__step-map-icon">
                            <Icon aria-hidden />
                        </span>
                        <span className="cubania-review__step-map-label">
                            {t(mapStep.label)}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
