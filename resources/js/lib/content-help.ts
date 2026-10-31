import type { ContentLevel, DanceTypeCard, FigureItem } from '@/types/content';

type FigureFields = Pick<FigureItem, 'description' | 'video_url'> &
    Partial<Pick<FigureItem, 'video_file'>>;

function isBlank(value: string | null | undefined): boolean {
    return (value?.trim() ?? '') === '';
}

/**
 * A figure has a video when it has a YouTube link or its own MP4.
 */
export function figureMissingVideo(figure: FigureFields): boolean {
    return isBlank(figure.video_url) && !figure.video_file?.available;
}

export function figureMissingDescription(figure: FigureFields): boolean {
    return isBlank(figure.description);
}

export function levelsWithoutFigures(levels: ContentLevel[]): ContentLevel[] {
    return levels.filter((level) => level.level_contents.length === 0);
}

export function danceTypeHasPendingContent(danceType: DanceTypeCard): boolean {
    return (
        danceType.levels_count === 0 ||
        danceType.empty_levels_count > 0 ||
        danceType.figures_without_video_count > 0 ||
        danceType.figures_without_description_count > 0
    );
}
