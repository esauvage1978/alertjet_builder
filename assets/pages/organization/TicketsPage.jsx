import { useCallback } from 'react';
import { fetchJson } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

export default function TicketsPage() {
  const loadFn = useCallback(async () => fetchJson('/mon-organisation/tickets'), []);
  const { data, error, loading, reload } = useAsyncResource(loadFn);

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger les tickets'} onRetry={reload} />;
  }

  return (
    <>
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}
      <PageCard>
        <div className="table-responsive">
          <table className="table ou-members-table mb-0">
            <thead className="ou-members-thead">
              <tr>
                <th>Titre</th>
                <th>Statut</th>
                <th>Priorité</th>
              </tr>
            </thead>
            <tbody>
              {data.tickets?.map((t) => (
                <tr key={t.id} className="ou-member-row">
                  <td>{t.title}</td>
                  <td>{t.status}</td>
                  <td>{t.priority}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </PageCard>
    </>
  );
}
