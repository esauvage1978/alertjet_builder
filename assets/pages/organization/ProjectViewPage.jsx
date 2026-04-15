import { useCallback } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';
import {
  contrastTextForBackground,
  darkenBorderHex,
  normalizeHex,
} from '../../js/projectAccentColors.js';

function formatMinutes(n) {
  if (n == null || n === '') return '—';
  const v = Number(n);
  return Number.isFinite(v) ? `${v} min` : '—';
}

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
      const partDay = day[part];
      if (!partDay || typeof partDay !== 'object') continue;
      const s = typeof partDay.start === 'string' ? partDay.start : '';
      const e = typeof partDay.end === 'string' ? partDay.end : '';
      if (s) out[d.key][part].start = s;
      if (e) out[d.key][part].end = e;
    }
  }
  return out;
}

export default function ProjectViewPage() {
  const { orgToken: orgFromRoute, projectId } = useParams();
  const { data: boot } = useBootstrap();
  const orgToken = orgFromRoute ?? boot.currentOrganization?.publicToken;

  const loadFn = useCallback(async () => {
    if (!orgToken || !projectId) {
      throw new Error('Organisation ou projet manquant.');
    }
    return fetchJson(`/organisation/${orgToken}/projets/${projectId}`);
  }, [orgToken, projectId]);

  const { data, error, loading, reload } = useAsyncResource(loadFn);

  if (!orgToken || !projectId) {
    return <ErrorAlert message="Organisation ou projet manquant. Revenez à la liste des projets." />;
  }

  if (!data && loading) {
    return <LoadingState />;
  }

  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger le projet'} onRetry={reload} />;
  }

  const p = data.project;
  const created = p.createdAt ? new Date(p.createdAt).toLocaleString('fr-FR') : '—';
  const handlers = Array.isArray(p.handlers) ? p.handlers : [];
  const accentBg = normalizeHex(p.accentColor) || '#64748b';
  const accentFg = normalizeHex(p.accentTextColor) || contrastTextForBackground(accentBg);
  const accentBd = normalizeHex(p.accentBorderColor) || darkenBorderHex(accentBg);

  const phoneOn = Boolean(p.phoneIntegrationEnabled);
  const internalFormOn = Boolean(p.internalFormIntegrationEnabled);
  const phoneSchedule = safePhoneSchedule(p.phoneSchedule);

  return (
    <div className="webhook-projects-page op-project-edit op-project-view">
      <header className="op-project-edit__header">
        <div className="op-project-edit__header-row">
          <div>
            <h1 className="op-project-edit__title h4 m-0 d-flex align-items-center">
              <i className="fas fa-folder-open op-project-edit__title-icon" aria-hidden="true" />
              {p.name || 'Projet'}
            </h1>
            <p className="op-project-edit__meta small mb-0 mt-2">
              Jeton public{' '}
              <span className="font-monospace">{p.publicToken || p.public_token || projectId}</span> · Créé le{' '}
              <time dateTime={p.createdAt}>{created}</time>
            </p>
          </div>
          <div className="d-flex flex-wrap align-items-center" style={{ gap: '0.5rem' }}>
            <Link to={`/projects/${projectId}/edit`} className="btn btn-sm btn-primary">
              <i className="fas fa-pen mr-1" aria-hidden="true" />
              Modifier le projet
            </Link>
            <Link to="/projects" className="btn btn-sm op-project-edit__btn-back">
              <i className="fas fa-arrow-left mr-1" aria-hidden="true" />
              Liste des projets
            </Link>
          </div>
        </div>
      </header>

      <div className="row">
        <div className="col-lg-6 mb-3">
          <PageCard className="op-projects-card content-card op-project-edit-card h-100">
            <div className="op-projects-card-body op-project-edit-card-body">
              <h2 className="op-project-edit__pane-title h6">Indicateurs (SLA)</h2>
              <dl className="mb-0 small op-project-view__dl">
                <dt>Objectif prise en charge</dt>
                <dd>{formatMinutes(p.slaAckTargetMinutes)}</dd>
                <dt>Objectif résolution</dt>
                <dd>{formatMinutes(p.slaResolveTargetMinutes)}</dd>
              </dl>
            </div>
          </PageCard>
        </div>
        <div className="col-lg-6 mb-3">
          <PageCard className="op-projects-card content-card op-project-edit-card h-100">
            <div className="op-projects-card-body op-project-edit-card-body">
              <h2 className="op-project-edit__pane-title h6">Tickets</h2>
              <p className="mb-0">
                <span className="font-weight-bold" style={{ fontSize: '1.35rem' }}>
                  {p.ticketCount ?? 0}
                </span>{' '}
                <span className="text-muted">ticket(s)</span>
              </p>
            </div>
          </PageCard>
        </div>
      </div>

      <PageCard className="op-projects-card content-card op-project-edit-card mb-3">
        <div className="op-projects-card-body op-project-edit-card-body">
          <h2 className="op-project-edit__pane-title h6">Membres affectés aux tickets</h2>
          {handlers.length === 0 ? (
            <p className="op-project-edit__hint small mb-0">Aucun membre affecté.</p>
          ) : (
            <ul className="list-unstyled mb-0 op-project-view__handlers">
              {handlers.map((h) => (
                <li key={h.id} className="d-flex align-items-center py-1" style={{ gap: '0.5rem' }}>
                  <span
                    className="d-inline-flex align-items-center justify-content-center flex-shrink-0"
                    style={{
                      width: 26,
                      height: 26,
                      borderRadius: 999,
                      backgroundColor: accentBg,
                      color: accentFg,
                      fontWeight: 700,
                      letterSpacing: '0.02em',
                      fontSize: '0.75rem',
                      border: `1px solid ${accentBd}`,
                    }}
                    title={`Couleurs du projet : ${accentBg} / ${accentFg} / ${accentBd}`}
                    aria-label={`Initiales ${h.initials}`}
                  >
                    {h.initials}
                  </span>
                  <span>{h.label}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </PageCard>

      {p.webhookIntegrationEnabled !== false ? (
        <PageCard className="op-projects-card content-card op-project-edit-card mb-3">
          <div className="op-projects-card-body op-project-edit-card-body">
            <h2 className="op-project-edit__pane-title h6">Webhook</h2>
            <p className="op-project-edit__hint small mb-2">URL d’ingestion (POST).</p>
            <input
              type="text"
              readOnly
              className="form-control form-control-sm font-monospace small mb-2"
              value={p.webhookUrl || ''}
            />
            {p.webhookPingUrl ? (
              <p className="op-project-edit__hint small mb-0">
                <a className="op-project-edit__link" href={p.webhookPingUrl} target="_blank" rel="noreferrer">
                  Ping (GET)
                </a>
              </p>
            ) : null}
          </div>
        </PageCard>
      ) : null}

      <PageCard className="op-projects-card content-card op-project-edit-card mb-3">
        <div className="op-projects-card-body op-project-edit-card-body">
          <h2 className="op-project-edit__pane-title h6">Téléphone</h2>
          <p className="op-project-edit__hint small mb-2">
            {phoneOn ? (
              <span className="text-success font-weight-bold">Activée</span>
            ) : (
              <span className="text-muted">Désactivée</span>
            )}
          </p>
          {phoneOn ? (
            <>
              <dl className="mb-3 small op-project-view__dl">
                <dt>Numéro de téléphone</dt>
                <dd>{p.phoneNumber?.trim() ? p.phoneNumber : '—'}</dd>
                <dt>Urgences (optionnel)</dt>
                <dd>{p.emergencyPhone?.trim() ? p.emergencyPhone : '—'}</dd>
              </dl>
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
                      const morning = day?.morning ?? { start: '', end: '' };
                      const evening = day?.evening ?? { start: '', end: '' };
                      return (
                        <tr key={d.key}>
                          <td className="align-middle">{d.label}</td>
                          <td className="align-middle">{day?.enabled ? 'Oui' : 'Non'}</td>
                          <td className="align-middle">
                            {day?.enabled ? `${morning.start} – ${morning.end}` : '—'}
                          </td>
                          <td className="align-middle">
                            {day?.enabled ? `${evening.start} – ${evening.end}` : '—'}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </>
          ) : (
            <p className="op-project-edit__hint small mb-0 text-muted">
              Activez l’intégration téléphone dans l’édition du projet pour renseigner numéro et horaires.
            </p>
          )}
        </div>
      </PageCard>

      <PageCard className="op-projects-card content-card op-project-edit-card mb-3">
        <div className="op-projects-card-body op-project-edit-card-body">
          <h2 className="op-project-edit__pane-title h6">Formulaire interne</h2>
          <p className="op-project-edit__hint small mb-2">
            {internalFormOn ? (
              <span className="text-success font-weight-bold">Activé</span>
            ) : (
              <span className="text-muted">Désactivé</span>
            )}
          </p>
          {internalFormOn ? (
            <p className="mb-0 small">
              La création de tickets via le formulaire interne est autorisée pour ce projet (selon les droits des
              membres dans l’application).
            </p>
          ) : (
            <p className="op-project-edit__hint small mb-0 text-muted">
              L’option peut être activée dans l’édition du projet (onglet Intégrations).
            </p>
          )}
        </div>
      </PageCard>

      <PageCard className="op-projects-card content-card op-project-edit-card">
        <div className="op-projects-card-body op-project-edit-card-body">
          <h2 className="op-project-edit__pane-title h6">Messagerie IMAP</h2>
          <p className="op-project-edit__hint small mb-2">
            {p.imapEnabled ? (
              <span className="text-success font-weight-bold">Activée</span>
            ) : (
              <span className="text-muted">Désactivée</span>
            )}
          </p>
          {p.imapEnabled ? (
            <dl className="mb-0 small op-project-view__dl">
              <dt>Serveur</dt>
              <dd>
                {p.imapHost || '—'}:{p.imapPort ?? '—'}
                {p.imapTls ? ' (TLS)' : ''}
              </dd>
              <dt>Identifiant</dt>
              <dd>{p.imapUsername || '—'}</dd>
              <dt>Dossier</dt>
              <dd>{p.imapMailbox || '—'}</dd>
              <dt>Mot de passe</dt>
              <dd>{p.hasImapPasswordConfigured ? 'Configuré' : 'Non renseigné'}</dd>
            </dl>
          ) : null}
        </div>
      </PageCard>
    </div>
  );
}
