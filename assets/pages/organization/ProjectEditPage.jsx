import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson, postForm } from '../../api/http.js';
import { TicketHandlerMiniCard } from '../../components/project/TicketHandlerMiniCard.jsx';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

/** URL longue affichée avec ellipse au milieu (cartes type workflow). */
function formatWebhookUrlForDisplay(url) {
  if (!url || typeof url !== 'string') return '';
  const u = url.trim();
  if (u.length <= 72) return u;
  return `${u.slice(0, 34)}…${u.slice(-34)}`;
}

/** Exemple de JSON documenté (aligné sur TicketIngestionService::ingestFromWebhook). */
const WEBHOOK_JSON_EXAMPLE = `{
  "title": "Incident paiement — timeout",
  "message": "Description détaillée, logs ou corps d'erreur…",
  "priority": "high",
  "dedupe_key": "mon-service:erreur-xyz-2025-04-13"
}`;

const DEFAULT_PHONE_SCHEDULE = {
  mon: { enabled: true, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
  tue: { enabled: true, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
  wed: { enabled: true, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
  thu: { enabled: true, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
  fri: { enabled: true, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
  sat: { enabled: false, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
  sun: { enabled: false, morning: { start: '08:00', end: '12:00' }, evening: { start: '14:00', end: '18:00' } },
};

const PHONE_DAYS = [
  { key: 'mon', label: 'Lundi' },
  { key: 'tue', label: 'Mardi' },
  { key: 'wed', label: 'Mercredi' },
  { key: 'thu', label: 'Jeudi' },
  { key: 'fri', label: 'Vendredi' },
  { key: 'sat', label: 'Samedi' },
  { key: 'sun', label: 'Dimanche' },
];

function safePhoneSchedule(v) {
  if (!v || typeof v !== 'object') return structuredClone(DEFAULT_PHONE_SCHEDULE);
  const out = structuredClone(DEFAULT_PHONE_SCHEDULE);
  for (const d of PHONE_DAYS) {
    const day = v[d.key];
    if (!day || typeof day !== 'object') continue;
    if (typeof day.enabled === 'boolean') out[d.key].enabled = day.enabled;
    for (const part of ['morning', 'evening']) {
      const p = day[part];
      if (!p || typeof p !== 'object') continue;
      const s = typeof p.start === 'string' ? p.start : '';
      const e = typeof p.end === 'string' ? p.end : '';
      if (s) out[d.key][part].start = s;
      if (e) out[d.key][part].end = e;
    }
  }
  return out;
}

const PANES = [
  { id: 'pe-pane-general', label: 'Général', icon: 'fa-sliders-h' },
  { id: 'pe-pane-members', label: 'Membres', icon: 'fa-users' },
  { id: 'pe-pane-indicators', label: 'Indicateurs', icon: 'fa-chart-line' },
  { id: 'pe-pane-integrations', label: 'Intégrations', icon: 'fa-puzzle-piece' },
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
  const [imapTestBusy, setImapTestBusy] = useState(false);
  const [imapTestFeedback, setImapTestFeedback] = useState(null);
  const [webhookTestBusy, setWebhookTestBusy] = useState(false);
  const [webhookTestFeedback, setWebhookTestFeedback] = useState(null);

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
  const [webhookIntegrationEnabled, setWebhookIntegrationEnabled] = useState(true);
  const [webhookCorsAllowedOrigins, setWebhookCorsAllowedOrigins] = useState('');
  const [phoneIntegrationEnabled, setPhoneIntegrationEnabled] = useState(false);
  const [internalFormIntegrationEnabled, setInternalFormIntegrationEnabled] = useState(false);
  const [phoneSchedule, setPhoneSchedule] = useState(() => structuredClone(DEFAULT_PHONE_SCHEDULE));
  const [phoneNumber, setPhoneNumber] = useState('');
  const [emergencyPhone, setEmergencyPhone] = useState('');
  /** Sous-vue dans l’onglet Intégrations : général | messagerie | webhook */
  const [integrationSub, setIntegrationSub] = useState('general');

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
    setWebhookIntegrationEnabled(
      typeof p.webhookIntegrationEnabled === 'boolean' ? p.webhookIntegrationEnabled : true,
    );
    setWebhookCorsAllowedOrigins(
      typeof p.webhookCorsAllowedOrigins === 'string' ? p.webhookCorsAllowedOrigins : '',
    );
    setPhoneIntegrationEnabled(
      typeof p.phoneIntegrationEnabled === 'boolean' ? p.phoneIntegrationEnabled : false,
    );
    setInternalFormIntegrationEnabled(
      typeof p.internalFormIntegrationEnabled === 'boolean' ? p.internalFormIntegrationEnabled : false,
    );
    setPhoneSchedule(safePhoneSchedule(p.phoneSchedule));
    setPhoneNumber(typeof p.phoneNumber === 'string' ? p.phoneNumber : '');
    setEmergencyPhone(typeof p.emergencyPhone === 'string' ? p.emergencyPhone : '');
  }, [data]);

  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (hash === 'pe-pane-mail') {
      setActiveTab('pe-pane-integrations');
      setIntegrationSub('mail');
      return;
    }
    if (hash === 'pe-pane-webhook') {
      setActiveTab('pe-pane-integrations');
      setIntegrationSub('webhook');
      return;
    }
    if (hash === 'pe-pane-phone') {
      setActiveTab('pe-pane-integrations');
      setIntegrationSub('phone');
      return;
    }
    if (hash && /^pe-pane-[a-z0-9-]+$/.test(hash)) {
      setActiveTab(hash);
    }
  }, []);

  useEffect(() => {
    if (!imapEnabled && integrationSub === 'mail') {
      setIntegrationSub('general');
    }
  }, [imapEnabled, integrationSub]);

  useEffect(() => {
    if (!webhookIntegrationEnabled && integrationSub === 'webhook') {
      setIntegrationSub('general');
    }
  }, [webhookIntegrationEnabled, integrationSub]);

  useEffect(() => {
    if (!phoneIntegrationEnabled && integrationSub === 'phone') {
      setIntegrationSub('general');
    }
  }, [phoneIntegrationEnabled, integrationSub]);

  function setTab(id) {
    setActiveTab(id);
    if (id === 'pe-pane-integrations') {
      setIntegrationSub('general');
    }
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
      [`${prefix}[webhookIntegrationEnabled]`]: webhookIntegrationEnabled ? '1' : '0',
      [`${prefix}[webhookCorsAllowedOrigins]`]: webhookCorsAllowedOrigins,
      [`${prefix}[phoneIntegrationEnabled]`]: phoneIntegrationEnabled ? '1' : '0',
      [`${prefix}[internalFormIntegrationEnabled]`]: internalFormIntegrationEnabled ? '1' : '0',
      [`${prefix}[phoneNumber]`]: phoneNumber.trim(),
      [`${prefix}[emergencyPhone]`]: emergencyPhone.trim(),
      [`${prefix}[phoneSchedule]`]: JSON.stringify(phoneSchedule),
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
    setImapTestBusy(true);
    setImapTestFeedback(null);
    try {
      const res = await postForm(
        `/organisation/${orgToken}/projets/${projectId}/test-imap`,
        { _token: data.testImapCsrf },
        { json: true },
      );
      const ct = res.headers.get('Content-Type') || '';
      if (!ct.includes('application/json')) {
        throw new Error('Réponse inattendue du serveur.');
      }
      const payload = await res.json();
      if (res.ok) {
        setImapTestFeedback({
          type: payload.ok ? 'success' : 'danger',
          message: typeof payload.message === 'string' ? payload.message : '',
        });
        return;
      }
      setImapTestFeedback({
        type: 'danger',
        message:
          typeof payload.message === 'string' && payload.message
            ? payload.message
            : `Erreur ${res.status}`,
      });
    } catch (e) {
      setImapTestFeedback({
        type: 'danger',
        message: e.message || 'Erreur réseau',
      });
    } finally {
      setImapTestBusy(false);
    }
  }

  function copyWebhook(url) {
    if (!url || !navigator.clipboard?.writeText) return;
    navigator.clipboard.writeText(url).catch(() => {});
  }

  async function onTestWebhook() {
    const url = data?.project?.webhookUrl;
    if (!url) return;
    setWebhookTestBusy(true);
    setWebhookTestFeedback(null);
    try {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          title: 'Test webhook (AlertJet Builder)',
          message: "Requête de test envoyée depuis l'onglet Intégrations.",
        }),
      });
      const ct = res.headers.get('Content-Type') || '';
      let payload = null;
      if (ct.includes('application/json')) {
        try {
          payload = await res.json();
        } catch {
          payload = null;
        }
      }
      if (res.status === 429) {
        setWebhookTestFeedback({
          type: 'warning',
          message: 'Trop de requêtes — réessayez dans quelques secondes.',
        });
        return;
      }
      if (res.ok && payload && payload.ok === true) {
        const id = payload.publicId != null ? String(payload.publicId) : payload.ticketId;
        setWebhookTestFeedback({
          type: 'success',
          message: payload.merged
            ? `Événement fusionné sur le ticket ${id}.`
            : `Ticket créé — identifiant ${id}.`,
        });
        return;
      }
      const errMsg =
        payload?.error === 'unknown_webhook_token'
          ? 'Jeton webhook inconnu.'
          : typeof payload?.error === 'string'
            ? payload.error
            : res.statusText || `Erreur ${res.status}`;
      setWebhookTestFeedback({ type: 'danger', message: errMsg });
    } catch (e) {
      setWebhookTestFeedback({ type: 'danger', message: e.message || 'Erreur réseau' });
    } finally {
      setWebhookTestBusy(false);
    }
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
              <p className="op-project-edit__hint small mb-3">Cliquez sur une carte pour inclure ou retirer un membre du traitement des tickets.</p>
              {(data.members ?? []).length === 0 ? (
                <p className="op-project-edit__hint small mb-0">Aucun membre dans cette organisation.</p>
              ) : (
                <div
                  className="ticket-handler-mini-card-grid"
                  role="group"
                  aria-label="Membres affectés aux tickets"
                >
                  {(data.members ?? []).map((m) => {
                    const selected = ticketHandlerIds.includes(m.id);
                    const displayName =
                      typeof m.displayName === 'string' && m.displayName.trim() !== ''
                        ? m.displayName
                        : typeof m.label === 'string' && m.label.includes('(')
                          ? m.label.replace(/\s*\([^)]*\)\s*$/, '').trim()
                          : m.label ?? '—';
                    const email = typeof m.email === 'string' ? m.email : '';
                    const initials =
                      typeof m.initials === 'string' && m.initials.trim() !== ''
                        ? m.initials
                        : displayName
                            .split(/\s+/)
                            .filter(Boolean)
                            .slice(0, 2)
                            .map((p) => p[0])
                            .join('')
                            .toUpperCase() || '?';
                    return (
                      <TicketHandlerMiniCard
                        key={m.id}
                        displayName={displayName}
                        email={email}
                        initials={initials}
                        avatarColor={m.avatarColor}
                        avatarForegroundColor={m.avatarForegroundColor}
                        selected={selected}
                        disabled={busy}
                        onToggle={() => toggleHandler(m.id)}
                      />
                    );
                  })}
                </div>
              )}
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

          {activeTab === 'pe-pane-integrations' ? (
            <div className="pe-pane pe-pane-integrations" id="pe-pane-integrations">
              <h2 className="op-project-edit__pane-title h6">Intégrations</h2>
              <div className="pe-int-layout">
                <nav className="pe-int-nav" aria-label="Sous-sections intégrations">
                  <button
                    type="button"
                    className={`pe-int-nav__btn ${integrationSub === 'general' ? 'pe-int-nav__btn--active' : ''}`}
                    onClick={() => setIntegrationSub('general')}
                  >
                    Général
                  </button>
                  {imapEnabled ? (
                    <button
                      type="button"
                      className={`pe-int-nav__btn ${integrationSub === 'mail' ? 'pe-int-nav__btn--active' : ''}`}
                      onClick={() => setIntegrationSub('mail')}
                    >
                      <i className="fas fa-envelope mr-1" aria-hidden="true" />
                      Messagerie
                    </button>
                  ) : null}
                  {webhookIntegrationEnabled ? (
                    <button
                      type="button"
                      className={`pe-int-nav__btn ${integrationSub === 'webhook' ? 'pe-int-nav__btn--active' : ''}`}
                      onClick={() => setIntegrationSub('webhook')}
                    >
                      <i className="fas fa-plug mr-1" aria-hidden="true" />
                      Webhook
                    </button>
                  ) : null}
                  {phoneIntegrationEnabled ? (
                    <button
                      type="button"
                      className={`pe-int-nav__btn ${integrationSub === 'phone' ? 'pe-int-nav__btn--active' : ''}`}
                      onClick={() => setIntegrationSub('phone')}
                    >
                      <i className="fas fa-phone-alt mr-1" aria-hidden="true" />
                      Téléphone
                    </button>
                  ) : null}
                </nav>
                <div className="pe-int-body">
                  {integrationSub === 'general' ? (
                    <div className="pe-int-panel" id="pe-int-panel-general">
                      <h3 className="pe-int-panel__title h6">Options d’intégration</h3>
                      <p className="op-project-edit__hint small mb-3">
                        Activez une intégration pour afficher le menu correspondant et configurer les paramètres.
                      </p>
                      <div className="form-group pe-mail-switch-row mb-3">
                        <label className="pe-mail-switch" htmlFor="pe-int-switch-imap">
                          <input
                            id="pe-int-switch-imap"
                            type="checkbox"
                            role="switch"
                            className="pe-mail-switch__input"
                            checked={imapEnabled}
                            aria-checked={imapEnabled}
                            onChange={(e) => setImapEnabled(e.target.checked)}
                            disabled={busy}
                          />
                          <span className="pe-mail-switch__track" aria-hidden="true">
                            <span className="pe-mail-switch__thumb" />
                          </span>
                          <span className="pe-mail-switch__label">Messagerie (IMAP)</span>
                        </label>
                      </div>
                      <div className="form-group pe-mail-switch-row">
                        <label className="pe-mail-switch" htmlFor="pe-int-switch-webhook">
                          <input
                            id="pe-int-switch-webhook"
                            type="checkbox"
                            role="switch"
                            className="pe-mail-switch__input"
                            checked={webhookIntegrationEnabled}
                            aria-checked={webhookIntegrationEnabled}
                            onChange={(e) => setWebhookIntegrationEnabled(e.target.checked)}
                            disabled={busy}
                          />
                          <span className="pe-mail-switch__track" aria-hidden="true">
                            <span className="pe-mail-switch__thumb" />
                          </span>
                          <span className="pe-mail-switch__label">Webhook HTTP (POST)</span>
                        </label>
                      </div>
                      <div className="form-group pe-mail-switch-row mb-3">
                        <label className="pe-mail-switch" htmlFor="pe-int-switch-phone">
                          <input
                            id="pe-int-switch-phone"
                            type="checkbox"
                            role="switch"
                            className="pe-mail-switch__input"
                            checked={phoneIntegrationEnabled}
                            aria-checked={phoneIntegrationEnabled}
                            onChange={(e) => setPhoneIntegrationEnabled(e.target.checked)}
                            disabled={busy}
                          />
                          <span className="pe-mail-switch__track" aria-hidden="true">
                            <span className="pe-mail-switch__thumb" />
                          </span>
                          <span className="pe-mail-switch__label">Téléphone</span>
                        </label>
                      </div>
                      <div className="form-group pe-mail-switch-row">
                        <label className="pe-mail-switch" htmlFor="pe-int-switch-internal-form">
                          <input
                            id="pe-int-switch-internal-form"
                            type="checkbox"
                            role="switch"
                            className="pe-mail-switch__input"
                            checked={internalFormIntegrationEnabled}
                            aria-checked={internalFormIntegrationEnabled}
                            onChange={(e) => setInternalFormIntegrationEnabled(e.target.checked)}
                            disabled={busy}
                          />
                          <span className="pe-mail-switch__track" aria-hidden="true">
                            <span className="pe-mail-switch__thumb" />
                          </span>
                          <span className="pe-mail-switch__label">Formulaire interne</span>
                        </label>
                      </div>
                    </div>
                  ) : null}

                  {integrationSub === 'phone' && phoneIntegrationEnabled ? (
                    <div className="pe-int-panel" id="pe-int-panel-phone">
                      <h3 className="pe-int-panel__title h6">Téléphone — coordonnées et horaires</h3>
                      <div className="form-group">
                        <label htmlFor="pe-phone-number">
                          Numéro de téléphone <span className="text-danger">*</span>
                        </label>
                        <input
                          id="pe-phone-number"
                          type="tel"
                          className="form-control form-control-sm"
                          maxLength={48}
                          value={phoneNumber}
                          onChange={(e) => setPhoneNumber(e.target.value)}
                          disabled={busy}
                          autoComplete="tel"
                          inputMode="tel"
                          placeholder="+33 1 23 45 67 89"
                        />
                      </div>
                      <div className="form-group">
                        <label htmlFor="pe-phone-emergency">Urgences (optionnel)</label>
                        <input
                          id="pe-phone-emergency"
                          type="text"
                          className="form-control form-control-sm"
                          maxLength={255}
                          value={emergencyPhone}
                          onChange={(e) => setEmergencyPhone(e.target.value)}
                          disabled={busy}
                          autoComplete="off"
                          placeholder="Numéro ou consigne pour les urgences"
                        />
                      </div>
                      <div className="table-responsive">
                        <table className="table table-sm mb-0">
                          <thead>
                            <tr>
                              <th scope="col">Jour</th>
                              <th scope="col">Actif</th>
                              <th scope="col">Matin</th>
                              <th scope="col">Soir</th>
                            </tr>
                          </thead>
                          <tbody>
                            {PHONE_DAYS.map((d) => {
                              const day = phoneSchedule[d.key];
                              return (
                                <tr key={d.key}>
                                  <td className="align-middle">{d.label}</td>
                                  <td className="align-middle">
                                    <div className="form-group pe-mail-switch-row mb-0">
                                      <label className="pe-mail-switch mb-0" htmlFor={`pe-phone-${d.key}-enabled`}>
                                        <input
                                          id={`pe-phone-${d.key}-enabled`}
                                          type="checkbox"
                                          role="switch"
                                          className="pe-mail-switch__input"
                                          checked={Boolean(day?.enabled)}
                                          aria-checked={Boolean(day?.enabled)}
                                          onChange={(e) =>
                                            setPhoneSchedule((prev) => ({
                                              ...prev,
                                              [d.key]: { ...prev[d.key], enabled: e.target.checked },
                                            }))
                                          }
                                          disabled={busy}
                                        />
                                        <span className="pe-mail-switch__track" aria-hidden="true">
                                          <span className="pe-mail-switch__thumb" />
                                        </span>
                                        <span className="sr-only">Activer {d.label}</span>
                                      </label>
                                    </div>
                                  </td>
                                  <td className="align-middle">
                                    <div className="form-row">
                                      <div className="col">
                                        <input
                                          type="time"
                                          className="form-control form-control-sm"
                                          value={day.morning.start}
                                          onChange={(e) =>
                                            setPhoneSchedule((prev) => ({
                                              ...prev,
                                              [d.key]: {
                                                ...prev[d.key],
                                                morning: { ...prev[d.key].morning, start: e.target.value },
                                              },
                                            }))
                                          }
                                          disabled={busy || !day.enabled}
                                        />
                                      </div>
                                      <div className="col">
                                        <input
                                          type="time"
                                          className="form-control form-control-sm"
                                          value={day.morning.end}
                                          onChange={(e) =>
                                            setPhoneSchedule((prev) => ({
                                              ...prev,
                                              [d.key]: {
                                                ...prev[d.key],
                                                morning: { ...prev[d.key].morning, end: e.target.value },
                                              },
                                            }))
                                          }
                                          disabled={busy || !day.enabled}
                                        />
                                      </div>
                                    </div>
                                  </td>
                                  <td className="align-middle">
                                    <div className="form-row">
                                      <div className="col">
                                        <input
                                          type="time"
                                          className="form-control form-control-sm"
                                          value={day.evening.start}
                                          onChange={(e) =>
                                            setPhoneSchedule((prev) => ({
                                              ...prev,
                                              [d.key]: {
                                                ...prev[d.key],
                                                evening: { ...prev[d.key].evening, start: e.target.value },
                                              },
                                            }))
                                          }
                                          disabled={busy || !day.enabled}
                                        />
                                      </div>
                                      <div className="col">
                                        <input
                                          type="time"
                                          className="form-control form-control-sm"
                                          value={day.evening.end}
                                          onChange={(e) =>
                                            setPhoneSchedule((prev) => ({
                                              ...prev,
                                              [d.key]: {
                                                ...prev[d.key],
                                                evening: { ...prev[d.key].evening, end: e.target.value },
                                              },
                                            }))
                                          }
                                          disabled={busy || !day.enabled}
                                        />
                                      </div>
                                    </div>
                                  </td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  ) : null}

                  {integrationSub === 'mail' && imapEnabled ? (
                    <div className="pe-int-panel" id="pe-int-panel-mail">
                      <h3 className="pe-int-panel__title h6">Messagerie IMAP</h3>
                      <p className="op-project-edit__hint small mb-3">
                        Paramètres de connexion et dossier lu par la commande d’import.
                      </p>
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
              <div className="form-group pe-mail-switch-row">
                <label className="pe-mail-switch" htmlFor="pe-imap-tls">
                  <input
                    id="pe-imap-tls"
                    type="checkbox"
                    role="switch"
                    className="pe-mail-switch__input"
                    checked={imapTls}
                    aria-checked={imapTls}
                    onChange={(e) => setImapTls(e.target.checked)}
                    disabled={busy}
                  />
                  <span className="pe-mail-switch__track" aria-hidden="true">
                    <span className="pe-mail-switch__thumb" />
                  </span>
                  <span className="pe-mail-switch__label">Connexion TLS/SSL</span>
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
                disabled={busy || imapTestBusy || !data.testImapCsrf}
                onClick={() => onTestImap()}
              >
                {imapTestBusy ? (
                  <i className="fas fa-circle-notch fa-spin mr-1" aria-hidden="true" />
                ) : (
                  <i className="fas fa-plug mr-1" aria-hidden="true" />
                )}
                {imapTestBusy ? 'Test en cours…' : 'Tester la connexion'}
              </button>
              {imapTestFeedback ? (
                <div
                  className={`alert mt-3 mb-0 py-2 small ${
                    imapTestFeedback.type === 'success' ? 'alert-success' : 'alert-danger'
                  }`}
                  role="status"
                >
                  {imapTestFeedback.message}
                </div>
              ) : null}
                    </div>
                  ) : null}

                  {integrationSub === 'webhook' && webhookIntegrationEnabled ? (
                    <div className="pe-int-panel" id="pe-int-panel-webhook">
                      <h3 className="pe-int-panel__title h6">Webhook HTTP (POST)</h3>
              <p className="op-project-edit__hint small mb-3">
                Envoyez du JSON ou du texte brut. Un GET sur l’URL vérifie que le jeton est valide (sans créer de ticket).
              </p>
              {p.webhookUrl ? (
                <>
                  <div
                    className="fw-url-box fw-url-box--compact fw-url-box--workflow-card mb-2"
                    title={p.webhookUrl}
                  >
                    <div className="fw-url-box__text">
                      <code className="fw-url-code">{formatWebhookUrlForDisplay(p.webhookUrl)}</code>
                    </div>
                    <div className="fw-url-box__actions">
                      <button
                        type="button"
                        className="fw-btn-ghost"
                        title="Envoyer un POST JSON de test (peut créer ou fusionner un ticket selon le contenu)"
                        disabled={webhookTestBusy || busy}
                        onClick={() => onTestWebhook()}
                      >
                        {webhookTestBusy ? (
                          <>
                            <i className="fas fa-circle-notch fa-spin mr-1" aria-hidden="true" />
                            Test…
                          </>
                        ) : (
                          'Tester'
                        )}
                      </button>
                      <button
                        type="button"
                        className="fw-btn-ghost"
                        onClick={() => copyWebhook(p.webhookUrl)}
                      >
                        Copier
                      </button>
                    </div>
                  </div>
                  {webhookTestFeedback ? (
                    <div
                      className={`alert py-2 small mb-2 ${
                        webhookTestFeedback.type === 'success'
                          ? 'alert-success'
                          : webhookTestFeedback.type === 'warning'
                            ? 'alert-warning'
                            : 'alert-danger'
                      }`}
                      role="status"
                    >
                      {webhookTestFeedback.message}
                    </div>
                  ) : null}
                </>
              ) : (
                <p className="op-project-edit__hint small mb-0">URL indisponible.</p>
              )}

              <div className="form-group mb-0">
                <label htmlFor="pe-webhook-cors" className="small font-weight-bold d-block mb-1">
                  Origines autorisées (CORS)
                </label>
                <textarea
                  id="pe-webhook-cors"
                  className="form-control form-control-sm"
                  rows={4}
                  value={webhookCorsAllowedOrigins}
                  onChange={(e) => setWebhookCorsAllowedOrigins(e.target.value)}
                  disabled={busy}
                  placeholder="https://app.exemple.fr"
                  spellCheck={false}
                  autoComplete="off"
                />
                <p className="op-project-edit__hint small mt-2 mb-0">
                  Une URL par ligne (http ou https). Vide = pas de filtre sur l’en-tête Origin (outils en ligne de
                  commande, scripts serveur). Si au moins une origine est indiquée, les POST depuis un navigateur dont
                  l’origine n’est pas listée sont refusés.
                </p>
              </div>

              <div className="fw-json-example mt-3">
                <h3 className="fw-json-example__title">Exemple de corps JSON (POST)</h3>
                <p className="op-project-edit__hint small mb-2">
                  En-tête <code className="fw-json-example__inline">Content-Type: application/json</code>. Champs
                  reconnus — tous optionnels sauf besoin d’un titre ou d’un message explicite :
                </p>
                <ul className="fw-json-example__fields small text-muted mb-2 pl-3">
                  <li>
                    <strong>title</strong>, <strong>subject</strong> ou <strong>summary</strong> — titre du ticket
                    (sinon « Incident »).
                  </li>
                  <li>
                    <strong>message</strong>, <strong>description</strong>, <strong>body</strong> ou <strong>detail</strong>{' '}
                    — description (sinon le corps brut du POST).
                  </li>
                  <li>
                    <strong>priority</strong> — <code>low</code> / <code>medium</code> / <code>high</code> /{' '}
                    <code>critical</code> (ou <code>1</code> à <code>4</code>).
                  </li>
                  <li>
                    <strong>dedupe_key</strong>, <strong>fingerprint</strong>, <strong>error_id</strong> ou{' '}
                    <strong>incident_key</strong> — même valeur pour fusionner les événements sur un ticket ouvert.
                  </li>
                </ul>
                <pre className="fw-json-example__pre" tabIndex={0}>
                  <code>{WEBHOOK_JSON_EXAMPLE}</code>
                </pre>
                <p className="op-project-edit__hint small mb-0">
                  Un corps texte brut (sans JSON) utilise tout le texte comme description ; le titre par défaut est «
                  Incident ».
                </p>
              </div>

              {p.webhookPingUrl ? (
                <p className="op-project-edit__hint small mt-3 mb-0">
                  <a className="op-project-edit__link" href={p.webhookPingUrl} target="_blank" rel="noreferrer">
                    Ouvrir le ping (GET)
                  </a>
                </p>
              ) : null}
                    </div>
                  ) : null}
                </div>
              </div>
            </div>
          ) : null}

          <div className="op-project-edit__actions d-flex flex-wrap align-items-center">
            <button type="submit" className="btn btn-sm btn-primary" disabled={busy || !name.trim()}>
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
