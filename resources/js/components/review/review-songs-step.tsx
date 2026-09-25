import { Link } from '@inertiajs/react';
import { ExternalLink, Music } from 'lucide-react';
import { ReviewStreakCard } from '@/components/review/review-streak-card';
import { useTranslation } from '@/i18n/use-translation';
import { home } from '@/routes';
import type { ReviewSong, ReviewStreak } from '@/types/review';

type ReviewSongsStepProps = {
    songs: ReviewSong[];
    loading: boolean;
    onComplete: () => void;
};

export function ReviewSongsStep({
    songs,
    loading,
    onComplete,
}: ReviewSongsStepProps) {
    const { t } = useTranslation();

    return (
        <section className="cubania-review__panel">
            <h2 className="cubania-review__panel-title">
                {t('review.songs.title')}
            </h2>
            <p className="cubania-review__panel-lead">
                {t('review.songs.lead')}
            </p>

            <div className="cubania-review__grid">
                {songs.map((song) => (
                    <a
                        key={song.id}
                        href={song.audio_or_link_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="cubania-review__song-card"
                        data-cubania-cursor="interactive"
                    >
                        <Music
                            className="cubania-review__song-icon"
                            aria-hidden
                        />
                        <span>
                            <span className="cubania-review__song-title">
                                {song.title}
                            </span>
                            <span className="cubania-review__song-artist">
                                {song.artist}
                            </span>
                        </span>
                        <ExternalLink
                            className="cubania-review__song-link"
                            aria-hidden
                        />
                    </a>
                ))}
            </div>

            {songs.length === 0 ? (
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
