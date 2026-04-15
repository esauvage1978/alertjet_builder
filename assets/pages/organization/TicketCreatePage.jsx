import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Basse' },
  { value: 'medium', label: 'Moyenne' },
  { value: 'high', label: 'Haute' },
  { value: 'critical', label: 'Critique' },
];

const TYPE_OPTIONS = [
  { value: 'incident', label: 'Incident', icon: 'fa-exclamation-triangle' },
  { value: 'problem', label: 'Problème', icon: 'fa-bug' },
  { value: 'request', label: 'Demande', icon: 'fa-handshake' },
];

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

async function createInternalTicket(body) {
  const res = await fetch('/mon-organisation/tickets/nouveau', {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json' },
    body,
  });
  const ct = res.headers.get('Content-Type') || '';
  if (ct.includes('application/json')) {
    const payload = await res.json();
    if (!res.ok) {
      throw new Error(payload?.message || payload?.error || `HTTP ${res.status}`);
    }
    return payload;
  }
  const text = await res.text();
  if (!res.ok) {
    throw new Error(text || `HTTP ${res.status}`);
  }
  return { ok: false, message: 'Réponse inattendue du serveur.' };
}

export default function TicketCreatePage() {
  const loadFn = useCallback(async () => fetchTicketNewPayload(), []);
  const { data, error, loading, reload } = useAsyncResource(loadFn);
  const [projectToken, setProjectToken] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [priority, setPriority] = useState('medium');
  const [type, setType] = useState('incident');
  const [attachments, setAttachments] = useState([]);
  const [dragActive, setDragActive] = useState(false);
  const [busy, setBusy] = useState(false);
  const [saveError, setSaveError] = useState('');
  const fileInputRef = useRef(null);

  const totalAttachmentBytes = useMemo(() => attachments.reduce((sum, f) => sum + (f?.size || 0), 0), [attachments]);

  function addFiles(fileList) {
    const list = Array.from(fileList || []).filter((f) => f instanceof File);
    if (list.length === 0) return;

    setAttachments((prev) => {
      const next = [...prev];
      const existing = new Set(prev.map((f) => `${f.name}|${f.size}|${f.lastModified}`));
      for (const f of list) {
        const key = `${f.name}|${f.size}|${f.lastModified}`;
        if (existing.has(key)) continue;
        next.push(f);
      }
      return next.slice(0, 10);
    });
  }

  function removeAttachmentAt(idx) {
    setAttachments((prev) => prev.filter((_, i) => i !== idx));
  }

  const projects = useMemo(() => (Array.isArray(data?.projects) ? data.projects : []), [data?.projects]);
  const firstProjectToken = useMemo(
    () => (projects[0]?.publicToken ? String(projects[0].publicToken) : ''),
    [projects],
  );

  useEffect(() => {
    if (!projectToken && firstProjectToken) {
      setProjectToken(firstProjectToken);
    }
  }, [firstProjectToken, projectToken]);

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger la page'} onRetry={reload} />;
  }

  async function onSubmit(ev) {
    ev.preventDefault();
    if (!data.formCsrf) return;
    if (!projectToken || !title.trim() || !description.trim() || !type) {
      setSaveError('Projet, titre, description et type sont obligatoires.');
      return;
    }
    setSaveError('');
    setBusy(true);
    try {
      const fd = new FormData();
      fd.set('_token', data.formCsrf);
      fd.set('projectToken', projectToken);
      fd.set('title', title.trim());
      fd.set('description', description.trim());
      fd.set('priority', priority);
      fd.set('type', type);
      attachments.forEach((f) => fd.append('attachments[]', f, f.name));

      const payload = await createInternalTicket(fd);
      if (payload?.ok && payload.ticketId) {
        window.location.href = `/app/tickets/${payload.ticketId}`;
        return;
      }
      throw new Error(payload?.message || 'Création impossible.');
    } catch (e) {
      setSaveError(e?.message || 'Création impossible.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="webhook-projects-page op-project-edit">
      <header className="op-project-edit__header mb-3">
        <div className="op-project-edit__header-row">
          <div>
            <h1 className="op-project-edit__title h4 m-0 d-flex align-items-center">
              <i className="fas fa-plus-circle op-project-edit__title-icon mr-2" aria-hidden="true" />
              Nouveau ticket
            </h1>
          </div>
          <Link to="/tickets" className="btn btn-sm op-project-edit__btn-back">
            <i className="fas fa-arrow-left mr-1" aria-hidden="true" />
            Tickets
          </Link>
        </div>
      </header>

      {saveError ? (
        <div className="mb-3">
          <ErrorAlert message={saveError} onRetry={() => setSaveError('')} />
        </div>
      ) : null}

      <PageCard className="op-projects-card content-card op-project-edit-card ou-members-card">
        <div className="op-project-edit-card-body">
          {projects.length === 0 ? (
            <p className="mb-0 small">
              Aucun projet n’a le formulaire interne activé. Active-le dans les paramètres du projet, puis reviens ici.
            </p>
          ) : (
            <form onSubmit={onSubmit}>
            <div className="form-row">
              <div className="form-group col-md-6">
                <label htmlFor="tc-project">Projet</label>
                <select
                  id="tc-project"
                  className="form-control"
                  value={projectToken}
                  onChange={(e) => setProjectToken(e.target.value)}
                  disabled={busy}
                >
                  {projects.map((p) => (
                    <option key={p.publicToken} value={p.publicToken}>
                      {p.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="form-group col-md-6">
                <label htmlFor="tc-title">Titre</label>
                <input
                  id="tc-title"
                  className="form-control"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Résumé du besoin / incident…"
                  disabled={busy}
                  required
                />
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="tc-desc">Description</label>
              <textarea
                id="tc-desc"
                className="form-control"
                rows={6}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Contexte, symptômes, étapes, urgence, etc."
                disabled={busy}
                required
              />
            </div>

            <div className="form-group">
              <label>Pièces jointes</label>
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
                  if (busy) return;
                  setDragActive(true);
                }}
                onDragOver={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  if (busy) return;
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
                  if (busy) return;
                  addFiles(e.dataTransfer?.files);
                }}
                aria-label="Déposer des fichiers"
              >
                <input
                  ref={fileInputRef}
                  type="file"
                  multiple
                  className="tc-dropzone__input"
                  onChange={(e) => {
                    addFiles(e.target.files);
                    e.target.value = '';
                  }}
                  disabled={busy}
                />
                <div className="tc-dropzone__inner">
                  <i className="fas fa-paperclip tc-dropzone__icon" aria-hidden="true" />
                  <div className="tc-dropzone__text">
                    <div className="tc-dropzone__title">Glisse-dépose tes fichiers ici</div>
                    <div className="tc-dropzone__hint text-muted">ou clique pour sélectionner (jusqu’à 10)</div>
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

              {attachments.length > 0 ? (
                <ul className="tc-attachments list-unstyled mb-0 mt-2">
                  {attachments.map((f, idx) => (
                    <li key={`${f.name}|${f.size}|${f.lastModified}`} className="tc-attachment">
                      <span className="tc-attachment__name" title={f.name}>
                        {f.name}
                      </span>
                      <span className="tc-attachment__size text-muted small">{Math.round((f.size || 0) / 1024)} Ko</span>
                      <button
                        type="button"
                        className="btn btn-sm btn-link tc-attachment__remove"
                        onClick={() => removeAttachmentAt(idx)}
                        disabled={busy}
                        aria-label={`Retirer ${f.name}`}
                      >
                        Retirer
                      </button>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>

            <div className="tc-pickers-row d-flex flex-wrap align-items-center">
              <div className="te-priority-picker" role="group" aria-label="Priorité">
                {PRIORITY_OPTIONS.map((o) => (
                  <button
                    key={o.value}
                    type="button"
                    className={`te-priority-chip te-priority-chip--${o.value} ${priority === o.value ? 'is-active' : ''}`}
                    onClick={() => setPriority(o.value)}
                    disabled={busy}
                  >
                    {o.label}
                  </button>
                ))}
              </div>

              <div className="te-ticket-header__divider" aria-hidden="true" />

              <div className="te-type-chips" role="group" aria-label="Type">
                {TYPE_OPTIONS.map((o) => (
                  <button
                    key={o.value}
                    type="button"
                    className={`te-type-chip te-type-chip--${o.value} ${type === o.value ? 'is-active' : ''}`}
                    onClick={() => setType(o.value)}
                    disabled={busy}
                  >
                    <i className={`fas ${o.icon} mr-1`} aria-hidden="true" />
                    {o.label}
                  </button>
                ))}
              </div>
            </div>

            <div className="tc-actions-row d-flex flex-wrap align-items-center">
              <button
                type="submit"
                className="btn btn-primary"
                disabled={busy || !projectToken || !title.trim() || !description.trim() || !type}
              >
                <i className="fas fa-check mr-1" aria-hidden="true" />
                {busy ? 'Création…' : 'Créer le ticket'}
              </button>
              <Link to="/tickets" className="btn btn-outline-secondary">
                Annuler
              </Link>
            </div>
            </form>
          )}
        </div>
      </PageCard>
    </div>
  );
}
