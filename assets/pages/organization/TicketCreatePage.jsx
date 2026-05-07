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
  const [step, setStep] = useState(1); // 1 = type, 2 = details
  const [projectToken, setProjectToken] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [priority, setPriority] = useState('medium');
  const [type, setType] = useState('incident');
  const [attachments, setAttachments] = useState([]);
  const [dragActive, setDragActive] = useState(false);
  const [busy, setBusy] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [submittedOnce, setSubmittedOnce] = useState(false);
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

  const projectByToken = useMemo(() => {
    const by = new Map(projects.map((p) => [String(p.publicToken), p]));
    return (tok) => by.get(String(tok)) || null;
  }, [projects]);
  const selectedProject = useMemo(() => (projectToken ? projectByToken(projectToken) : null), [projectByToken, projectToken]);

  const typeMeta = useMemo(() => {
    const defs = {
      incident: {
        title: 'Incident',
        icon: 'fa-exclamation-triangle',
        blurb: 'Une interruption ou dégradation non planifiée d’un service.',
        examples: 'Ex: accès KO, erreur 500, lenteurs, email non reçu…',
      },
      problem: {
        title: 'Problème',
        icon: 'fa-bug',
        blurb: 'La cause sous-jacente d’un ou plusieurs incidents (analyse).',
        examples: 'Ex: incident récurrent, investigation, RCA…',
      },
      request: {
        title: 'Demande',
        icon: 'fa-handshake',
        blurb: 'Une demande standard (information, accès, amélioration).',
        examples: 'Ex: créer un accès, paramétrage, évolution…',
      },
    };
    return defs;
  }, []);

  const missing = useMemo(() => {
    return {
      project: !projectToken,
      title: title.trim() === '',
      description: description.trim() === '',
      type: !type,
    };
  }, [projectToken, title, description, type]);

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
    setSubmittedOnce(true);
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
            <p className="mb-0 text-muted small tc-required-hint">
              Les champs marqués <span className="tc-required-star" aria-hidden="true">*</span> sont obligatoires.
            </p>
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
            <>
              {step === 1 ? (
                <div className="tc-wizard">
                  <div className="tc-wizard__stepTitle">
                    <div className="tc-wizard__kicker">Étape 1/2</div>
                    <h2 className="h5 mb-1">Quel type de ticket veux-tu créer ?</h2>
                    <p className="mb-0 text-muted small">Choisis la catégorie la plus proche, tu pourras affiner ensuite.</p>
                  </div>
                  <div className="tc-type-cards" role="list">
                    {TYPE_OPTIONS.map((o) => {
                      const meta = typeMeta[o.value];
                      const active = type === o.value;
                      return (
                        <button
                          key={o.value}
                          type="button"
                          className={`tc-type-card ${active ? 'is-active' : ''}`}
                          onClick={() => {
                            setType(o.value);
                            setStep(2);
                          }}
                          disabled={busy}
                          role="listitem"
                          aria-pressed={active}
                        >
                          <div className="tc-type-card__top">
                            <span className={`tc-type-card__icon tc-type-card__icon--${o.value}`} aria-hidden="true">
                              <i className={`fas ${meta.icon}`} />
                            </span>
                            <div className="tc-type-card__title">
                              <span className="tc-type-card__name">{meta.title}</span>
                              <span className="tc-type-card__pill">Recommandé</span>
                            </div>
                          </div>
                          <div className="tc-type-card__blurb">{meta.blurb}</div>
                          <div className="tc-type-card__examples">{meta.examples}</div>
                        </button>
                      );
                    })}
                  </div>
                  <div className="tc-wizard__footer">
                    <Link to="/tickets" className="btn btn-outline-secondary">
                      Annuler
                    </Link>
                  </div>
                </div>
              ) : (
                <form onSubmit={onSubmit}>
                  <div className="tc-wizard__bar">
                    <div className="tc-wizard__progress" aria-hidden="true">
                      <div className="tc-wizard__progressFill" style={{ width: '100%' }} />
                    </div>
                    <button
                      type="button"
                      className="btn btn-sm btn-link tc-wizard__back"
                      onClick={() => setStep(1)}
                      disabled={busy}
                      title="Revenir au choix du type"
                    >
                      <i className="fas fa-arrow-left mr-1" aria-hidden="true" />
                      Changer le type
                    </button>
                    <div className="tc-wizard__currentType">
                      <span className={`tc-type-badge tc-type-badge--${type}`}>
                        <i className={`fas ${typeMeta[type]?.icon || 'fa-ticket-alt'} mr-1`} aria-hidden="true" />
                        {typeMeta[type]?.title || 'Ticket'}
                      </span>
                    </div>
                  </div>

                  <div className="form-row">
                    <div className="form-group col-md-6">
                      <label htmlFor="tc-project">
                        Projet <span className="tc-required-star" aria-hidden="true">*</span>
                      </label>
                      {projects.length <= 1 ? (
                        <div className="te-readonly-field" aria-label="Projet">
                          {selectedProject?.name || '—'}
                        </div>
                      ) : (
                        <select
                          id="tc-project"
                          className={`form-control ${submittedOnce && missing.project ? 'is-invalid' : ''}`}
                          value={projectToken}
                          onChange={(e) => setProjectToken(e.target.value)}
                          disabled={busy}
                          required
                        >
                          {projects.map((p) => (
                            <option key={p.publicToken} value={p.publicToken}>
                              {p.name}
                            </option>
                          ))}
                        </select>
                      )}
                      {submittedOnce && missing.project ? <div className="invalid-feedback">Choisis un projet.</div> : null}
                    </div>
                    <div className="form-group col-md-6">
                      <label htmlFor="tc-title">
                        Titre <span className="tc-required-star" aria-hidden="true">*</span>
                      </label>
                      <input
                        id="tc-title"
                        className={`form-control ${submittedOnce && missing.title ? 'is-invalid' : ''}`}
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="Résumé clair en une phrase"
                        disabled={busy}
                        required
                        aria-required="true"
                      />
                      {submittedOnce && missing.title ? <div className="invalid-feedback">Le titre est obligatoire.</div> : null}
                    </div>
                  </div>

                  <div className="form-group">
                    <label htmlFor="tc-desc">
                      Description <span className="tc-required-star" aria-hidden="true">*</span>
                    </label>
                    <textarea
                      id="tc-desc"
                      className={`form-control ${submittedOnce && missing.description ? 'is-invalid' : ''}`}
                      rows={6}
                      value={description}
                      onChange={(e) => setDescription(e.target.value)}
                      placeholder="Contexte, symptômes, étapes pour reproduire, impact, urgence…"
                      disabled={busy}
                      required
                      aria-required="true"
                    />
                    {submittedOnce && missing.description ? (
                      <div className="invalid-feedback">La description est obligatoire.</div>
                    ) : null}
                    <div className="text-muted small mt-1 tc-desc-help">
                      Astuce: commence par l’<strong>impact</strong> (quoi / qui), puis les <strong>étapes</strong> et enfin le <strong>résultat attendu</strong>.
                    </div>
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
                          <div className="tc-dropzone__title">Ajoute des captures / logs</div>
                          <div className="tc-dropzone__hint text-muted">glisse-dépose ou clique (jusqu’à 10 fichiers)</div>
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
                            <span className="tc-attachment__size text-muted small">
                              {Math.round((f.size || 0) / 1024)} Ko
                            </span>
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
                      <span className="te-type-picker__label mr-2">Priorité</span>
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
            </>
          )}
        </div>
      </PageCard>
    </div>
  );
}
