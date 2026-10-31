import axios from 'axios';
import type { TFunction } from 'i18next';
import { mapValidationErrors } from '@/lib/map-validation-errors';

/**
 * What to tell staff when a figure video request fails: the server's own validation
 * message, the storage outage notice, or the given fallback.
 */
export function figureVideoFailureMessage(
    error: unknown,
    t: TFunction,
    fallback: string,
): string {
    if (axios.isAxiosError(error)) {
        if (error.response?.status === 422) {
            const mapped = mapValidationErrors(error.response.data);
            const first = mapped ? Object.values(mapped)[0] : undefined;

            if (first) {
                return first;
            }
        }

        if (error.response?.status === 503) {
            return t('content.figureVideo.errors.unavailable');
        }
    }

    return fallback;
}
