import { useCallback } from 'react';
import { Link } from 'react-router-dom';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

async function fetchTicketNewPayload() {
  const res = await fetch('/mon-organisation/tickets/nouveau', {
    credentials: 'include',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  });
  const ct = res.headers.get('Content-Type') || '';
  if (res.status === 403) {
    let msg = 'Accès refusé.';
    if (ct.includes('application/json')) {
      try {
        const j = await res.json();
        if (typeof j.message === 'string' && j.message) msg = j.message;
      } catch {
        /* ignore */
      }
    }
    throw new Error(msg);
  }
  if (!res.ok) {
    const t = await res.text();
    throw new Error(t || res.statusText || String(res.status));
  }
  if (!ct.includes('application/json')) {
    return null;
  }
  return res.json();
}

export default function TicketCreatePage() {
  const loadFn = useCallback(async () => fetchTicketNewPayload(), []);
  const { data, error, loading, reload } = useAsyncResource(loadFn);

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger la page'} onRetry={reload} />;
  }

  const projects = Array.isArray(data.projects) ? data.projects : [];

  return (
    <PageCard>
      <p className="text-muted small mb-3">
        Création de ticket (formulaire interne). Les options spécifiques au rôle Client seront définies ultérieurement.
      </p>
      {projects.length === 0 ? (
        <p className="mb-0 small">
          Aucun projet n’a le formulaire interne activé. Activez-le dans les paramètres du projet (édition), puis
          revenez ici.
        </p>
      ) : (
        <>
          <p className="small mb-2">Projets éligibles :</p>
          <ul className="list-unstyled mb-0">
            {projects.map((p) => (
              <li key={p.publicToken} className="mb-1">
                <span className="font-weight-bold">{p.name}</span>{' '}
                <span className="text-muted font-monospace small">({p.publicToken})</span>
              </li>
            ))}
          </ul>
          <p className="small text-muted mt-3 mb-0">
            Le formulaire de saisie sera branché sur cette page dans une prochaine itération.
          </p>
        </>
      )}
      <p className="mt-3 mb-0">
        <Link to="/tickets" className="small">
          ← Retour à la liste des tickets
        </Link>
      </p>
    </PageCard>
  );
}
