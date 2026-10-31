import {
    destroy,
    download,
    library,
    link,
    previewUrl,
    store,
    streamUrl,
    uploadUrl,
} from '@/actions/App/Http/Controllers/Api/LevelContentVideoController';
import { show as levelVideoLibraryShow } from '@/actions/App/Http/Controllers/Api/LevelVideoLibraryController';
import apiClient from '@/services/apiClient';
import { normalizeFigure } from '@/types/content';
import type { FigureItem } from '@/types/content';

export type FigureVideoUploadTicket = {
    key: string;
    url: string;
    headers: Record<string, string>;
    expires_at: string;
};

export type FigureVideoStreamUrl = {
    url: string;
    expires_at: string;
};

export type FigureVideoLibraryFile = {
    key: string;
    name: string;
    size_bytes: number;
    linked_to: { id: number; name: string }[];
};

export type FigureVideoLibrary = {
    folder: string;
    folders: string[];
    files: FigureVideoLibraryFile[];
};

export type LevelVideoLibraryFile = FigureVideoLibraryFile & {
    suggested_figure_id: number | null;
    match: 'exact' | 'similar' | null;
};

export type BrokenVideoLink = {
    figure_id: number;
    name: string;
    key: string;
    reason: 'missing' | 'size_changed';
};

export type LevelVideoLibrary = {
    folder: string;
    files: LevelVideoLibraryFile[];
    broken_links: BrokenVideoLink[];
};

/**
 * Without `folder` the server opens the level's folder; `''` is the bucket's root.
 */
export const getFigureVideoLibrary = async (
    figureId: number,
    folder?: string,
): Promise<FigureVideoLibrary> => {
    const response = await apiClient.get(library.url(figureId), {
        params: { folder },
    });

    return response.data;
};

export const getFigureVideoPreviewUrl = async (
    figureId: number,
    key: string,
): Promise<FigureVideoStreamUrl> => {
    const response = await apiClient.get(previewUrl.url(figureId), {
        params: { key },
    });

    return response.data;
};

export const linkFigureVideo = async (
    figureId: number,
    key: string,
    durationSeconds: number | null,
): Promise<FigureItem> => {
    const response = await apiClient.post(link.url(figureId), {
        key,
        duration_seconds: durationSeconds,
    });

    return normalizeFigure(response.data);
};

export const getLevelVideoLibrary = async (
    levelId: number,
): Promise<LevelVideoLibrary> => {
    const response = await apiClient.get(levelVideoLibraryShow.url(levelId));

    return response.data;
};

export const requestFigureVideoUpload = async (
    figureId: number,
    sizeBytes: number,
): Promise<FigureVideoUploadTicket> => {
    const response = await apiClient.post(uploadUrl.url(figureId), {
        content_type: 'video/mp4',
        size_bytes: sizeBytes,
    });

    return response.data;
};

export const confirmFigureVideo = async (
    figureId: number,
    key: string,
    durationSeconds: number | null,
): Promise<FigureItem> => {
    const response = await apiClient.post(store.url(figureId), {
        key,
        duration_seconds: durationSeconds,
    });

    return normalizeFigure(response.data);
};

export const removeFigureVideo = async (
    figureId: number,
): Promise<FigureItem> => {
    const response = await apiClient.delete(destroy.url(figureId));

    return normalizeFigure(response.data);
};

export const getFigureVideoStreamUrl = async (
    figureId: number,
): Promise<FigureVideoStreamUrl> => {
    const response = await apiClient.get(streamUrl.url(figureId));

    return response.data;
};

/**
 * A plain link: the server redirects it to a short-lived signed download.
 */
export const figureVideoDownloadUrl = (figureId: number): string =>
    download.url(figureId);
