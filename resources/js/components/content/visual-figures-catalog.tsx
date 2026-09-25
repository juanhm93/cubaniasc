import type { ContentLevel, FigureItem } from '@/types/content';

export default function VisualFiguresCatalog({
    danceTypeName,
    levels,
    onSelectFigure,
}: {
    danceTypeName: string;
    levels: ContentLevel[];
    onSelectFigure: (figure: FigureItem) => void;
}) {
    const figureCount = levels.reduce(
        (total, level) => total + level.level_contents.length,
        0,
    );

    return (
        <div className="rounded-xl border border-[#D5DBE3] bg-[#F3F5F7] px-5 py-8 text-[#121417] dark:border-[#2A3139] dark:bg-[#161A1F] dark:text-[#EEF1F4]">
            <div className="mb-8 text-center">
                <p className="text-xs tracking-[0.2em] text-[#5B6570] uppercase dark:text-[#9AA3AD]">
                    Lista de figuras
                </p>
                <h2 className="mt-1 font-serif text-3xl font-semibold tracking-tight">
                    {danceTypeName}
                </h2>
                <p className="mt-2 text-sm text-[#5B6570] dark:text-[#9AA3AD]">
                    {levels.length} nivel
                    {levels.length === 1 ? '' : 'es'} · {figureCount} figura
                    {figureCount === 1 ? '' : 's'}
                </p>
            </div>

            {levels.length === 0 ? (
                <p className="text-center text-sm text-[#5B6570] dark:text-[#9AA3AD]">
                    Este estilo todavía no tiene niveles.
                </p>
            ) : (
                <div className="columns-1 gap-8 sm:columns-2 xl:columns-3">
                    {levels.map((level) => (
                        <section
                            key={level.id}
                            className="mb-8 break-inside-avoid"
                        >
                            <h3 className="mb-3 border-b border-[#C45C26]/40 pb-1 font-serif text-lg font-semibold dark:border-[#E07A3A]/50">
                                {level.name}
                            </h3>
                            {level.level_contents.length === 0 ? (
                                <p className="text-sm text-[#5B6570] dark:text-[#9AA3AD]">
                                    Sin figuras todavía.
                                </p>
                            ) : (
                                <ul className="space-y-1.5">
                                    {level.level_contents.map((figure) => (
                                        <li key={figure.id}>
                                            <button
                                                type="button"
                                                className="w-full rounded-sm px-1 py-0.5 text-left text-sm leading-snug transition-colors hover:bg-[#E8EDF3] hover:underline dark:hover:bg-white/[0.07]"
                                                onClick={() =>
                                                    onSelectFigure(figure)
                                                }
                                            >
                                                {figure.name}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            )}
        </div>
    );
}
