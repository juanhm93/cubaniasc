export type FigureItem = {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    video_url: string | null;
};

export type ContentLevel = {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    level_contents: FigureItem[];
};

export type DanceTypeCard = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    levels_count: number;
    figures_count: number;
};

export type DanceTypeDetail = DanceTypeCard & {
    levels: ContentLevel[];
};

export function normalizeFigure(raw: unknown): FigureItem {
    const figure = raw as Partial<FigureItem>;

    return {
        id: figure.id ?? 0,
        name: figure.name ?? '',
        description: figure.description ?? null,
        sort_order: figure.sort_order ?? 0,
        video_url: figure.video_url ?? null,
    };
}

export function normalizeLevel(raw: unknown): ContentLevel {
    const level = raw as Partial<ContentLevel> & {
        level_contents?: unknown[];
        levelContents?: unknown[];
    };
    const contents = level.level_contents ?? level.levelContents ?? [];

    return {
        id: level.id ?? 0,
        name: level.name ?? '',
        description: level.description ?? null,
        sort_order: level.sort_order ?? 0,
        level_contents: Array.isArray(contents)
            ? contents.map(normalizeFigure)
            : [],
    };
}

export function normalizeDanceTypeCard(raw: unknown): DanceTypeCard {
    const danceType = raw as Partial<DanceTypeCard>;

    return {
        id: danceType.id ?? 0,
        name: danceType.name ?? '',
        slug: danceType.slug ?? '',
        description: danceType.description ?? null,
        sort_order: danceType.sort_order ?? 0,
        levels_count: danceType.levels_count ?? 0,
        figures_count: danceType.figures_count ?? 0,
    };
}

export function normalizeDanceTypeDetail(raw: unknown): DanceTypeDetail {
    const danceType = raw as Partial<DanceTypeDetail> & {
        levels?: unknown[];
    };

    return {
        ...normalizeDanceTypeCard(danceType),
        levels: Array.isArray(danceType.levels)
            ? danceType.levels.map(normalizeLevel)
            : [],
    };
}
