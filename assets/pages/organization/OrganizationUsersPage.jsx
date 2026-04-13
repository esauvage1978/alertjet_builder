import { useCallback, useState } from 'react';
import { fetchJson, postForm, urlWithCurrentSearch } from '../../api/http.js';
import { UserAvatar } from '../../components/ui/UserAvatar.jsx';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

export default function OrganizationUsersPage() {
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);

  const loadFn = useCallback(async () => fetchJson(urlWithCurrentSearch('/mon-organisation/utilisateurs')), []);
  const { data, error, loading, reload, setError } = useAsyncResource(loadFn);

  async function invite(ev) {
    ev.preventDefault();
    if (!data?.inviteForm || !email.trim()) return;
    setBusy(true);
    setError('');
    try {
      const res = await postForm(data.inviteForm.action, {
        [data.inviteForm.emailName]: email.trim(),
        [data.inviteForm.csrfName]: data.inviteForm.csrfValue,
      });
      if (res.type === 'opaqueredirect' || (res.status >= 300 && res.status < 400)) {
        setEmail('');
        await reload();
        window.location.href = '/app/organization/users';
        return;
      }
      if (!res.ok) throw new Error('Invitation échouée');
      setEmail('');
      await reload();
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
    return <ErrorAlert message={error || 'Impossible de charger les membres'} onRetry={reload} />;
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
          <form onSubmit={invite} className="d-flex flex-wrap align-items-center" style={{ gap: '0.5rem' }}>
            <input
              type="email"
              className="form-control form-control-sm"
              style={{ maxWidth: 280 }}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="inviter@exemple.fr"
              autoComplete="email"
            />
            <button type="submit" className="btn btn-sm btn-primary ou-invite-btn" disabled={busy}>
              <i className="fas fa-user-plus mr-1" aria-hidden="true" /> Inviter
            </button>
          </form>
        }
      >
        <div className="table-responsive">
          <table className="table ou-members-table mb-0">
            <thead className="ou-members-thead">
              <tr>
                <th>Membre</th>
                <th>Rôle</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.members?.map((m) => (
                <tr key={m.id} className="ou-member-row">
                  <td>
                    <div className="d-flex align-items-center ou-member-cell">
                      <UserAvatar
                        className="navbar-avatar ou-member-avatar flex-shrink-0"
                        initials={m.avatarInitials}
                        bg={m.avatarColorOrDefault}
                        fg={m.avatarForegroundColorOrDefault}
                      />
                      <div className="ou-member-info ml-2">
                        <div className="ou-member-name">{m.displayName || m.email}</div>
                        {m.displayName ? <div className="ou-member-email">{m.email}</div> : null}
                      </div>
                    </div>
                  </td>
                  <td>
                    <span className={`badge ou-role-badge ${m.roleBadgeClass}`}>{m.primaryRoleCatalogKey}</span>
                  </td>
                  <td className="text-right">
                    <form method="post" action={`/mon-organisation/utilisateurs/${m.id}/retirer`} className="d-inline">
                      <input type="hidden" name="_token" value={m.csrfRemove} />
                      <button
                        type="submit"
                        className="btn btn-sm btn-icon btn-icon--danger"
                        title="Retirer"
                        onClick={(e) => {
                          if (!window.confirm('Retirer ce membre ?')) e.preventDefault();
                        }}
                      >
                        <i className="fas fa-user-minus" aria-hidden="true" />
                      </button>
                    </form>
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
