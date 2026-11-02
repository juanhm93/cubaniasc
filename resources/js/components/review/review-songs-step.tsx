import { Link } from '@inertiajs/react';
import { useCallback } from 'react';
import { ReviewStreakCard } from '@/components/review/review-streak-card';
import { SongPlayer } from '@/components/song-player';
import { useTranslation } from '@/i18n/use-translation';
import { fetchSongAudioUrl } from '@/lib/review-service';
import { home } from '@/routes';
import type { ReviewSong, ReviewStreak } from '@/types/review';

type ReviewSongsStepProps = {
    sessionId: number;
    songs: ReviewSong[];
    loading: boolean;
    onComplete: () => void;
};

/**
 * The URL to play is requested when the step opens, so the file is ready when the
 * student taps play. The one to download is requested on the tap: it answers with
 * `attachment`, so the browser saves the file and the page stays where it is.
 */
function ReviewSongPlayer({
    sessionId,
    song,
}: {
    sessionId: number;
    song: ReviewSong;
}) {
    const loadUrl = useCallback(
        async () => (await fetchSongAudioUrl(sessionId, song.id, 'stream')).url,
        [sessionId, song.id],
    );

    const download = async (): Promise<void> => {
        const { url } = await fetchSongAudioUrl(sessionId, song.id, 'download');

        window.location.assign(url);
    };

    return (
        <SongPlayer
            className="cubania-review__song-player"
            title={song.title}
            artist={song.artist}
            loadUrl={loadUrl}
            durationSeconds={song.audio.duration_seconds}
            onDownload={download}
            autoLoad
        />
    );
}

export function ReviewSongsStep({
    sessionId,
    songs,
    loading,
    onComplete,
}: ReviewSongsStepProps) {
    const { t } = useTranslation();
    const playableSongs = songs.filter((song) => song.audio.available);

    return (
        <section className="cubania-review__panel">
            <h2 className="cubania-review__panel-title">
                {t('review.songs.title')}
            </h2>
            <p className="cubania-review__panel-lead">
                {t('review.songs.lead')}
            </p>

            <div className="cubania-review__grid">
                {playableSongs.map((song) => (
                    <ReviewSongPlayer
                        key={song.id}
                        sessionId={sessionId}
                        song={song}
                    />
                ))}
            </div>

            {playableSongs.length === 0 ? (
                <p className="cubania-review__muted">
                    {t('review.songs.empty')}
                </p>
            ) : null}

            <div className="cubania-review__actions">
                <button
                    type="button"
                    className="cubania-btn cubania-btn--primary"
                    disabled={loading}
                    onClick={onComplete}
                    data-cubania-cursor="interactive"
                >
                    {loading
                        ? t('review.songs.saving')
                        : t('review.songs.finish')}
                </button>
            </div>
        </section>
    );
}

type ReviewCompleteStepProps = {
    streak: ReviewStreak | null;
    streakGrew: boolean;
    studentName: string;
    onSignOut: () => void;
};

export function ReviewCompleteStep({
    streak,
    streakGrew,
    studentName,
    onSignOut,
}: ReviewCompleteStepProps) {
    const { t } = useTranslation();

    return (
        <section className="cubania-review__dashboard cubania-review__dashboard--complete">
            <div className="cubania-review__dashboard-intro">
                <h2 className="cubania-review__dashboard-title">
                    {t('review.complete.title', { name: studentName })}
                </h2>
                <p className="cubania-review__panel-lead">
                    {t('review.complete.lead')}
                </p>

                <div className="cubania-review__actions">
                    <Link
                        href={home()}
                        className="cubania-btn cubania-btn--primary"
                        data-cubania-cursor="interactive"
                    >
                        {t('review.complete.backHome')}
                    </Link>
                    <button
                        type="button"
                        className="cubania-btn cubania-btn--secondary"
                        onClick={onSignOut}
                        data-cubania-cursor="interactive"
                    >
                        {t('review.signOut')}
                    </button>
                </div>
            </div>

            <ReviewStreakCard streak={streak} celebrate={streakGrew} />
        </section>
    );
}
