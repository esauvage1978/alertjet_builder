import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson, postForm, postFormRedirect } from '../../api/http.js';
import { TicketHandlerMiniCard } from '../../components/project/TicketHandlerMiniCard.jsx';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

const PANES = [
  { id: 'pe-pane-general', label: 'Général', icon: 'fa-sliders-h' },
  { id: 'pe-pane-members', label: 'Membres', icon: 'fa-users' },
  { id: 'pe-pane-indicators', label: 'Indicateurs', icon: 'fa-chart-line' },
  { id: 'pe-pane-mail', label: 'Messagerie', icon: 'fa-envelope' },
  { id: 'pe-pane-webhook', label: 'Webhook', icon: 'fa-plug' },
];

export default function ProjectEditPage() {
  const { orgToken: orgFromRoute, projectId } = useParams();
  const { data: boot } = useBootstrap();
  const orgToken = orgFromRoute ?? boot.currentOrganization?.publicToken;

  const loadFn = useCallback(async () => {
    if (!orgToken || !projectId) {
      throw new Error('Organisation ou projet manquant.');
    }
    return fetchJson(`/organisation/${orgToken}/projets/${projectId}/edit`);
  }, [orgToken, projectId]);

  const { data, error, loading, reload, setError } = useAsyncResource(loadFn);

  const [activeTab, setActiveTab] = useState('pe-pane-general');
  const [busy, setBusy] = useState(false);
  const [imapPassword, setImapPassword] = useState('');

  const [name, setName] = useState('');
  const [ticketHandlerIds, setTicketHandlerIds] = useState([]);
  const [slaAck, setSlaAck] = useState('');
  const [slaResolve, setSlaResolve] = useState('');
  const [imapEnabled, setImapEnabled] = useState(false);
  const [imapHost, setImapHost] = useState('');
  const [imapPort, setImapPort] = useState('993');
  const [imapTls, setImapTls] = useState(true);
  const [imapUsername, setImapUsername] = useState('');
  const [imapMailbox, setImapMailbox] = useState('INBOX');

  useEffect(() => {
    if (!data?.project) return;
    const p = data.project;
    setName(p.name ?? '');
    setTicketHandlerIds(Array.isArray(p.ticketHandlerIds) ? [...p.ticketHandlerIds] : []);
    setSlaAck(p.slaAckTargetMinutes != null ? String(p.slaAckTargetMinutes) : '');
    setSlaResolve(p.slaResolveTargetMinutes != null ? String(p.slaResolveTargetMinutes) : '');
    setImapEnabled(Boolean(p.imapEnabled));
    setImapHost(p.imapHost ?? '');
    setImapPort(String(p.imapPort ?? 993));
    setImapTls(Boolean(p.imapTls));
    setImapUsername(p.imapUsername ?? '');
    setImapMailbox(p.imapMailbox ?? 'INBOX');
    setImapPassword('');
  }, [data]);

  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (hash && /^pe-pane-[a-z0-9-]+$/.test(hash)) {
      setActiveTab(hash);
    }
  }, []);

  function setTab(id) {
    setActiveTab(id);
    const base = `${window.location.pathname}${window.location.search}`;
    window.history.replaceState(null, '', `${base}#${id}`);
  }

  function toggleHandler(id) {
    setTicketHandlerIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  }

  async function onSubmit(ev) {
    ev.preventDefault();
    if (!data?.formPrefix || !data?.formCsrf || !orgToken || !projectId) return;
    setBusy(true);
    setError('');
    const prefix = data.formPrefix;
    const fields = {
      [`${prefix}[_token]`]: data.formCsrf,
      [`${prefix}[name]`]: name.trim(),
      [`${prefix}[_active_tab]`]: activeTab,
      [`${prefix}[ticketHandlers][]`]: ticketHandlerIds,
      [`${prefix}[slaAckTargetMinutes]`]: slaAck,
      [`${prefix}[slaResolveTargetMinutes]`]: slaResolve,
      [`${prefix}[imapEnabled]`]: imapEnabled ? '1' : '0',
      [`${prefix}[imapTls]`]: imapTls ? '1' : '0',
      [`${prefix}[imapHost]`]: imapHost,
      [`${prefix}[imapPort]`]: imapPort,
      [`${prefix}[imapUsername]`]: imapUsername,
      [`${prefix}[imapMailbox]`]: imapMailbox,
    };
    if (imapPassword.trim()) {
      fields[`${prefix}[imapPassword]`] = imapPassword;
    }
    try {
      const res = await postForm(`/organisation/${orgToken}/projets/${projectId}/edit`, fields, { json: true });
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
            : typeof payload.error === 'string'
              ? payload.error
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

  async function onTestImap() {
    if (!data?.testImapCsrf || !orgToken || !projectId) return;
    await postFormRedirect(`/organisation/${orgToken}/projets/${projectId}/test-imap`, {
      _token: data.testImapCsrf,
    });
  }

  function copyWebhook(url) {
    if (!url || !navigator.clipboard?.writeText) return;
    navigator.clipboard.writeText(url).catch(() => {});
  }

  if (!orgToken || !projectId) {
    return (
      <ErrorAlert message="Organisation ou projet manquant. Revenez à la liste des projets." />
    );
  }

  if (!data && loading) {
    return <LoadingState />;
  }

  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger le projet'} onRetry={reload} />;
  }

  const p = data.project;
  const created = p.createdAt ? new Date(p.createdAt).toLocaleString('fr-FR') : '—';

  return (
    <div className="webhook-projects-page op-project-edit">
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}

      <header className="op-project-edit__header">
        <div className="op-project-edit__header-row">
          <div>
            <h1 className="op-project-edit__title h4 m-0 d-flex align-items-center">
              <i className="fas fa-pen op-project-edit__title-icon" aria-hidden="true" />
              Modifier le projet
            </h1>
          </div>
          <Link to="/projects" className="btn btn-sm op-project-edit__btn-back">
            <i className="fas fa-arrow-left mr-1" aria-hidden="true" />
            Liste des projets
          </Link>
        </div>
      </header>

      <ul className="nav op-project-edit-tabs flex-wrap" role="tablist">
        {PANES.map((pane) => (
          <li className="nav-item" key={pane.id}>
            <button
              type="button"
              className={`nav-link ${activeTab === pane.id ? 'active' : ''}`}
              onClick={() => setTab(pane.id)}
              role="tab"
              aria-selected={activeTab === pane.id}
            >
              <i className={`fas ${pane.icon} mr-1`} aria-hidden="true" />
              {pane.label}
            </button>
          </li>
        ))}
      </ul>

      <form onSubmit={onSubmit}>
        <PageCard className="op-projects-card content-card op-project-edit-card">
          <div className="op-projects-card-body op-project-edit-card-body">
          {activeTab === 'pe-pane-general' ? (
            <div className="pe-pane" id="pe-pane-general">
              <h2 className="op-project-edit__pane-title h6">Général</h2>
              <div className="form-group">
                <label htmlFor="pe-name">Nom du projet</label>
                <input
                  id="pe-name"
                  type="text"
                  className="form-control form-control-sm"
                  maxLength={180}
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  disabled={busy}
                  autoComplete="off"
                />
              </div>
              <p className="op-project-edit__meta small mb-0">
                Créé le <time dateTime={p.createdAt}>{created}</time>
              </p>
            </div>
          ) : null}

          {activeTab === 'pe-pane-members' ? (
            <div className="pe-pane" id="pe-pane-members">
              <h2 className="op-project-edit__pane-title h6">Membres affectés aux tickets</h2>
              <p className="op-project-edit__hint small mb-3">Parmi les membres de l’organisation.</p>
              <div className="pe-handlers-form-widget d-flex flex-column" style={{ gap: '0.5rem' }}>
                {(data.members ?? []).map((m) => (
                  <label key={m.id} className="d-flex align-items-center mb-0" style={{ gap: '0.5rem' }}>
                    <input
                      type="checkbox"
                      checked={ticketHandlerIds.includes(m.id)}
                      onChange={() => toggleHandler(m.id)}
                      disabled={busy}
                    />
                    <span>{m.label}</span>
                  </label>
                ))}
              </div>
            </div>
          ) : null}

          {activeTab === 'pe-pane-indicators' ? (
            <div className="pe-pane" id="pe-pane-indicators">
              <h2 className="op-project-edit__pane-title h6">Indicateurs (minutes)</h2>
              <div className="form-row">
                <div className="form-group col-md-6">
                  <label htmlFor="pe-sla-ack">Objectif prise en charge</label>
                  <input
                    id="pe-sla-ack"
                    type="number"
                    min={1}
                    className="form-control form-control-sm"
                    placeholder="60"
                    value={slaAck}
                    onChange={(e) => setSlaAck(e.target.value)}
                    disabled={busy}
                  />
                </div>
                <div className="form-group col-md-6">
                  <label htmlFor="pe-sla-resolve">Objectif résolution</label>
                  <input
                    id="pe-sla-resolve"
                    type="number"
                    min={1}
                    className="form-control form-control-sm"
                    placeholder="480"
                    value={slaResolve}
                    onChange={(e) => setSlaResolve(e.target.value)}
                    disabled={busy}
                  />
                </div>
              </div>
            </div>
          ) : null}

          {activeTab === 'pe-pane-mail' ? (
            <div className="pe-pane" id="pe-pane-mail">
              <h2 className="op-project-edit__pane-title h6">Messagerie IMAP</h2>
              <div className="form-group form-check">
                <label className="form-check-label">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    checked={imapEnabled}
                    onChange={(e) => setImapEnabled(e.target.checked)}
                    disabled={busy}
                  />
                  Activer la récupération des e-mails en tickets
                </label>
              </div>
              <div className="form-row">
                <div className="form-group col-md-8">
                  <label htmlFor="pe-imap-host">Serveur IMAP</label>
                  <input
                    id="pe-imap-host"
                    type="text"
                    className="form-control form-control-sm"
                    value={imapHost}
                    onChange={(e) => setImapHost(e.target.value)}
                    disabled={busy}
                    autoComplete="off"
                  />
                </div>
                <div className="form-group col-md-4">
                  <label htmlFor="pe-imap-port">Port</label>
                  <input
                    id="pe-imap-port"
                    type="number"
                    min={1}
                    max={65535}
                    className="form-control form-control-sm"
                    value={imapPort}
                    onChange={(e) => setImapPort(e.target.value)}
                    disabled={busy}
                  />
                </div>
              </div>
              <div className="form-group form-check">
                <label className="form-check-label">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    checked={imapTls}
                    onChange={(e) => setImapTls(e.target.checked)}
                    disabled={busy}
                  />
                  Connexion TLS/SSL
                </label>
              </div>
              <div className="form-group">
                <label htmlFor="pe-imap-user">Identifiant</label>
                <input
                  id="pe-imap-user"
                  type="text"
                  className="form-control form-control-sm"
                  value={imapUsername}
                  onChange={(e) => setImapUsername(e.target.value)}
                  disabled={busy}
                  autoComplete="username"
                />
              </div>
              <div className="form-group">
                <label htmlFor="pe-imap-pw">Mot de passe</label>
                <input
                  id="pe-imap-pw"
                  type="password"
                  className="form-control form-control-sm"
                  value={imapPassword}
                  onChange={(e) => setImapPassword(e.target.value)}
                  disabled={busy}
                  placeholder={p.hasImapPassword ? 'Laisser vide pour conserver' : ''}
                  autoComplete="new-password"
                />
              </div>
              <div className="form-group">
                <label htmlFor="pe-imap-box">Dossier</label>
                <input
                  id="pe-imap-box"
                  type="text"
                  className="form-control form-control-sm"
                  value={imapMailbox}
                  onChange={(e) => setImapMailbox(e.target.value)}
                  disabled={busy}
                />
              </div>
              <button
                type="button"
                className="btn btn-sm op-project-edit__btn-outline"
                disabled={busy || !data.testImapCsrf}
                onClick={() => onTestImap()}
              >
                <i className="fas fa-plug mr-1" aria-hidden="true" />
                Tester la connexion
              </button>
            </div>
          ) : null}

          {activeTab === 'pe-pane-webhook' ? (
            <div className="pe-pane" id="pe-pane-webhook">
              <h2 className="op-project-edit__pane-title h6">URL du webhook (POST)</h2>
              <p className="op-project-edit__hint small mb-3">
                Envoyez du JSON ou du texte brut. GET pour vérifier que le jeton est valide.
              </p>
              <div className="input-group input-group-sm op-project-edit__webhook-group mb-2">
                <input
                  type="text"
                  readOnly
                  className="form-control font-monospace small"
                  value={p.webhookUrl || ''}
                />
                <div className="input-group-append">
                  <button
                    type="button"
                    className="btn op-project-edit__btn-input-addon"
                    onClick={() => copyWebhook(p.webhookUrl)}
                  >
                    Copier
                  </button>
                </div>
              </div>
              {p.webhookPingUrl ? (
                <p className="op-project-edit__hint small mb-0">
                  <a className="op-project-edit__link" href={p.webhookPingUrl} target="_blank" rel="noreferrer">
                    Ouvrir le ping (GET)
                  </a>
                </p>
              ) : null}
            </div>
          ) : null}

          <div className="op-project-edit__actions d-flex flex-wrap align-items-center">
            <button type="submit" className="btn btn-sm btn-primary op-new-btn" disabled={busy || !name.trim()}>
              {busy ? (
                <>
                  <i className="fas fa-circle-notch fa-spin mr-1" aria-hidden="true" />
                  Enregistrement…
                </>
              ) : (
                <>
                  <i className="fas fa-save mr-1" aria-hidden="true" />
                  Enregistrer
                </>
              )}
            </button>
            <Link to="/projects" className="btn btn-sm op-project-edit__btn-back">
              Annuler
            </Link>
          </div>
          </div>
        </PageCard>
      </form>
    </div>
  );
}
