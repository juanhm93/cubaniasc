export function mapValidationErrors(
    data: unknown,
): Record<string, string> | null {
    if (!data || typeof data !== 'object' || !('errors' in data)) {
        return null;
    }

    const raw = (data as { errors?: Record<string, string[]> }).errors;

    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const out: Record<string, string> = {};

    for (const [key, messages] of Object.entries(raw)) {
        if (Array.isArray(messages) && messages[0]) {
            out[key] = messages[0];
        }
    }

    return Object.keys(out).length ? out : null;
}
