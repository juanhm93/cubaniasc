export default function LevelVideoPreview({
    url,
}: {
    url: string | null | undefined;
}) {
    const trimmed = url?.trim() ?? '';

    if (trimmed === '') {
        return (
            <div
                className="flex aspect-video items-center justify-center rounded-md border bg-muted px-4 text-center text-sm text-muted-foreground"
                role="status"
            >
                Esta figura todavía no tiene un video asignado.
            </div>
        );
    }

    const ytMatch = trimmed.match(
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
    );

    if (ytMatch) {
        return (
            <iframe
                title="Vista previa del video"
                className="aspect-video w-full rounded-md border-0"
                src={`https://www.youtube.com/embed/${ytMatch[1]}`}
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowFullScreen
            />
        );
    }

    return (
        <video
            className="aspect-video w-full rounded-md bg-black"
            controls
            playsInline
            src={trimmed}
        />
    );
}
