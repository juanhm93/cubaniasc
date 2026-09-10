import { useCallback, useEffect, useState } from 'react';
import { index as scheduleIndex } from '@/routes/schedule';

export type PublicWeeklyScheduleCell = {
    hour: string;
    occupied: boolean;
    labels: string[];
};

export type PublicWeeklyScheduleDay = {
    weekday: number;
    cells: PublicWeeklyScheduleCell[];
};

export type PublicWeeklySchedule = {
    hours: string[];
    days: PublicWeeklyScheduleDay[];
};

export type PublicWeeklyScheduleStatus = 'loading' | 'success' | 'error';

export type UsePublicWeeklyScheduleResult = {
    data: PublicWeeklySchedule | null;
    status: PublicWeeklyScheduleStatus;
    retry: () => void;
};

/**
 * Fetches the public Monday–Friday timetable of active courses.
 */
export function usePublicWeeklySchedule(): UsePublicWeeklyScheduleResult {
    const [data, setData] = useState<PublicWeeklySchedule | null>(null);
    const [status, setStatus] = useState<PublicWeeklyScheduleStatus>('loading');
    const [requestId, setRequestId] = useState(0);

    const retry = useCallback((): void => {
        setStatus('loading');
        setRequestId((current) => current + 1);
    }, []);

    useEffect(() => {
        const controller = new AbortController();

        const load = async (): Promise<void> => {
            try {
                const response = await fetch(scheduleIndex.url(), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('schedule-request-failed');
                }

                const payload = (await response.json()) as PublicWeeklySchedule;
                setData(payload);
                setStatus('success');
            } catch {
                if (controller.signal.aborted) {
                    return;
                }

                setStatus('error');
            }
        };

        void load();

        return () => controller.abort();
    }, [requestId]);

    return { data, status, retry };
}
