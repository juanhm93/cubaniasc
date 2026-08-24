import type { ReviewSong, ReviewStreak } from '@/types/review';

type ReviewSongsStepProps = {
  songs: ReviewSong[];
  loading: boolean;
  onComplete: () => void;
};

export function ReviewSongsStep({ songs, loading, onComplete }: ReviewSongsStepProps) {
  return (
    <section className="cubania-review__panel">
      <h2 className="cubania-review__panel-title">Canciones para practicar</h2>
      <p className="cubania-review__panel-lead">
        Tres recomendaciones de tu nivel para seguir el ritmo en casa.
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
            <p className="cubania-review__song-title">{song.title}</p>
            <p className="cubania-review__song-artist">{song.artist}</p>
          </a>
        ))}
      </div>

      {songs.length === 0 ? (
        <p className="cubania-review__muted">Aún no hay canciones configuradas para tu nivel.</p>
      ) : null}

      <div className="cubania-review__actions">
        <button
          type="button"
          className="cubania-btn cubania-btn--primary"
          disabled={loading}
          onClick={onComplete}
          data-cubania-cursor="interactive"
        >
          {loading ? 'Guardando…' : 'Completar repaso'}
        </button>
      </div>
    </section>
  );
}

type ReviewCompleteStepProps = {
  streak: ReviewStreak | null;
  studentName: string;
  onRestart: () => void;
  onSignOut: () => void;
};

export function ReviewCompleteStep({
  streak,
  studentName,
  onRestart,
  onSignOut,
}: ReviewCompleteStepProps) {
  return (
    <section className="cubania-review__panel">
      <h2 className="cubania-review__panel-title">¡Buen trabajo, {studentName}!</h2>
      <p className="cubania-review__panel-lead">
        Completaste tu sesión de repaso. Vuelve mañana para mantener la racha.
      </p>

      {streak ? (
        <div className="cubania-review__streak">
          <span aria-hidden>🔥</span>
          <span>
            Llevas {streak.current_streak} {streak.current_streak === 1 ? 'día' : 'días'} repasando
          </span>
        </div>
      ) : null}

      <div className="cubania-review__actions">
        <button
          type="button"
          className="cubania-btn cubania-btn--primary"
          onClick={onRestart}
          data-cubania-cursor="interactive"
        >
          Repasar de nuevo
        </button>
        <button
          type="button"
          className="cubania-btn cubania-btn--secondary"
          onClick={onSignOut}
          data-cubania-cursor="interactive"
        >
          Salir
        </button>
      </div>
    </section>
  );
}
