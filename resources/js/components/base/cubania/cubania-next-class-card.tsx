import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCubaniaNextClass } from '@/components/base/cubania/use-cubania-next-class';
import { usePublicWeeklySchedule } from '@/components/base/cubania/use-public-weekly-schedule';
import { useTranslation } from '@/i18n/use-translation';
import preRegistration from '@/routes/pre-registration';

const WEEKDAY_KEYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
] as const;

function formatClock(hour: string): { time: string; suffix: string } {
    const [hours, minutes] = hour.split(':');
    const parsed = Number.parseInt(hours ?? '', 10);

    if (Number.isNaN(parsed)) {
        return { time: hour, suffix: '' };
    }

    const twelveHour = parsed % 12 || 12;

    return {
        time: `${twelveHour}:${minutes ?? '00'}`,
        suffix: parsed >= 12 ? 'PM' : 'AM',
    };
}

/**
 * Hero conversion card: the next real class on the timetable plus the booking CTA.
 */
export function CubaniaNextClassCard(): ReactNode {
    const { t } = useTranslation();
    const { data, status } = usePublicWeeklySchedule();
    const nextClass = useCubaniaNextClass(data);

    const clock = nextClass ? formatClock(nextClass.hour) : null;
    const weekdayKey = nextClass
        ? (WEEKDAY_KEYS[nextClass.weekday - 1] ?? 'monday')
        : null;
    const subtitle =
        nextClass && nextClass.labels.length > 0
            ? `${nextClass.labels.join(' · ')} · ${t('landing.hero.nextClass.allLevels')}`
            : t('landing.hero.nextClass.fallbackBody');

    return (
        <aside className="cubania-next-class" aria-live="polite">
            <div className="cubania-next-class__label">
                {t('landing.hero.nextClass.label')}
            </div>

            {nextClass && clock && weekdayKey ? (
                <p className="cubania-next-class__when">
                    <span className="cubania-next-class__day">
                        {t(`admin.weekdaysShort.${weekdayKey}`)}
                    </span>
                    <span className="cubania-next-class__time">
                        {clock.time}
                        <span className="cubania-next-class__suffix">
                            {clock.suffix}
                        </span>
                    </span>
                </p>
            ) : (
                <p
                    className={[
                        'cubania-next-class__when',
                        'cubania-next-class__when--placeholder',
                        status === 'loading'
                            ? 'cubania-next-class__when--loading'
                            : '',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                >
                    <span className="cubania-next-class__day">
                        {status === 'loading'
                            ? ' '
                            : t('landing.hero.nextClass.fallbackTitle')}
                    </span>
                </p>
            )}

            <p className="cubania-next-class__what">
                {status === 'loading'
                    ? t('landing.hero.nextClass.loading')
                    : subtitle}
            </p>

            <Link
                href={preRegistration.create.url()}
                className="cubania-btn cubania-btn--primary cubania-next-class__cta"
                data-cubania-cursor="interactive"
            >
                {t('landing.hero.nextClass.cta')}
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 16 16"
                    fill="none"
                    aria-hidden
                >
                    <path
                        d="M3 8H13M13 8L9 4M13 8L9 12"
                        stroke="currentColor"
                        strokeWidth="1.8"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </Link>

            <p className="cubania-next-class__proof">
                {t('landing.hero.nextClass.proof')}
            </p>
        </aside>
    );
}
