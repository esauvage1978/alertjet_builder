import { useCallback, useMemo, useState } from 'react';
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

  async function onToggleBlock(userId) {
    if (!data?.formCsrf || !userId) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(
        '/mon-organisation/clients',
        { _token: data.formCsrf, action: 'toggle_block', userId: String(userId) },
        { json: true },
      );
      const payload = await res.json().catch(() => ({}));
      if (res.ok && payload.ok === true) {
        await reload();
        return;
      }
      throw new Error(payload?.message || `Erreur (${res.status})`);
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  async function onSetRole(userId, roleKey) {
    if (!data?.formCsrf || !userId || !roleKey) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(
        '/mon-organisation/clients',
        { _token: data.formCsrf, action: 'set_role', userId: String(userId), role: roleKey },
        { json: true },
      );
      const payload = await res.json().catch(() => ({}));
      if (res.ok && payload.ok === true) {
        await reload();
        return;
      }
      throw new Error(payload?.message || `Erreur (${res.status})`);
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  async function onSendReset(userId) {
    if (!data?.formCsrf || !userId) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(
        '/mon-organisation/clients',
        { _token: data.formCsrf, action: 'send_reset', userId: String(userId) },
        { json: true },
      );
      const payload = await res.json().catch(() => ({}));
      if (res.ok && payload.ok === true) {
        await reload();
        return;
      }
      throw new Error(payload?.message || `Erreur (${res.status})`);
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
  const roleOptions = useMemo(
    () => [
      { value: 'client', label: 'Client' },
      { value: 'client_supervisor', label: 'Client (superviseur)' },
    ],
    [],
  );

  return (
    <div className="webhook-projects-page op-project-edit">
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
                <th>Rôle</th>
                <th className="text-center">Tickets</th>
                <th className="text-center">Accès</th>
                <th>Depuis</th>
                <th className="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {accesses.length === 0 ? (
                <tr>
                  <td colSpan={7} className="text-muted small">
                    Aucun accès enregistré.
                  </td>
                </tr>
              ) : (
                accesses.map((row) => (
                  <tr key={row.userId} className="ou-member-row">
                    <td>{row.email}</td>
                    <td>{row.displayName || '—'}</td>
                    <td style={{ minWidth: 220 }}>
                      <select
                        className="form-control form-control-sm"
                        value={row.roleKey || 'client'}
                        disabled={busy}
                        onChange={(e) => onSetRole(row.userId, e.target.value)}
                      >
                        {roleOptions.map((o) => (
                          <option key={o.value} value={o.value}>
                            {o.label}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="text-center">
                      <span className="badge badge-light">{Number(row.ticketCount || 0)}</span>
                    </td>
                    <td className="text-center">
                      {row.blockedAt ? (
                        <span className="badge badge-danger">Bloqué</span>
                      ) : (
                        <span className="badge badge-success">Actif</span>
                      )}
                    </td>
                    <td>
                      {row.createdAt ? new Date(row.createdAt).toLocaleString('fr-FR') : '—'}
                    </td>
                    <td className="text-right">
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-primary mr-2"
                        disabled={busy}
                        onClick={() => onSendReset(row.userId)}
                        title="Renvoyer un e-mail pour définir / réinitialiser le mot de passe"
                      >
                        Relancer e-mail
                      </button>
                      <button
                        type="button"
                        className={`btn btn-sm ${row.blockedAt ? 'btn-outline-success' : 'btn-outline-warning'} mr-2`}
                        disabled={busy}
                        onClick={() => onToggleBlock(row.userId)}
                        title={row.blockedAt ? 'Débloquer l’accès' : 'Bloquer l’accès'}
                      >
                        {row.blockedAt ? 'Débloquer' : 'Bloquer'}
                      </button>
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
    </div>
  );
}
