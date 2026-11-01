const TRACK_NUMBER = /^\d+\s*[-.]?\s+/;
const VIDEO_LABEL = /\s*[([](?:official|oficial|cover)[^)\]]*[)\]]/gi;
const ARTIST_SEPARATOR = /\s+[-–—·]\s*/;

/**
 * Bucket names may come in Unicode NFD: what people read goes in NFC. The key itself
 * must never pass through here, or it would stop matching its object.
 */
export function readableFileName(fileName: string): string {
    return fileName.normalize('NFC');
}

/**
 * Title and artist guessed from a file name like `01 - Artist - Title (Official
 * Video).mp3`, to prefill the form. Many names carry no artist (`01 - Track 1.mp3`):
 * those only give a title, and staff fills in the rest.
 */
export function suggestSongFields(fileName: string): {
    title: string;
    artist: string;
} {
    const name = readableFileName(fileName)
        .replace(/\.[^.]+$/, '')
        .replace(/_/g, ' ')
        .replace(VIDEO_LABEL, '')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(TRACK_NUMBER, '')
        .replace(/^[-–—·]\s+/, '');

    const separator = ARTIST_SEPARATOR.exec(name);

    if (separator === null) {
        return { title: name, artist: '' };
    }

    return {
        title: name.slice(separator.index + separator[0].length),
        artist: name.slice(0, separator.index),
    };
}

/**
 * Lets a search ignore case and accents, whichever Unicode form the name is in.
 */
export function searchableText(text: string): string {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}
