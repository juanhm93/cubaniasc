import type { ReviewLevelContent } from '@/types/review';

type ReviewFigureSelectionProps = {
  figures: ReviewLevelContent[];
  selectedIds: number[];
  loading: boolean;
  onToggle: (figureId: number) => void;
  onContinue: () => void;
};

export function ReviewFigureSelection({
  figures,
  selectedIds,
  loading,
  onToggle,
  onContinue,
}: ReviewFigureSelectionProps) {
  return (
    <section className="cubania-review__panel">
      <h2 className="cubania-review__panel-title">Elige 2 figuras</h2>
      <p className="cubania-review__panel-lead">
        Te mostramos tus últimas vistas del nivel. Selecciona exactamente dos para repasar hoy.
      </p>

      <div className="cubania-review__grid">
        {figures.map((figure) => {
          const selected = selectedIds.includes(figure.id);

          return (
            <button
              key={figure.id}
              type="button"
              className={`cubania-review__card ${selected ? 'cubania-review__card--selected' : ''}`}
              onClick={() => onToggle(figure.id)}
              data-cubania-cursor="interactive"
            >
              <h3 className="cubania-review__card-name">{figure.name}</h3>
              {figure.description ? (
                <p className="cubania-review__card-desc">{figure.description}</p>
              ) : null}
            </button>
          );
        })}
      </div>

      <p className="cubania-review__muted">
        {selectedIds.length}/2 seleccionadas
      </p>

      <div className="cubania-review__actions">
        <button
          type="button"
          className="cubania-btn cubania-btn--primary"
          disabled={loading || selectedIds.length !== 2}
          onClick={onContinue}
          data-cubania-cursor="interactive"
        >
          {loading ? 'Guardando…' : 'Continuar al repaso'}
        </button>
      </div>
    </section>
  );
}
