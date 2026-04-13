export function LoadingState({ message = 'Chargement…' }) {
  return (
    <div className="text-muted py-4 text-center" role="status" aria-live="polite">
      <i className="fas fa-circle-notch fa-spin mr-2" aria-hidden="true" />
      {message}
    </div>
  );
}
