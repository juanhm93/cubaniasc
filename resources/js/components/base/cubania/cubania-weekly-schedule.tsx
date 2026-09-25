import type { ReactNode } from 'react';
import { usePublicWeeklySchedule } from '@/components/base/cubania/use-public-weekly-schedule';
import type { PublicWeeklySchedule } from '@/components/base/cubania/use-public-weekly-schedule';
import { useTranslation } from '@/i18n/use-translation';

const WEEKDAY_KEYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
] as const;

const SKELETON_HOURS = [
    '16:00',
    '17:00',
    '18:00',
    '19:00',
    '20:00',
    '21:00',
] as const;

function formatHourLabel(hour: string): string {
    const parsed = Number.parseInt(hour.slice(0, 2), 10);

    if (Number.isNaN(parsed)) {
        return hour;
    }

    const twelveHour = parsed % 12 || 12;
    const suffix = parsed >= 12 ? 'pm' : 'am';

    return `${twelveHour}${suffix}`;
}

function weekdayKey(weekday: number): (typeof WEEKDAY_KEYS)[number] {
    return WEEKDAY_KEYS[weekday - 1] ?? 'monday';
}

function cellForHour(
    day: PublicWeeklySchedule['days'][number] | { weekday: number },
    hour: string,
): PublicWeeklySchedule['days'][number]['cells'][number] | null {
    if (!('cells' in day)) {
        return null;
    }

    return day.cells.find((cell) => cell.hour === hour) ?? null;
}

function ScheduleTable({
    hours,
    days,
}: {
    hours: readonly string[];
    days: PublicWeeklySchedule['days'] | Array<{ weekday: number }>;
}): ReactNode {
    const { t } = useTranslation();

    return (
        <table className="cubania-week-grid">
            <caption className="cubania-sr-only">
                {t('landing.cta.scheduleCaption')}
            </caption>
            <thead>
                <tr>
                    <th className="cubania-week-grid__corner" scope="col">
                        <span className="cubania-sr-only">
                            {t('landing.cta.scheduleHourColumn')}
                        </span>
                    </th>
                    {days.map((day) => (
                        <th
                            key={day.weekday}
                            className="cubania-week-grid__day"
                            scope="col"
                        >
                            {t(
                                `admin.weekdaysShort.${weekdayKey(day.weekday)}`,
                            )}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {hours.map((hour) => (
                    <tr key={hour}>
                        <th className="cubania-week-grid__hour" scope="row">
                            {formatHourLabel(hour)}
                        </th>
                        {days.map((day) => {
                            const cell = cellForHour(day, hour);

                            if (cell === null) {
                                return (
                                    <td
                                        key={`${day.weekday}-${hour}`}
                                        className="cubania-week-grid__cell cubania-week-grid__cell--loading"
                                    />
                                );
                            }

                            return (
                                <td
                                    key={`${day.weekday}-${hour}`}
                                    className={[
                                        'cubania-week-grid__cell',
                                        cell.occupied
                                            ? 'cubania-week-grid__cell--occupied'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    title={cell.labels.join(' · ')}
                                >
                                    {cell.labels.join(' · ')}
                                </td>
                            );
                        })}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/**
 * Public weekly timetable of occupied hours for active courses.
 */
export function CubaniaWeeklySchedule(): ReactNode {
    const { t } = useTranslation();
    const { data, status, retry } = usePublicWeeklySchedule();

    return (
        <div
            className="cubania-cta-band__schedule"
            id="horarios"
            aria-busy={status === 'loading'}
            aria-live="polite"
        >
            {status === 'error' ? (
                <div className="cubania-cta-band__schedule-status">
                    <p>{t('landing.cta.scheduleError')}</p>
                    <button
                        type="button"
                        className="cubania-btn cubania-btn--outline-dark"
                        onClick={retry}
                        data-cubania-cursor="interactive"
                    >
                        {t('landing.cta.scheduleRetry')}
                    </button>
                </div>
            ) : null}
            {status === 'loading' ? (
                <>
                    <p className="cubania-sr-only">
                        {t('landing.cta.scheduleLoading')}
                    </p>
                    <ScheduleTable
                        hours={SKELETON_HOURS}
                        days={WEEKDAY_KEYS.map((_, index) => ({
                            weekday: index + 1,
                        }))}
                    />
                </>
            ) : null}
            {status === 'success' && data !== null ? (
                <ScheduleTable hours={data.hours} days={data.days} />
            ) : null}
        </div>
    );
}
