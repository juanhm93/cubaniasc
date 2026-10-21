import { useMemo } from 'react';
import type {
    PublicWeeklySchedule,
    PublicWeeklyScheduleClass,
} from '@/components/base/cubania/use-public-weekly-schedule';

export type CubaniaNextClass = {
    /** ISO weekday, 1 = Monday. */
    weekday: number;
    /** Start hour in `HH:MM` form. */
    hour: string;
    classes: PublicWeeklyScheduleClass[];
};

const MINUTES_PER_DAY = 1440;

function classesKey(classes: PublicWeeklyScheduleClass[]): string {
    return classes
        .map(
            (scheduleClass) =>
                `${scheduleClass.level ?? ''}|${scheduleClass.danceType}`,
        )
        .join('||');
}

/**
 * Start of every contiguous run of occupied hours, so a 6–8pm course only
 * surfaces once instead of one entry per hour of the grid.
 */
function startsOfDay(
    day: PublicWeeklySchedule['days'][number],
): CubaniaNextClass[] {
    const starts: CubaniaNextClass[] = [];
    let previousClasses: string | null = null;

    for (const cell of day.cells) {
        if (!cell.occupied) {
            previousClasses = null;
            continue;
        }

        const classes = classesKey(cell.classes);

        if (classes !== previousClasses) {
            starts.push({
                weekday: day.weekday,
                hour: cell.hour,
                classes: cell.classes,
            });
        }

        previousClasses = classes;
    }

    return starts;
}

function hourToMinutes(hour: string): number {
    const [hours, minutes] = hour.split(':');

    return (
        Number.parseInt(hours ?? '0', 10) * 60 +
        Number.parseInt(minutes ?? '0', 10)
    );
}

function weekMinutes(weekday: number, hour: string): number {
    return (weekday - 1) * MINUTES_PER_DAY + hourToMinutes(hour);
}

/** ISO weekday (1 = Monday, 7 = Sunday) for a `Date`. */
function isoWeekday(date: Date): number {
    return date.getDay() === 0 ? 7 : date.getDay();
}

/**
 * Picks the next upcoming class from the public timetable, wrapping to the
 * start of next week once the current week has no classes left.
 */
export function useCubaniaNextClass(
    schedule: PublicWeeklySchedule | null,
    now: Date = new Date(),
): CubaniaNextClass | null {
    const nowKey = weekMinutes(
        isoWeekday(now),
        `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`,
    );

    return useMemo(() => {
        if (schedule === null) {
            return null;
        }

        const starts = schedule.days
            .flatMap(startsOfDay)
            .sort(
                (a, b) =>
                    weekMinutes(a.weekday, a.hour) -
                    weekMinutes(b.weekday, b.hour),
            );

        if (starts.length === 0) {
            return null;
        }

        const upcoming = starts.find(
            (start) => weekMinutes(start.weekday, start.hour) >= nowKey,
        );

        return upcoming ?? starts[0] ?? null;
    }, [schedule, nowKey]);
}
