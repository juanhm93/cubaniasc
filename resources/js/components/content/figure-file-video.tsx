import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/i18n/use-translation';
import { getFigureVideoStreamUrl } from '@/services/figureVideoService';

const boxClassName =
    'flex aspect-video flex-col items-center justify-center gap-3 rounded-md border bg-muted px-4 text-center text-sm text-muted-foreground';

/**
 * Plays the figure's own MP4 from a short-lived signed URL. If the video stops
 * loading (the URL expired or the file was replaced), it asks for a new URL once
 * before showing an error with a retry button.
 */
export default function FigureFileVideo({
    figureId,
    version,
}: {
    figureId: number;
    version: number;
}) {
    const [attempt, setAttempt] = useState(0);

    return (
        <FigureFileVideoAttempt
            key={`${figureId}-${version}-${attempt}`}
            figureId={figureId}
            retriesAutomatically={attempt === 0}
            onRetry={() => setAttempt((current) => current + 1)}
        />
    );
}

type AttemptState =
    | { status: 'loading' }
    | { status: 'ready'; url: string }
    | { status: 'error' };

function FigureFileVideoAttempt({
    figureId,
    retriesAutomatically,
    onRetry,
}: {
    figureId: number;
    retriesAutomatically: boolean;
    onRetry: () => void;
}) {
    const { t } = useTranslation();
    const [state, setState] = useState<AttemptState>({ status: 'loading' });

    useEffect(() => {
        let cancelled = false;

        getFigureVideoStreamUrl(figureId)
            .then(({ url }) => {
                if (!cancelled) {
                    setState({ status: 'ready', url });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setState({ status: 'error' });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [figureId]);

    if (state.status === 'loading') {
        return (
            <div className={boxClassName} role="status">
                <Spinner className="size-6" />
                {t('content.figureVideo.player.loading')}
            </div>
        );
    }

    if (state.status === 'error') {
        return (
            <div className={boxClassName} role="alert">
                {t('content.figureVideo.player.error')}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onRetry}
                >
                    <RefreshCw className="size-4" aria-hidden />
                    {t('content.figureVideo.retry')}
                </Button>
            </div>
        );
    }

    return (
        <video
            className="aspect-video w-full rounded-md bg-black"
            controls
            playsInline
            preload="metadata"
            src={state.url}
            onError={() =>
                retriesAutomatically ? onRetry() : setState({ status: 'error' })
            }
        />
    );
}
