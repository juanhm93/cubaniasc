import { useState } from 'react';

export type VideoSource = 'youtube' | 'file';

const STORAGE_KEY = 'cubania.videoSource';

function readStoredSource(): VideoSource | null {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'youtube' || stored === 'file' ? stored : null;
    } catch {
        return null;
    }
}

function storeSource(source: VideoSource): void {
    try {
        localStorage.setItem(STORAGE_KEY, source);
    } catch {
        // Private mode or blocked storage: the choice lasts until reload.
    }
}

/**
 * Which video to show for a figure. With both sources it starts on YouTube
 * (it adapts to a weak signal) unless the viewer last chose the Cubanía MP4;
 * with only one, that one. `null` when the figure has no video at all.
 */
export function usePreferredVideoSource(
    hasYoutube: boolean,
    hasFile: boolean,
): [VideoSource | null, (source: VideoSource) => void] {
    const [preferred, setPreferred] = useState<VideoSource>(
        () => readStoredSource() ?? 'youtube',
    );

    const choose = (source: VideoSource) => {
        setPreferred(source);
        storeSource(source);
    };

    if (hasYoutube && hasFile) {
        return [preferred, choose];
    }

    if (hasYoutube) {
        return ['youtube', choose];
    }

    return [hasFile ? 'file' : null, choose];
}
