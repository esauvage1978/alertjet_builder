import { useCallback, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson, postForm, urlWithCurrentSearch } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

export default function ProjectsPage() {
  const { orgToken } = useParams();
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);

  const loadFn = useCallback(
    async () => fetchJson(urlWithCurrentSearch(`/organisation/${orgToken}/projets`)),
    [orgToken],
  );
  const { data, error, loading, reload, setError } = useAsyncResource(loadFn);

  async function createProject(ev) {
    ev.preventDefault();
    if (!data?.newProjectCsrf || !name.trim()) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(`/organisation/${orgToken}/projets/nouveau`, {
        name: name.trim(),
        _token: data.newProjectCsrf,
      });
      if (!res.ok) {
        throw new Error('Création du projet échouée');
      }
      setName('');
      await reload();
      window.location.href = `/app/organization/${orgToken}/projects`;
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger les projets'} onRetry={reload} />;
  }

  return (
    <>
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}
      <PageCard
        toolbar={
          <form onSubmit={createProject} className="d-flex flex-wrap align-items-center" style={{ gap: '0.5rem' }}>
            <input
              className="form-control form-control-sm"
              style={{ maxWidth: 260 }}
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Nouveau projet"
            />
            <button type="submit" className="btn btn-sm btn-primary ou-invite-btn" disabled={busy}>
              Créer
            </button>
          </form>
        }
      >
        <div className="table-responsive">
          <table className="table ou-members-table mb-0">
            <thead className="ou-members-thead">
              <tr>
                <th>Projet</th>
                <th>Tickets</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.projects?.map((p) => (
                <tr key={p.id} className="ou-member-row">
                  <td>
                    <strong>{p.name}</strong>
                    <div className="small text-muted">{p.webhookTokenPrefix}…</div>
                  </td>
                  <td>{p.ticketCount}</td>
                  <td className="text-right">
                    <Link
                      to={`/organization/${orgToken}/projects/${p.id}/edit`}
                      className="btn btn-sm btn-icon btn-icon--primary"
                      title="Modifier"
                    >
                      <i className="fas fa-pen" aria-hidden="true" />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </PageCard>
    </>
  );
}
