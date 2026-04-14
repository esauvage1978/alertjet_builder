import { useCallback, useState } from 'react';
import { fetchJson, postForm } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

export default function OrganizationClientsPage() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [selectedUserId, setSelectedUserId] = useState('');

  const loadFn = useCallback(async () => fetchJson('/mon-organisation/clients'), []);
  const { data, error: loadError, loading, reload } = useAsyncResource(loadFn);

  async function onAdd(ev) {
    ev.preventDefault();
    if (!data?.formCsrf || !selectedUserId) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(
        '/mon-organisation/clients',
        {
          _token: data.formCsrf,
          action: 'add',
          userId: selectedUserId,
        },
        { json: true },
      );
      const ct = res.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        const payload = await res.json();
        if (res.ok && payload.ok === true) {
          setSelectedUserId('');
          await reload();
          return;
        }
        const msg =
          typeof payload.message === 'string' && payload.message
            ? payload.message
            : `Erreur (${res.status})`;
        throw new Error(msg);
      }
      throw new Error('Réponse inattendue du serveur.');
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  async function onRemove(userId) {
    if (!data?.formCsrf || !userId) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(
        '/mon-organisation/clients',
        {
          _token: data.formCsrf,
          action: 'remove',
          userId: String(userId),
        },
        { json: true },
      );
      const ct = res.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        const payload = await res.json();
        if (res.ok && payload.ok === true) {
          await reload();
          return;
        }
        const msg =
          typeof payload.message === 'string' && payload.message
            ? payload.message
            : `Erreur (${res.status})`;
        throw new Error(msg);
      }
      throw new Error('Réponse inattendue du serveur.');
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
    return <ErrorAlert message={loadError || 'Impossible de charger la page'} onRetry={reload} />;
  }

  const accesses = Array.isArray(data.accesses) ? data.accesses : [];
  const eligible = Array.isArray(data.eligibleUsers) ? data.eligibleUsers : [];

  return (
    <>
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={() => setError('')} />
        </div>
      ) : null}
      <PageCard
        toolbar={
          eligible.length > 0 ? (
            <form onSubmit={onAdd} className="d-flex flex-wrap align-items-end" style={{ gap: '0.5rem' }}>
              <div className="form-group mb-0">
                <label htmlFor="oc-eligible" className="small text-muted mb-1 d-block">
                  Membre (rôle Client)
                </label>
                <select
                  id="oc-eligible"
                  className="form-control form-control-sm"
                  style={{ minWidth: 220 }}
                  value={selectedUserId}
                  onChange={(e) => setSelectedUserId(e.target.value)}
                  disabled={busy}
                >
                  <option value="">— Choisir —</option>
                  {eligible.map((u) => (
                    <option key={u.id} value={String(u.id)}>
                      {(u.displayName && String(u.displayName).trim()) || u.email}
                    </option>
                  ))}
                </select>
              </div>
              <button type="submit" className="btn btn-sm btn-primary" disabled={busy || !selectedUserId}>
                Autoriser l’accès
              </button>
            </form>
          ) : null
        }
      >
        <p className="small text-muted mb-3">
          Comptes au rôle « Client » autorisés pour le portail (formulaire interne). Géré ici, séparément de la
          liste des utilisateurs.
        </p>
        {eligible.length === 0 && accesses.length === 0 ? (
          <p className="small mb-0 text-muted">
            Aucun membre avec le rôle Client à ajouter, et aucun accès enregistré. Assignez le rôle Client au compte
            (hors de cette page), puis revenez autoriser l’accès ici.
          </p>
        ) : null}
        {eligible.length === 0 && accesses.length > 0 ? (
          <p className="small text-muted mb-3">Tous les membres « Client » éligibles ont déjà un accès enregistré.</p>
        ) : null}
        <div className="table-responsive">
          <table className="table ou-members-table mb-0">
            <thead className="ou-members-thead">
              <tr>
                <th>E-mail</th>
                <th>Nom</th>
                <th>Depuis</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {accesses.length === 0 ? (
                <tr>
                  <td colSpan={4} className="text-muted small">
                    Aucun accès enregistré.
                  </td>
                </tr>
              ) : (
                accesses.map((row) => (
                  <tr key={row.userId}>
                    <td>{row.email}</td>
                    <td>{row.displayName || '—'}</td>
                    <td>
                      {row.createdAt ? new Date(row.createdAt).toLocaleString('fr-FR') : '—'}
                    </td>
                    <td className="text-right">
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        disabled={busy}
                        onClick={() => onRemove(row.userId)}
                      >
                        Retirer
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </PageCard>
    </>
  );
}
