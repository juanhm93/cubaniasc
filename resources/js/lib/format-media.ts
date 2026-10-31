const byteFormatter = new Intl.NumberFormat('es', {
    maximumFractionDigits: 1,
});

/**
 * Human size in MB (or KB for tiny files), e.g. "48,2 MB".
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${byteFormatter.format(bytes / 1024)} KB`;
    }

    return `${byteFormatter.format(bytes / (1024 * 1024))} MB`;
}

/**
 * Clock-style duration, e.g. "1:14" or "1:02:05".
 */
export function formatDuration(totalSeconds: number): string {
    const seconds = Math.max(0, Math.round(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = String(seconds % 60).padStart(2, '0');

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}`
        : `${minutes}:${rest}`;
}
