import {
    download,
    library,
    previewUrl,
    streamUrl,
} from '@/actions/App/Http/Controllers/Api/RecommendedSongAudioController';
import apiClient from '@/services/apiClient';

export type SongLibraryFile = {
    key: string;
    name: string;
    size_bytes: number;
    song: { id: number; title: string } | null;
};

export type SongLibrary = {
    folder: string;
    files: SongLibraryFile[];
};

export const getSongLibrary = async (): Promise<SongLibrary> => {
    const response = await apiClient.get(library.url());

    return response.data;
};

export const getSongPreviewUrl = async (key: string): Promise<string> => {
    const response = await apiClient.get(previewUrl.url(), {
        params: { key },
    });

    return response.data.url;
};

export const getSongStreamUrl = async (songId: number): Promise<string> => {
    const response = await apiClient.get(streamUrl.url(songId));

    return response.data.url;
};

/**
 * A plain link: the server redirects it to a short-lived signed download.
 */
export const songDownloadUrl = (songId: number): string => download.url(songId);
