export function ErrorAlert({ message, onRetry }) {
  if (!message) return null;
  return (
    <div className="alert alert-danger d-flex flex-wrap align-items-center justify-content-between" role="alert">
      <span>{message}</span>
      {onRetry ? (
        <button type="button" className="btn btn-sm btn-outline-danger mt-2 mt-sm-0" onClick={onRetry}>
          Réessayer
        </button>
      ) : null}
    </div>
  );
}
