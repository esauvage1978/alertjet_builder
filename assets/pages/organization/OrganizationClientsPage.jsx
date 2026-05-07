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

  const i18n = (data && typeof data === 'object' && data.i18n && typeof data.i18n === 'object') ? data.i18n : {};

  const accesses = Array.isArray(data.accesses) ? data.accesses : [];
  const eligible = Array.isArray(data.eligibleUsers) ? data.eligibleUsers : [];
  const roleOptions = [
    { value: 'client', label: i18n.org_clients_role_client || 'Client' },
    { value: 'client_supervisor', label: i18n.org_clients_role_client_supervisor || 'Client (superviseur)' },
  ];

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
                  {i18n.org_clients_eligible_member_label || 'Membre (rôle Client)'}
                </label>
                <select
                  id="oc-eligible"
                  className="form-control form-control-sm"
                  style={{ minWidth: 220 }}
                  value={selectedUserId}
                  onChange={(e) => setSelectedUserId(e.target.value)}
                  disabled={busy}
                >
                  <option value="">{i18n.org_clients_choose_placeholder || '— Choisir —'}</option>
                  {eligible.map((u) => (
                    <option key={u.id} value={String(u.id)}>
                      {(u.displayName && String(u.displayName).trim()) || u.email}
                    </option>
                  ))}
                </select>
              </div>
              <button type="submit" className="btn btn-sm btn-primary" disabled={busy || !selectedUserId}>
                {i18n.org_clients_add_submit || 'Autoriser'}
              </button>
            </form>
          ) : null
        }
      >
        <p className="small text-muted mb-3">
          {i18n.org_clients_intro ||
            'Comptes au rôle « Client » autorisés pour le portail (formulaire interne). Géré ici, séparément de la liste des utilisateurs.'}
        </p>
        {eligible.length === 0 && accesses.length === 0 ? (
          <p className="small mb-0 text-muted">
            {i18n.org_clients_no_eligible_hint ||
              'Aucun membre avec le rôle Client à ajouter, et aucun accès enregistré. Assignez le rôle Client au compte (hors de cette page), puis revenez autoriser l’accès ici.'}
          </p>
        ) : null}
        {eligible.length === 0 && accesses.length > 0 ? (
          <p className="small text-muted mb-3">
            {i18n.org_clients_all_eligible_already || 'Tous les membres « Client » éligibles ont déjà un accès enregistré.'}
          </p>
        ) : null}
        <div className="table-responsive">
          <table className="table ou-members-table mb-0">
            <thead className="ou-members-thead">
              <tr>
                <th>{i18n.org_clients_th_email || 'E-mail'}</th>
                <th>{i18n.org_clients_th_display_name || 'Nom'}</th>
                <th>{i18n.org_clients_th_role || 'Rôle'}</th>
                <th className="text-center">{i18n.org_clients_th_tickets || 'Tickets'}</th>
                <th className="text-center">{i18n.org_clients_th_access || 'Accès'}</th>
                <th>{i18n.org_clients_th_since || 'Depuis'}</th>
                <th className="text-right">{i18n.org_clients_th_actions || 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {accesses.length === 0 ? (
                <tr>
                  <td colSpan={7} className="text-muted small">
                    {i18n.org_clients_empty || 'Aucun accès enregistré.'}
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
                        <span className="badge badge-danger">{i18n.org_clients_access_blocked || 'Bloqué'}</span>
                      ) : (
                        <span className="badge badge-success">{i18n.org_clients_access_active || 'Actif'}</span>
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
                        title={i18n.org_clients_reset_title || 'Renvoyer un e-mail pour définir / réinitialiser le mot de passe'}
                      >
                        {i18n.org_clients_send_reset || 'Relancer e-mail'}
                      </button>
                      <button
                        type="button"
                        className={`btn btn-sm ${row.blockedAt ? 'btn-outline-success' : 'btn-outline-warning'} mr-2`}
                        disabled={busy}
                        onClick={() => onToggleBlock(row.userId)}
                        title={
                          row.blockedAt
                            ? i18n.org_clients_unblock_title || 'Débloquer l’accès'
                            : i18n.org_clients_block_title || 'Bloquer l’accès'
                        }
                      >
                        {row.blockedAt ? i18n.org_clients_unblock || 'Débloquer' : i18n.org_clients_block || 'Bloquer'}
                      </button>
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        disabled={busy}
                        onClick={() => onRemove(row.userId)}
                      >
                        {i18n.org_clients_remove || 'Retirer'}
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
