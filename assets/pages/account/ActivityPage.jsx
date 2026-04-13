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
              <strong>{l.action}</strong>
              <span className="text-muted ml-2">{new Date(l.createdAt).toLocaleString()}</span>
            </li>
          ))}
        </ul>
      </PageCard>
    </>
  );
}
