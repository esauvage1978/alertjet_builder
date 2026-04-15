import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';
import {
  contrastTextForBackground,
  darkenBorderHex,
  normalizeHex,
} from '../../js/projectAccentColors.js';

const STATUS_OPTIONS = [
  { value: 'open', label: 'Ouvert (legacy)' },
  { value: 'new', label: 'Nouveau' },
  { value: 'acknowledged', label: 'Pris en compte' },
  { value: 'in_progress', label: 'En cours' },
  { value: 'on_hold', label: 'En attente' },
  { value: 'resolved', label: 'Résolu' },
  { value: 'closed', label: 'Fermé' },
  { value: 'cancelled', label: 'Annulé' },
];

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Basse' },
  { value: 'medium', label: 'Moyenne' },
  { value: 'high', label: 'Haute' },
  { value: 'critical', label: 'Critique' },
];

const TYPE_OPTIONS = [
  { value: 'incident', label: 'Incident' },
  { value: 'problem', label: 'Problème' },
  { value: 'request', label: 'Demande' },
];

const TYPE_ICON = {
  incident: 'fa-exclamation-triangle',
  problem: 'fa-bug',
  request: 'fa-handshake',
};

const SOURCE_OPTIONS = [
  { value: 'phone', label: 'Téléphone' },
  { value: 'email', label: 'E-mail' },
  { value: 'webhook', label: 'Webhook' },
  { value: 'client_form', label: 'Formulaire client' },
  { value: 'internal_form', label: 'Formulaire interne' },
];

const LOG_TYPE_LABEL = {
  note: 'Note',
  status: 'Statut',
  assignment: 'Affectation',
};

function formatDateTime(iso) {
  if (iso == null || iso === '') return '—';
  try {
    return new Date(iso).toLocaleString('fr-FR', {
      dateStyle: 'short',
      timeStyle: 'short',
    });
  } catch {
    return String(iso);
  }
}

function formatSlaDue(iso) {
  if (!iso) return null;
  try {
    return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return String(iso);
  }
}

function logTypeLabel(type) {
  return LOG_TYPE_LABEL[type] || type || 'Événement';
}

const TE_TAB_MAIN = 'te-pane-main';
const TE_TAB_HISTORY = 'te-pane-history';
const TE_TAB_TECH = 'te-pane-tech';

function AssigneeCard({ member, selected, onSelect }) {
  const label = member ? member.label : '— Non assigné —';
  const initials = member ? member.initials : '—';
  const avatarColor = member ? member.avatarColor : '#e2e8f0';
  const avatarFg = member ? member.avatarForegroundColor : '#0f172a';

  return (
    <button
      type="button"
      className={`te-assignee-card ${selected ? 'te-assignee-card--selected' : ''}`}
      onClick={onSelect}
      aria-pressed={selected}
    >
      <span
        className="te-assignee-card__avatar"
        aria-hidden="true"
        style={{ backgroundColor: avatarColor, color: avatarFg }}
      >
        {initials}
      </span>
      <span className="te-assignee-card__text">
        <span className="te-assignee-card__label">{label}</span>
      </span>
      {selected ? (
        <span className="te-assignee-card__check" aria-hidden="true">
          ✓
        </span>
      ) : null}
    </button>
  );
}

