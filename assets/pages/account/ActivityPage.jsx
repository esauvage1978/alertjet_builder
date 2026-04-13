import { useCallback } from 'react';
import { fetchJson } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

export default function ActivityPage() {
  const loadFn = useCallback(async () => fetchJson('/compte/activite'), []);
  const { data, error, loading, reload } = useAsyncResource(loadFn);

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger l’activité'} onRetry={reload} />;
  }

  return (
    <>
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}
      <PageCard>
        <ul className="list-group list-group-flush">
          {data.logs?.map((l) => (
            <li key={l.id} className="list-group-item small">
              <div>
                <strong>{l.action}</strong>
                <span className="text-muted ml-2">{new Date(l.createdAt).toLocaleString()}</span>
              </div>
              {l.details && typeof l.details === 'object' && Object.keys(l.details).length > 0 ? (
                <details className="mt-1 mb-0">
                  <summary className="text-muted" style={{ cursor: 'pointer', fontSize: '0.8rem' }}>
                    Détails
                  </summary>
                  <pre
                    className="mb-0 mt-1 p-2 bg-light rounded border text-muted"
                    style={{ fontSize: '0.72rem', maxHeight: '12rem', overflow: 'auto' }}
                  >
                    {JSON.stringify(l.details, null, 2)}
                  </pre>
                </details>
              ) : null}
            </li>
          ))}
        </ul>
      </PageCard>
    </>
  );
}