async function patchTicket(id, body) {
  return fetchJson(`/api/tickets/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

async function sendClientMessage(id, body) {
  return fetchJson(`/api/tickets/${id}/client-message`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

async function validateClientForTicket(id) {
  return fetchJson(`/api/tickets/${id}/validate-client`, { method: 'POST' });
}

async function uploadTicketAttachments(id, files) {
  const fd = new FormData();
  Array.from(files || []).forEach((f) => {
    if (f instanceof File) fd.append('attachments[]', f, f.name);
  });
  return fetchJson(`/api/tickets/${id}/attachments`, { method: 'POST', body: fd });
}

export default function TicketEditPage() {
  const { ticketId } = useParams();
  const idNum = useMemo(() => {
    const n = Number.parseInt(String(ticketId), 10);
    return Number.isFinite(n) && n > 0 ? n : null;
  }, [ticketId]);

  const loadFn = useCallback(async () => {
    if (idNum == null) {
      return null;
    }
    return fetchJson(`/api/tickets/${idNum}`);
  }, [idNum]);

  const { data, error, loading, reload, setData } = useAsyncResource(loadFn);

  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [type, setType] = useState('');
  const [source, setSource] = useState('');
  const [onHoldReason, setOnHoldReason] = useState('');
  const [cancelReason, setCancelReason] = useState('');
  const [note, setNote] = useState('');
  const [assigneeUserId, setAssigneeUserId] = useState('');
  const [saveError, setSaveError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState(TE_TAB_MAIN);
  const [copiedEmail, setCopiedEmail] = useState(false);
  const [me, setMe] = useState(null);
  const [clientSubject, setClientSubject] = useState('');
  const [clientBody, setClientBody] = useState('');
  const [sendingClient, setSendingClient] = useState(false);
  const [clientSendError, setClientSendError] = useState(null);
  const [clientSendOk, setClientSendOk] = useState(false);
  const [actionModal, setActionModal] = useState(null); // { kind: 'on_hold'|'cancelled'|'resolved', title: string, placeholder: string, nextStatus: string, field: 'onHoldReason'|'cancelReason'|'note', saveNow?: boolean }
  const [actionReason, setActionReason] = useState('');
  const [clientModalOpen, setClientModalOpen] = useState(false);
  const [toasts, setToasts] = useState([]); // [{ id: string, message: string }]
  const [uploadingAttachments, setUploadingAttachments] = useState(false);
  const [attachmentsError, setAttachmentsError] = useState(null);
  const [dragActive, setDragActive] = useState(false);
  const fileInputRef = useRef(null);
  const [attachmentViewer, setAttachmentViewer] = useState(null); // { index: number }

  function pushToast(message) {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    setToasts((prev) => [{ id, message }, ...prev].slice(0, 5));
    window.setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 3800);
  }

  useEffect(() => {
    setActiveTab(TE_TAB_MAIN);
  }, [idNum]);

  useEffect(() => {
    let alive = true;
    fetchJson('/api/me').then((j) => {
      if (!alive) return;
      setMe(j?.authenticated ? j.user : null);
    });
    return () => {
      alive = false;
    };
  }, []);

  useEffect(() => {
    if (!data) return;
    setStatus(String(data.status ?? ''));
    setPriority(String(data.priority ?? ''));
    setType(String(data.type ?? ''));
    setSource(String(data.source ?? ''));
    setOnHoldReason(data.onHoldReason != null ? String(data.onHoldReason) : '');
    setCancelReason(data.cancelReason != null ? String(data.cancelReason) : '');
    setAssigneeUserId(data.assignee?.id != null ? String(data.assignee.id) : '');
    setNote('');
    setSaveError(null);
    setCopiedEmail(false);
    setClientSendError(null);
    setClientSendOk(false);
    const subject = `[Ticket ${data.publicId}] ${data.title ?? 'Question'}`.trim();
    setClientSubject(subject);
    const greeting = data.contact?.displayName ? `Bonjour ${data.contact.displayName},` : 'Bonjour,';
    setClientBody(
      [
        greeting,
        '',
        `Concernant votre ticket #${data.publicId} :`,
        '',
        'Pouvez-vous me préciser :',
        '- ',
        '',
        'Merci,',
      ].join('\n'),
    );
  }, [data]);

  const attachments = useMemo(() => (Array.isArray(data?.attachments) ? data.attachments : []), [data?.attachments]);
  const totalAttachmentBytes = useMemo(
    () => attachments.reduce((sum, a) => sum + (a?.sizeBytes || 0), 0),
    [attachments],
  );

  function attachmentPreviewKind(a) {
    const mime = (a?.mimeType || '').toLowerCase();
    const name = (a?.originalFilename || '').toLowerCase();
    if (mime.startsWith('image/')) return 'image';
    if (mime === 'application/pdf' || name.endsWith('.pdf')) return 'pdf';
    return 'none';
  }

  const viewerIndex = attachmentViewer?.index ?? null;
  const viewerAttachment = viewerIndex != null ? attachments[viewerIndex] : null;
  const viewerKind = viewerAttachment ? attachmentPreviewKind(viewerAttachment) : 'none';

  function openViewerAt(index) {
    const i = Number(index);
    if (!Number.isFinite(i) || i < 0 || i >= attachments.length) return;
    setAttachmentViewer({ index: i });
  }

  function closeViewer() {
    setAttachmentViewer(null);
  }

  function goPrev() {
    if (viewerIndex == null) return;
    openViewerAt(Math.max(0, viewerIndex - 1));
  }

  function goNext() {
    if (viewerIndex == null) return;
    openViewerAt(Math.min(attachments.length - 1, viewerIndex + 1));
  }

  useEffect(() => {
    if (!attachmentViewer) return undefined;
    function onKeyDown(e) {
      if (e.key === 'Escape') closeViewer();
      if (e.key === 'ArrowLeft') goPrev();
      if (e.key === 'ArrowRight') goNext();
    }
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [attachmentViewer, viewerIndex, attachments.length]);

  async function handleUploadFiles(fileList) {
    if (!idNum || uploadingAttachments) return;
    const files = Array.from(fileList || []).filter((f) => f instanceof File);
    if (files.length === 0) return;
    setAttachmentsError(null);
    setUploadingAttachments(true);
    try {
      const updated = await uploadTicketAttachments(idNum, files);
      setData(updated);
      pushToast('Pièce(s) jointe(s) ajoutée(s).');
    } catch (e) {
      setAttachmentsError(e?.message || 'Impossible d’ajouter les pièces jointes.');
    } finally {
      setUploadingAttachments(false);
    }
  }

  async function handleSendClientMessage() {
    if (!idNum) return;
    setClientSendError(null);
    setClientSendOk(false);
    setSendingClient(true);
    try {
      const updated = await sendClientMessage(idNum, {
        subject: clientSubject.trim(),
        body: clientBody.trim(),
      });
      setData(updated);
      setClientSendOk(true);
      setClientModalOpen(false);
      pushToast('Message envoyé au client.');
      window.setTimeout(() => setClientSendOk(false), 1400);
    } catch (err) {
      setClientSendError(err?.message || 'Envoi impossible.');
    } finally {
      setSendingClient(false);
    }
  }

  async function handleValidateClient() {
    if (!idNum) return;
    setSaveError(null);
    setSaving(true);
    try {
      const updated = await validateClientForTicket(idNum);
      setData(updated);
      pushToast('Client validé.');
    } catch (err) {
      setSaveError(err?.message || 'Validation impossible.');
    } finally {
      setSaving(false);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (idNum == null) return;
    setSaveError(null);
    setSaving(true);
    try {
      const updated = await patchTicket(idNum, {
        status,
        priority,
        type,
        assigneeUserId: assigneeUserId === '' ? null : Number(assigneeUserId),
        onHoldReason: onHoldReason.trim() === '' ? null : onHoldReason.trim(),
        cancelReason: cancelReason.trim() === '' ? null : cancelReason.trim(),
        ...(note.trim() !== '' ? { note: note.trim() } : {}),
      });
      setData(updated);
      setNote('');
      pushToast('Enregistré.');
    } catch (err) {
      setSaveError(err?.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  }

  async function applyQuickPatch(patch) {
    if (idNum == null) return;
    setSaveError(null);
    setSaving(true);
    try {
      const updated = await patchTicket(idNum, patch);
      setData(updated);
      setNote('');
      pushToast('Enregistré.');
    } catch (err) {
      setSaveError(err?.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  }

  const projectAccent = useMemo(() => {
    if (!data?.project) return null;
    const bg = normalizeHex(data.project.accentColor) || '#64748b';
    return {
      bg,
      fg: normalizeHex(data.project.accentTextColor) || contrastTextForBackground(bg),
      bd: normalizeHex(data.project.accentBorderColor) || darkenBorderHex(bg),
    };
  }, [data?.project]);

  if (idNum == null) {
    return <ErrorAlert message="Identifiant de ticket invalide." />;
  }

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return (
      <ErrorAlert
        message={error || 'Impossible de charger le ticket'}
        onRetry={idNum != null ? reload : undefined}
      />
    );
  }

  const assignable = Array.isArray(data.assignableMembers) ? data.assignableMembers : [];
  const createdLabel = formatDateTime(data.createdAt);
  const logCount = Array.isArray(data.logs) ? data.logs.length : 0;
  const sortedLogs =
    logCount > 0
      ? [...data.logs].sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)))
      : [];
  const selectedAssigneeId = assigneeUserId || '';

  const contactEmail = data?.contact?.email ? String(data.contact.email) : '';
  const meId = me?.id != null ? String(me.id) : null;
  const workflowActive =
    status === 'acknowledged' ||
    status === 'in_progress' ||
    status === 'on_hold' ||
    status === 'resolved' ||
    status === 'closed' ||
    status === 'cancelled'
      ? status
      : 'new';
  const contextMode = status === 'on_hold' ? 'on_hold' : status === 'cancelled' ? 'cancelled' : 'work_note';

  return (
    <div className="webhook-projects-page op-project-edit op-ticket-view">
      {toasts.length > 0 ? (
        <div className="aj-toast-stack" aria-live="polite" aria-relevant="additions">
          {toasts.map((t) => (
            <div key={t.id} className="aj-toast">
              <i className="fas fa-check-circle aj-toast__icon" aria-hidden="true" />
              <div className="aj-toast__msg">{t.message}</div>
            </div>
          ))}
        </div>
      ) : null}
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}

      <header className="op-project-edit__header">
        <div className="op-project-edit__header-row">
          <div>
            <h1 className="op-project-edit__title h4 m-0 d-flex align-items-start flex-wrap" style={{ gap: '0.5rem' }}>
              <i className="fas fa-ticket-alt op-project-edit__title-icon mt-1" aria-hidden="true" />
              <span className="flex-grow-1" style={{ minWidth: 0 }}>
                {data.title}
              </span>
            </h1>
            <p className="op-project-edit__meta small mb-0 mt-2">
              <span className="font-monospace text-muted">#{data.publicId}</span>
              {data.project ? (
                <>
                  {' '}
                  · Projet{' '}
                  {projectAccent ? (
                    <span
                      className="d-inline-block text-truncate align-middle font-weight-bold"
                      style={{
                        maxWidth: 220,
                        padding: '0.15rem 0.5rem',
                        borderRadius: 999,
                        backgroundColor: projectAccent.bg,
                        color: projectAccent.fg,
                        border: `1px solid ${projectAccent.bd}`,
                        fontSize: '0.75rem',
                        verticalAlign: 'middle',
                      }}
                      title={data.project.name}
                    >
                      {data.project.name}
                    </span>
                  ) : (
                    <span className="font-weight-bold">{data.project.name}</span>
                  )}
                </>
              ) : null}
              {' · '}
              <time dateTime={data.createdAt}>Créé le {createdLabel}</time>
            </p>
          </div>
          <div className="d-flex flex-wrap align-items-center" style={{ gap: '0.5rem' }}>
            {data.projectId != null ? (
              <Link to={`/projects/${data.projectId}`} className="btn btn-sm op-project-edit__btn-outline">
                <i className="fas fa-folder-open mr-1" aria-hidden="true" />
                Voir le projet
              </Link>
            ) : null}
            <Link to="/tickets" className="btn btn-sm op-project-edit__btn-back">
              <i className="fas fa-arrow-left mr-1" aria-hidden="true" />
              Tickets
            </Link>
          </div>
        </div>
      </header>

      <div className="te-ticket-header card shadow-sm border-0 mb-3">
        <div className="te-ticket-header__top">
          <div className="te-ticket-header__meta">
            <span className={`te-status-pill te-status-pill--${status || 'new'}`}>{status || 'new'}</span>
            <span className="te-source-pill" title="Source (lecture seule)">
              <i className="fas fa-inbox mr-1" aria-hidden="true" />
              {source || '—'}
            </span>
            {data?.sla?.current?.dueAt ? (
              <span className={`te-sla-pill ${data.sla.current.breached ? 'te-sla-pill--bad' : 'te-sla-pill--ok'}`}>
                <i className="fas fa-stopwatch mr-1" aria-hidden="true" />
                {data.sla.current.kind === 'ack' ? 'Prise en compte' : 'Résolution'} avant {formatSlaDue(data.sla.current.dueAt)}
              </span>
            ) : null}
          </div>

          <div className="te-ticket-header__workflow">
            <button
              type="button"
              className={`btn btn-sm ${workflowActive === 'acknowledged' || workflowActive === 'new' ? 'btn-primary' : 'btn-outline-secondary'}`}
              disabled={saving || !meId}
              onClick={() => {
                if (!meId) return;
                setAssigneeUserId(meId);
                setStatus('acknowledged');
              }}
              title={!meId ? 'Utilisateur courant indisponible' : 'Assigner à moi et passer en “Pris en compte”'}
            >
              <i className="fas fa-hand-paper mr-1" aria-hidden="true" />
              Prendre en compte
            </button>
            <button
              type="button"
              className={`btn btn-sm ${workflowActive === 'in_progress' ? 'btn-primary' : 'btn-outline-secondary'}`}
              disabled={saving}
              onClick={() => {
                if (assigneeUserId === '' && meId) setAssigneeUserId(meId);
                setStatus('in_progress');
              }}
            >
              <i className="fas fa-play mr-1" aria-hidden="true" />
              En cours
            </button>
            <button
              type="button"
              className={`btn btn-sm ${workflowActive === 'on_hold' ? 'btn-primary' : 'btn-outline-secondary'}`}
              disabled={saving}
              onClick={() => {
                setActionModal({
                  kind: 'on_hold',
                  title: 'Mise en attente',
                  placeholder: 'Pourquoi ce ticket est-il mis en attente ? (ex: attente retour client, attente accès, etc.)',
                  nextStatus: 'on_hold',
                  field: 'onHoldReason',
                });
                setActionReason(onHoldReason || '');
              }}
            >
              <i className="fas fa-pause mr-1" aria-hidden="true" />
              Mise en attente
            </button>
            <button
              type="button"
              className={`btn btn-sm ${workflowActive === 'resolved' ? 'btn-primary' : 'btn-outline-secondary'}`}
              disabled={saving}
              onClick={() => {
                setActionModal({
                  kind: 'resolved',
                  title: 'Résoudre — compte rendu d’intervention',
                  placeholder:
                    "Décris l’intervention (diagnostic, actions réalisées, résultat, éventuelles recommandations)…",
                  nextStatus: 'resolved',
                  field: 'note',
                  saveNow: true,
                });
                setActionReason('');
              }}
            >
              <i className="fas fa-check mr-1" aria-hidden="true" />
              Résoudre
            </button>
            <button
              type="button"
              className={`btn btn-sm ${workflowActive === 'closed' ? 'btn-primary' : 'btn-outline-secondary'}`}
              disabled={saving}
              onClick={() => setStatus('closed')}
            >
              <i className="fas fa-flag-checkered mr-1" aria-hidden="true" />
              Clôturer
            </button>
            <button
              type="button"
              className={`btn btn-sm ${workflowActive === 'cancelled' ? 'btn-danger' : 'btn-outline-danger'}`}
              disabled={saving}
              onClick={() => {
                setActionModal({
                  kind: 'cancelled',
                  title: 'Annuler',
                  placeholder: 'Motif d’annulation (ex: faux positif, doublon, hors périmètre, etc.)',
                  nextStatus: 'cancelled',
                  field: 'cancelReason',
                });
                setActionReason(cancelReason || '');
              }}
            >
              <i className="fas fa-ban mr-1" aria-hidden="true" />
              Annuler
            </button>
          </div>
        </div>

        <div className="te-ticket-header__bottom">
          <div className="te-ticket-header__controls" aria-label="Priorité et type">
            <div className="te-priority-picker" role="group" aria-label="Priorité">
              <button
                type="button"
                className={`te-priority-chip te-priority-chip--low ${priority === 'low' ? 'is-active' : ''}`}
                onClick={() => setPriority('low')}
              >
                Basse
              </button>
              <button
                type="button"
                className={`te-priority-chip te-priority-chip--medium ${priority === 'medium' ? 'is-active' : ''}`}
                onClick={() => setPriority('medium')}
              >
                Moyenne
              </button>
              <button
                type="button"
                className={`te-priority-chip te-priority-chip--high ${priority === 'high' ? 'is-active' : ''}`}
                onClick={() => setPriority('high')}
              >
                Haute
              </button>
              <button
                type="button"
                className={`te-priority-chip te-priority-chip--critical ${priority === 'critical' ? 'is-active' : ''}`}
                onClick={() => setPriority('critical')}
              >
                Critique
              </button>
            </div>

            <div className="te-ticket-header__divider" aria-hidden="true" />

            <div className="te-type-picker">
              <div className="te-type-chips" role="group" aria-label="Type">
                {TYPE_OPTIONS.map((o) => (
                  <button
                    key={o.value}
                    type="button"
                    className={`te-type-chip te-type-chip--${o.value} ${type === o.value ? 'is-active' : ''}`}
                    onClick={() => setType(o.value)}
                    aria-pressed={type === o.value}
                  >
                    <i className={`fas ${TYPE_ICON[o.value] || 'fa-tag'} mr-1`} aria-hidden="true" />
                    {o.label}
                  </button>
                ))}
              </div>
            </div>
          </div>

          <ul className="nav op-project-edit-tabs flex-wrap mb-0" role="tablist">
            <li className="nav-item">
              <button
                type="button"
                className={`nav-link ${activeTab === TE_TAB_MAIN ? 'active' : ''}`}
                onClick={() => setActiveTab(TE_TAB_MAIN)}
                role="tab"
                aria-selected={activeTab === TE_TAB_MAIN}
                id="te-tab-main"
              >
                <i className="fas fa-clipboard-list mr-1" aria-hidden="true" />
                Fiche
              </button>
            </li>
            <li className="nav-item">
              <button
                type="button"
                className={`nav-link ${activeTab === TE_TAB_HISTORY ? 'active' : ''}`}
                onClick={() => setActiveTab(TE_TAB_HISTORY)}
                role="tab"
                aria-selected={activeTab === TE_TAB_HISTORY}
                id="te-tab-history"
              >
                <i className="fas fa-history mr-1" aria-hidden="true" />
                Historique
                {logCount > 0 ? (
                  <span className="badge badge-secondary ml-1" style={{ fontSize: '0.7rem', verticalAlign: 'middle' }}>
                    {logCount}
                  </span>
                ) : null}
              </button>
            </li>
            <li className="nav-item">
              <button
                type="button"
                className={`nav-link ${activeTab === TE_TAB_TECH ? 'active' : ''}`}
                onClick={() => setActiveTab(TE_TAB_TECH)}
                role="tab"
                aria-selected={activeTab === TE_TAB_TECH}
                id="te-tab-tech"
              >
                <i className="fas fa-sliders-h mr-1" aria-hidden="true" />
                Technique
              </button>
            </li>
          </ul>
        </div>
      </div>

      {saveError ? (
        <div className="mb-3">
          <ErrorAlert message={saveError} />
        </div>
      ) : null}

      {actionModal ? (
        <div className="te-modal" role="dialog" aria-modal="true" aria-label={actionModal.title}>
          <div className="te-modal__backdrop" onClick={() => setActionModal(null)} />
          <div className="te-modal__panel">
            <h3 className="te-modal__title">{actionModal.title}</h3>
            <p className="te-modal__hint">
              {actionModal.kind === 'resolved'
                ? 'Un compte rendu est requis pour clôturer proprement l’intervention (traçabilité ITIL).'
                : 'Un motif est requis pour garder une traçabilité ITIL.'}
            </p>
            <textarea
              className="form-control"
              rows={3}
              value={actionReason}
              onChange={(e) => setActionReason(e.target.value)}
              placeholder={actionModal.placeholder}
              autoFocus
            />
            <div className="te-modal__actions">
              <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setActionModal(null)}>
                Annuler
              </button>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                onClick={async () => {
                  const r = actionReason.trim();
                  if (actionModal.kind === 'resolved') {
                    await applyQuickPatch({ status: 'resolved', ...(r !== '' ? { note: r } : {}) });
                    setActionModal(null);
                    setActionReason('');
                    return;
                  }

                  if (actionModal.field === 'onHoldReason') setOnHoldReason(r);
                  if (actionModal.field === 'cancelReason') setCancelReason(r);
                  setStatus(actionModal.nextStatus);

                  if (actionModal.saveNow) {
                    await applyQuickPatch({
                      status: actionModal.nextStatus,
                      ...(actionModal.field === 'onHoldReason' ? { onHoldReason: r } : {}),
                      ...(actionModal.field === 'cancelReason' ? { cancelReason: r } : {}),
                    });
                  }

                  setActionModal(null);
                  setActionReason('');
                }}
                disabled={actionReason.trim() === ''}
              >
                Valider
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {clientModalOpen ? (
        <div className="te-modal" role="dialog" aria-modal="true" aria-label="Demander une précision au client">
          <div className="te-modal__backdrop" onClick={() => setClientModalOpen(false)} />
          <div className="te-modal__panel">
            <h3 className="te-modal__title">Demander une précision au client</h3>
            <p className="te-modal__hint">
              Le message est envoyé par l’application et enregistré dans l’historique du ticket (traçabilité ITIL).
            </p>
            <div className="form-group">
              <label htmlFor="te-client-subject" className="mb-1">
                Sujet
              </label>
              <input
                id="te-client-subject"
                className="form-control"
                value={clientSubject}
                onChange={(e) => setClientSubject(e.target.value)}
                placeholder="Sujet"
              />
            </div>
            <div className="form-group mb-0">
              <label htmlFor="te-client-body" className="mb-1">
                Message
              </label>
              <textarea
                id="te-client-body"
                className="form-control"
                rows={6}
                value={clientBody}
                onChange={(e) => setClientBody(e.target.value)}
                placeholder="Votre message…"
              />
            </div>
            {clientSendError ? <p className="small text-danger mb-0 mt-2">{clientSendError}</p> : null}
            {clientSendOk ? <p className="small text-success mb-0 mt-2">Message envoyé et journalisé.</p> : null}
            <div className="te-modal__actions">
              <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setClientModalOpen(false)}>
                Fermer
              </button>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={sendingClient || clientSubject.trim() === '' || clientBody.trim() === ''}
                onClick={handleSendClientMessage}
              >
                <i className="fas fa-paper-plane mr-1" aria-hidden="true" />
                {sendingClient ? 'Envoi…' : 'Envoyer'}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {attachmentViewer && viewerAttachment ? (
        <div className="te-modal" role="dialog" aria-modal="true" aria-label="Pièce jointe">
          <div className="te-modal__backdrop" onClick={closeViewer} />
          <div className="te-modal__panel te-attach-modal__panel">
            <div className="te-attach-modal__header">
              <div className="te-attach-modal__title-wrap">
                <h3 className="te-modal__title te-attach-modal__title mb-0" title={viewerAttachment.originalFilename || ''}>
                  {viewerAttachment.originalFilename || 'Pièce jointe'}
                </h3>
                <p className="te-modal__hint te-attach-modal__hint mb-0">
                  {viewerIndex + 1} / {attachments.length}
                  {viewerAttachment.sizeBytes ? ` · ${Math.round(viewerAttachment.sizeBytes / 1024)} Ko` : ''}
                </p>
              </div>
              <button type="button" className="btn btn-sm btn-outline-secondary" onClick={closeViewer}>
                Fermer
              </button>
            </div>

            <div className="te-attach-modal__viewer" aria-label="Aperçu">
              {viewerKind === 'image' && viewerAttachment.downloadPath ? (
                <img className="te-attach-modal__img" src={viewerAttachment.downloadPath} alt={viewerAttachment.originalFilename || 'Pièce jointe'} />
              ) : viewerKind === 'pdf' && viewerAttachment.downloadPath ? (
                <iframe
                  className="te-attach-modal__frame"
                  src={`${viewerAttachment.downloadPath}#toolbar=0&navpanes=0&scrollbar=1`}
                  title={viewerAttachment.originalFilename || 'PDF'}
                />
              ) : (
                <div className="te-attach-modal__fallback">
                  <p className="mb-2">
                    Aperçu non disponible pour ce type de fichier.
                  </p>
                  {viewerAttachment.downloadPath ? (
                    <a className="btn btn-sm btn-primary" href={viewerAttachment.downloadPath} target="_blank" rel="noreferrer">
                      Ouvrir
                    </a>
                  ) : null}
                </div>
              )}
            </div>

            <div className="te-modal__actions te-attach-modal__actions">
              <button type="button" className="btn btn-sm btn-outline-secondary" onClick={goPrev} disabled={viewerIndex <= 0}>
                <i className="fas fa-arrow-left mr-1" aria-hidden="true" />
                Précédent
              </button>
              <button type="button" className="btn btn-sm btn-outline-secondary" onClick={goNext} disabled={viewerIndex >= attachments.length - 1}>
                Suivant
                <i className="fas fa-arrow-right ml-1" aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {activeTab === TE_TAB_MAIN ? (
      <form onSubmit={handleSubmit}>
        <div className="row">
          <div className="col-lg-3 mb-3">
            <PageCard className="op-projects-card content-card op-project-edit-card h-100">
              <div className="op-projects-card-body op-project-edit-card-body">
                <h2 className="op-project-edit__pane-title h6">Identité</h2>
                {data.contact || data.incomingEmailMessageId ? (
                  <div className="mb-3">
                    {data.contact ? (
                      <div className="te-contact">
                        <p className="small mb-2">
                          {data.contact.displayName ? (
                            <>
                              <strong>{data.contact.displayName}</strong>
                              <br />
                            </>
                          ) : null}
                          <span className="text-muted">{data.contact.email}</span>
                        </p>
                        {data.clientPortalAccess ? (
                          <p className="small mb-2 text-muted">Accès portail autorisé.</p>
                        ) : data.contact.validatedAt ? (
                          <div className="mb-2">
                            <p className="small mb-2 text-muted">
                              Client validé le {formatDateTime(data.contact.validatedAt)}.
                            </p>
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-primary"
                              disabled={saving}
                              onClick={handleValidateClient}
                              title="Ré-exécute la validation (idempotent) pour s’assurer que l’accès portail est bien autorisé."
                            >
                              Autoriser l’accès portail
                            </button>
                          </div>
                        ) : (
                          <div className="mb-2">
                            <button type="button" className="btn btn-sm btn-outline-success" disabled={saving} onClick={handleValidateClient}>
                              <i className="fas fa-badge-check mr-1" aria-hidden="true" />
                              Valider ce client
                            </button>
                          </div>
                        )}
                      <div className="te-contact__actions">
                        <button
                          type="button"
                          className="btn btn-sm btn-primary"
                          disabled={!contactEmail}
                          onClick={() => setClientModalOpen(true)}
                        >
                          <i className="fas fa-paper-plane mr-1" aria-hidden="true" />
                          Demander une précision
                        </button>
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-secondary"
                          disabled={!contactEmail || !navigator?.clipboard}
                          onClick={async () => {
                            if (!contactEmail || !navigator?.clipboard) return;
                            try {
                              await navigator.clipboard.writeText(contactEmail);
                              setCopiedEmail(true);
                              window.setTimeout(() => setCopiedEmail(false), 1200);
                            } catch {
                              setCopiedEmail(false);
                            }
                          }}
                        >
                          <i className="far fa-copy mr-1" aria-hidden="true" />
                          {copiedEmail ? 'Copié' : 'Copier e-mail'}
                        </button>
                      </div>
                      </div>
                    ) : (
                      <p className="op-project-edit__hint small mb-2">Aucun contact enregistré pour cet envoi.</p>
                    )}
                  </div>
                ) : (
                  <p className="op-project-edit__hint small mb-3">
                    Pas de données d’e-mail liées (ticket créé hors import IMAP ou sans expéditeur valide).
                  </p>
                )}

                <hr className="my-3" />

                <h2 className="op-project-edit__pane-title h6">Pièces jointes</h2>
                <div
                  className={`tc-dropzone${dragActive ? ' is-dragging' : ''}`}
                  role="button"
                  tabIndex={0}
                  onClick={() => fileInputRef.current?.click()}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.preventDefault();
                      fileInputRef.current?.click();
                    }
                  }}
                  onDragEnter={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (uploadingAttachments) return;
                    setDragActive(true);
                  }}
                  onDragOver={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (uploadingAttachments) return;
                    setDragActive(true);
                  }}
                  onDragLeave={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setDragActive(false);
                  }}
                  onDrop={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setDragActive(false);
                    if (uploadingAttachments) return;
                    handleUploadFiles(e.dataTransfer?.files);
                  }}
                  aria-label="Déposer des fichiers"
                >
                  <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    className="tc-dropzone__input"
                    onChange={(e) => {
                      handleUploadFiles(e.target.files);
                      e.target.value = '';
                    }}
                    disabled={uploadingAttachments}
                  />
                  <div className="tc-dropzone__inner">
                    <i className="fas fa-paperclip tc-dropzone__icon" aria-hidden="true" />
                    <div className="tc-dropzone__text">
                      <div className="tc-dropzone__title">
                        {uploadingAttachments ? 'Ajout en cours…' : 'Glisse-dépose tes fichiers ici'}
                      </div>
                      <div className="tc-dropzone__hint text-muted">ou clique pour sélectionner (max 10 · 15 Mo/fichier)</div>
                    </div>
                    <div className="tc-dropzone__meta text-muted small">
                      {attachments.length > 0 ? (
                        <span>
                          {attachments.length} fichier{attachments.length > 1 ? 's' : ''} ·{' '}
                          {Math.round(totalAttachmentBytes / 1024)} Ko
                        </span>
                      ) : (
                        <span>Aucune pièce jointe</span>
                      )}
                    </div>
                  </div>
                </div>

                {attachmentsError ? <p className="small text-danger mt-2 mb-0">{attachmentsError}</p> : null}

                {attachments.length > 0 ? (
                  <ul className="tc-attachments list-unstyled mb-0 mt-2">
                    {attachments.map((a, idx) => (
                      <li key={a.id ?? a.downloadPath ?? a.originalFilename ?? idx} className="tc-attachment">
                        <span className="tc-attachment__name" title={a.originalFilename || ''}>
                          {a.originalFilename || 'Fichier'}
                        </span>
                        <span className="tc-attachment__size text-muted small">
                          {Math.round((a.sizeBytes || 0) / 1024)} Ko
                        </span>
                        <span className="tc-attachment__actions">
                          {a.downloadPath ? (
                            <>
                              <button
                                type="button"
                                className="btn btn-sm btn-link tc-attachment__remove"
                                onClick={() => openViewerAt(idx)}
                              >
                                Consulter
                              </button>
                              <a className="btn btn-sm btn-link tc-attachment__remove" href={a.downloadPath}>
                                Télécharger
                              </a>
                            </>
                          ) : (
                            <span className="text-muted small">—</span>
                          )}
                        </span>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </div>
            </PageCard>
          </div>

          <div className="col-lg-6 mb-3">
            <PageCard className="op-projects-card content-card op-project-edit-card h-100">
              <div className="op-projects-card-body op-project-edit-card-body">
                <h2 className="op-project-edit__pane-title h6">Description</h2>
                {data.description ? (
                  <div className="op-ticket-view__desc small mb-0">{data.description}</div>
                ) : (
                  <p className="op-project-edit__hint small mb-0">Aucune description.</p>
                )}

                <hr className="my-4" />

                {contextMode === 'on_hold' ? (
                  <div className="form-group mb-0">
                    <label htmlFor="te-onhold">Motif de mise en attente</label>
                    <textarea
                      id="te-onhold"
                      className="form-control"
                      rows={2}
                      value={onHoldReason}
                      onChange={(ev) => setOnHoldReason(ev.target.value)}
                      placeholder="Ex: attente retour client, attente accès, attente fournisseur…"
                    />
                  </div>
                ) : null}
                {contextMode === 'cancelled' ? (
                  <div className="form-group mb-0">
                    <label htmlFor="te-cancel">Motif d’annulation</label>
                    <textarea
                      id="te-cancel"
                      className="form-control"
                      rows={2}
                      value={cancelReason}
                      onChange={(ev) => setCancelReason(ev.target.value)}
                      placeholder="Ex: doublon, hors périmètre, faux positif…"
                    />
                  </div>
                ) : null}
                {contextMode === 'work_note' ? (
                  <div className="form-group mb-0">
                    <label htmlFor="te-note">Traitement effectué</label>
                    <textarea
                      id="te-note"
                      className="form-control"
                      rows={3}
                      value={note}
                      onChange={(ev) => setNote(ev.target.value)}
                      placeholder="Décris l’action réalisée (diagnostic, correction, relance, contournement…). Ajouté à l’historique si renseigné."
                    />
                  </div>
                ) : null}
              </div>
            </PageCard>
          </div>

          <div className="col-lg-3 mb-3">
            <PageCard className="op-projects-card content-card op-project-edit-card h-100">
              <div className="op-projects-card-body op-project-edit-card-body">
                <h2 className="op-project-edit__pane-title h6">Affectation</h2>
                <div className="te-assignee-grid" role="list" aria-label="Responsable du traitement">
                  <div role="listitem">
                    <AssigneeCard
                      member={null}
                      selected={selectedAssigneeId === ''}
                      onSelect={() => setAssigneeUserId('')}
                    />
                  </div>
                  {assignable.map((m) => (
                    <div key={m.id} role="listitem">
                      <AssigneeCard
                        member={m}
                        selected={selectedAssigneeId === String(m.id)}
                        onSelect={() => setAssigneeUserId(String(m.id))}
                      />
                    </div>
                  ))}
                </div>
                {assignable.length === 0 ? (
                  <p className="op-project-edit__hint small mb-3">
                    Aucun gestionnaire sur ce projet : ajoutez-en dans l’édition du projet (Membres affectés aux
                    tickets).
                  </p>
                ) : null}
                {data.assignee && assignable.every((m) => m.id !== data.assignee.id) ? (
                  <p className="small text-warning mb-3">
                    L’assigné actuel n’est plus dans la liste des gestionnaires ; choisissez un membre ou retirez
                    l’affectation.
                  </p>
                ) : null}

                <hr className="my-3" />

                <dl className="mb-0 small op-ticket-view__meta-dl">
                  {data.acknowledgedAt ? (
                    <>
                      <dt>Pris en compte</dt>
                      <dd className="mb-2">{formatDateTime(data.acknowledgedAt)}</dd>
                    </>
                  ) : null}
                  {data.resolvedAt ? (
                    <>
                      <dt>Résolu</dt>
                      <dd className="mb-2">{formatDateTime(data.resolvedAt)}</dd>
                    </>
                  ) : null}
                </dl>

                <button type="submit" className="btn btn-primary btn-block mt-2" disabled={saving}>
                  {saving ? 'Enregistrement…' : 'Enregistrer les modifications'}
                </button>
              </div>
            </PageCard>
          </div>
        </div>
      </form>
      ) : activeTab === TE_TAB_HISTORY ? (
        <PageCard className="op-projects-card content-card op-project-edit-card mb-3">
          <div className="op-projects-card-body op-project-edit-card-body">
            <h2 className="op-project-edit__pane-title h6">Historique</h2>
            {sortedLogs.length > 0 ? (
              <ul className="list-unstyled mb-0 op-ticket-view__log-list">
                {sortedLogs.map((log) => (
                  <li key={log.id} className="op-ticket-view__log-item small">
                    <div className="d-flex flex-wrap align-items-baseline" style={{ gap: '0.35rem' }}>
                      <span className="text-muted">{formatDateTime(log.createdAt)}</span>
                      <span className="badge badge-light border">{logTypeLabel(log.type)}</span>
                      {log?.context?.actor?.name || log?.context?.actorName ? (
                        <span className="text-muted">
                          · {log?.context?.actor?.name || log?.context?.actorName}
                        </span>
                      ) : null}
                    </div>
                    <div className="mt-1">{log.message}</div>
                    {log?.type === 'client_message' ? (
                      <div className="mt-2">
                        <div className="text-muted small">
                          {log?.context?.to ? (
                            <span className="d-inline-block mr-2">
                              <strong>À</strong> {String(log.context.to)}
                            </span>
                          ) : null}
                          {log?.context?.subject ? (
                            <span className="d-inline-block">
                              <strong>Sujet</strong> {String(log.context.subject)}
                            </span>
                          ) : null}
                        </div>
                        {log?.context?.bodyExcerpt ? (
                          <details className="mt-1">
                            <summary className="small">Voir le message</summary>
                            <div className="op-ticket-view__desc small mb-0 mt-2">
                              {String(log.context.bodyExcerpt)}
                            </div>
                          </details>
                        ) : null}
                      </div>
                    ) : null}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="op-project-edit__hint small mb-0">Aucun événement enregistré pour l’instant.</p>
            )}
          </div>
        </PageCard>
      ) : (
        <PageCard className="op-projects-card content-card op-project-edit-card mb-3">
          <div className="op-projects-card-body op-project-edit-card-body">
            <h2 className="op-project-edit__pane-title h6">Technique</h2>
            <div className="mb-3">
              <div className="small text-muted mb-1">Événements</div>
              <div className="font-weight-bold">{data.eventCount ?? 0}</div>
            </div>
            {data.incomingEmailMessageId ? (
              <div className="mb-2">
                <div className="small text-muted mb-1">Message-ID (référence e-mail)</div>
                <code className="small d-block text-break">{data.incomingEmailMessageId}</code>
              </div>
            ) : (
              <p className="op-project-edit__hint small mb-0">Aucune métadonnée technique disponible.</p>
            )}
          </div>
        </PageCard>
      )}
    </div>
  );
}
