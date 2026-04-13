import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, useParams } from 'react-router-dom';
import { fetchJson, postForm, urlWithCurrentSearch } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

export default function ProjectsPage() {
  const { orgToken: orgTokenParam } = useParams();
  const { data: boot } = useBootstrap();
  const orgToken = orgTokenParam ?? boot.currentOrganization?.publicToken;
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
  const [createError, setCreateError] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [projectToDelete, setProjectToDelete] = useState(null);
  const [deleteBusy, setDeleteBusy] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [headerSlot, setHeaderSlot] = useState(null);
  const nameInputRef = useRef(null);

  useLayoutEffect(() => {
    setHeaderSlot(document.getElementById('spa-projects-content-header'));
    return () => setHeaderSlot(null);
  }, []);

  const loadFn = useCallback(async () => {
    if (!orgToken) {
      throw new Error('Aucune organisation courante');
    }
    return fetchJson(urlWithCurrentSearch(`/organisation/${orgToken}/projets`));
  }, [orgToken]);
  const { data, error, loading, reload, setError } = useAsyncResource(loadFn);

  useEffect(() => {
    const anyModal = modalOpen || projectToDelete != null;
    if (!anyModal) return undefined;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const t = window.setTimeout(() => {
      if (modalOpen) nameInputRef.current?.focus();
    }, 80);
    function onKey(ev) {
      if (ev.key !== 'Escape' || busy || deleteBusy) return;
      if (modalOpen) {
        setModalOpen(false);
        setName('');
        setCreateError('');
      } else if (projectToDelete != null) {
        setProjectToDelete(null);
        setDeleteError('');
      }
    }
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prev;
      window.clearTimeout(t);
      document.removeEventListener('keydown', onKey);
    };
  }, [modalOpen, projectToDelete, busy, deleteBusy]);

  async function createProject(ev) {
    ev.preventDefault();
    if (!orgToken || !data?.newProjectCsrf || !name.trim()) return;
    setBusy(true);
    setCreateError('');
    try {
      const res = await postForm(
        `/organisation/${orgToken}/projets/nouveau`,
        {
          name: name.trim(),
          _token: data.newProjectCsrf,
        },
        { json: true },
      );
      const ct = res.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        const payload = await res.json();
        if (res.ok && payload.ok === true) {
          setName('');
          setModalOpen(false);
          setCreateError('');
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
      const text = await res.text();
      if (!res.ok) {
        throw new Error(text.trim().slice(0, 280) || `HTTP ${res.status}`);
      }
      throw new Error('Réponse inattendue du serveur.');
    } catch (e) {
      setCreateError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  function closeModal() {
    if (busy) return;
    setModalOpen(false);
    setName('');
    setCreateError('');
  }

  function closeDeleteModal() {
    if (deleteBusy) return;
    setProjectToDelete(null);
    setDeleteError('');
  }

  async function confirmDeleteProject(ev) {
    ev.preventDefault();
    if (!orgToken || !data?.deleteProjectCsrf || !projectToDelete?.publicToken) return;
    setDeleteBusy(true);
    setDeleteError('');
    try {
      const res = await postForm(
        `/organisation/${orgToken}/projets/${projectToDelete.publicToken}/supprimer`,
        { _token: data.deleteProjectCsrf },
        { json: true },
      );
      const ct = res.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        const payload = await res.json();
        if (res.ok && payload.ok === true) {
          setProjectToDelete(null);
          setDeleteError('');
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
      const text = await res.text();
      if (!res.ok) {
        throw new Error(text.trim().slice(0, 280) || `HTTP ${res.status}`);
      }
      throw new Error('Réponse inattendue du serveur.');
    } catch (e) {
      setDeleteError(e.message || 'Erreur');
    } finally {
      setDeleteBusy(false);
    }
  }

  const countLabel =
    loading && !data ? '…' : data != null ? (data.total ?? data.projects?.length ?? 0) : '—';

  const projectsHeaderPortal =
    orgToken && headerSlot
      ? createPortal(
          <div className="webhook-projects-page wp-projects-hero d-flex align-items-center justify-content-between flex-wrap w-100 op-projects-content-header-inner">
            <div className="integrations-hub-intro">
              <div className="d-flex align-items-center flex-wrap op-projects-page__heading">
                <h1 className="m-0 content-header__title op-projects-page__title wp-proj-page-title">
                  <i className="fas fa-folder-open" aria-hidden="true" />
                  Projets
                </h1>
                <span className="op-projects-page__count text-muted" aria-live="polite">
                  {countLabel}
                </span>
              </div>
            </div>
            <div className="wp-projects-hero-actions">
              <button
                type="button"
                className="btn btn-sm btn-primary op-new-btn wp-proj-hero-btn"
                onClick={() => {
                  setCreateError('');
                  setModalOpen(true);
                }}
              >
                <i className="fas fa-plus mr-1" aria-hidden="true" />
                Nouveau projet
              </button>
            </div>
          </div>,
          headerSlot,
        )
      : null;

  const modal =
    modalOpen &&
    typeof document !== 'undefined' &&
    createPortal(
      <>
        <div className="modal-backdrop fade show" role="presentation" style={{ zIndex: 1040 }} />
        <div
          className="modal fade show d-block op-new-modal webhook-projects-modal"
          tabIndex={-1}
          role="dialog"
          aria-modal="true"
          aria-labelledby="op-new-project-title"
          style={{ zIndex: 1050 }}
          onClick={closeModal}
        >
          <div
            className="modal-dialog modal-dialog-centered"
            role="document"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-content shadow border-0 webhook-projects-modal__panel">
              <form className="op-new-form" onSubmit={createProject}>
                <div className="op-new-modal__header sc-modal-head">
                  <h2
                    className="op-new-modal__title wp-proj-modal-title d-flex align-items-center"
                    id="op-new-project-title"
                  >
                    <i className="fas fa-folder-plus mr-2 flex-shrink-0" aria-hidden="true" />
                    <span>Nouveau projet</span>
                  </h2>
                  <p className="op-new-modal__subtitle text-muted mb-0">
                    Le nom doit être unique dans votre organisation.
                  </p>
                </div>
                <div className="op-new-modal__body">
                  {createError ? (
                    <div className="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                      {createError}
                    </div>
                  ) : null}
                  <label htmlFor="op-new-project-name" className="op-new-label">
                    Nom du projet
                  </label>
                  <div className="input-group input-group-sm">
                    <div className="input-group-prepend">
                      <span className="input-group-text">
                        <i className="fas fa-tag text-muted" aria-hidden="true" />
                      </span>
                    </div>
                    <input
                      ref={nameInputRef}
                      id="op-new-project-name"
                      type="text"
                      className="form-control"
                      maxLength={180}
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      placeholder="Ex. Production, Staging…"
                      autoComplete="off"
                      disabled={busy}
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-sm btn-outline-secondary" onClick={closeModal} disabled={busy}>
                    Annuler
                  </button>
                  <button type="submit" className="btn btn-sm btn-primary op-new-btn" disabled={busy || !name.trim()}>
                    {busy ? (
                      <>
                        <i className="fas fa-circle-notch fa-spin mr-1" aria-hidden="true" />
                        Création…
                      </>
                    ) : (
                      <>
                        <i className="fas fa-check mr-1" aria-hidden="true" />
                        Créer le projet
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </>,
      document.body,
    );

  const deleteModal =
    projectToDelete &&
    typeof document !== 'undefined' &&
    createPortal(
      <>
        <div className="modal-backdrop fade show" role="presentation" style={{ zIndex: 1060 }} />
        <div
          className="modal fade show d-block op-delete-modal webhook-projects-modal"
          tabIndex={-1}
          role="dialog"
          aria-modal="true"
          aria-labelledby="op-delete-project-title"
          style={{ zIndex: 1070 }}
          onClick={closeDeleteModal}
        >
          <div
            className="modal-dialog modal-dialog-centered"
            role="document"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-content shadow border-0 webhook-projects-modal__panel">
              <form className="op-delete-form" onSubmit={confirmDeleteProject}>
                <div className="op-new-modal__header sc-modal-head">
                  <h2
                    className="op-new-modal__title wp-proj-modal-title d-flex align-items-center text-danger"
                    id="op-delete-project-title"
                  >
                    <i className="fas fa-trash-alt mr-2 flex-shrink-0" aria-hidden="true" />
                    <span>Supprimer ce projet ?</span>
                  </h2>
                  <p className="op-new-modal__subtitle text-muted mb-0">
                    Le projet « {projectToDelete.name} » et ses tickets associés seront supprimés définitivement. Cette
                    action est irréversible.
                  </p>
                </div>
                <div className="op-new-modal__body">
                  {deleteError ? (
                    <div className="alert alert-danger py-2 px-3 mb-0 small" role="alert">
                      {deleteError}
                    </div>
                  ) : null}
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    className="btn btn-sm btn-outline-secondary"
                    onClick={closeDeleteModal}
                    disabled={deleteBusy}
                  >
                    Annuler
                  </button>
                  <button type="submit" className="btn btn-sm btn-danger" disabled={deleteBusy}>
                    {deleteBusy ? (
                      <>
                        <i className="fas fa-circle-notch fa-spin mr-1" aria-hidden="true" />
                        Suppression…
                      </>
                    ) : (
                      <>
                        <i className="fas fa-trash mr-1" aria-hidden="true" />
                        Supprimer le projet
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </>,
      document.body,
    );

  if (!orgToken) {
    return <ErrorAlert message="Aucune organisation courante. Sélectionnez une organisation ou reconnectez-vous." />;
  }

  if (!data && loading) {
    return (
      <div className="webhook-projects-page">
        {projectsHeaderPortal}
        {modal}
        {deleteModal}
        <LoadingState />
      </div>
    );
  }

  if (!data) {
    return (
      <div className="webhook-projects-page">
        {projectsHeaderPortal}
        {modal}
        {deleteModal}
        <ErrorAlert message={error || 'Impossible de charger les projets'} onRetry={reload} />
      </div>
    );
  }

  const projects = data.projects ?? [];
  const hasRows = projects.length > 0;
  const totalCount = Number(data.total ?? projects.length) || 0;
  const canDeleteAny = totalCount > 1 && Boolean(data.deleteProjectCsrf);

  return (
    <div className="webhook-projects-page">
      {projectsHeaderPortal}
      {modal}
      {deleteModal}
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}
      <PageCard className="op-projects-card content-card content-card--projects">
        <div className="op-projects-card-body">
          {!hasRows ? (
            <div className="fw-empty op-projects-empty" role="status">
              <h3>Aucun projet</h3>
              <p>Créez un premier projet pour brancher vos webhooks et vos workflows.</p>
              <button
                type="button"
                className="btn btn-primary op-new-btn wp-proj-hero-btn"
                onClick={() => {
                  setCreateError('');
                  setModalOpen(true);
                }}
              >
                <i className="fas fa-plus mr-1" aria-hidden="true" />
                Nouveau projet
              </button>
            </div>
          ) : (
            <div className="op-projects-table-wrap org-table-wrap">
              <div className="table-responsive">
                <table className="table table-borderless ou-members-table org-table op-projects-table mb-0 w-100">
                  <thead className="ou-members-thead op-projects-thead">
                    <tr>
                      <th className="op-projects-thead__col-name wp-proj-th-icon">
                        <i className="fas fa-folder" aria-hidden="true" />
                        <span>Projet</span>
                      </th>
                      <th className="wp-proj-th-icon wp-proj-th-center">
                        <i className="fas fa-ticket-alt" aria-hidden="true" />
                        <span>Tickets</span>
                      </th>
                      <th className="org-table-th-actions" aria-label="Actions" />
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((p) => {
                      const projectToken = String(p.publicToken || p.public_token || '').trim();
                      return (
                      <tr key={p.id} className="ou-member-row op-project-row">
                        <td>
                          <div className="d-flex align-items-start op-project-row-main" style={{ gap: '0.65rem' }}>
                            <span className="op-project-icon" aria-hidden="true">
                              <i className="fas fa-folder" />
                            </span>
                            <div>
                              <div className="op-project-name">{p.name}</div>
                              <div className="small op-project-token">{p.webhookTokenPrefix}…</div>
                            </div>
                          </div>
                        </td>
                        <td className="wp-proj-count-cell">
                          <span className="wp-proj-count-inner">
                            <i className="fas fa-ticket-alt" aria-hidden="true" />
                            <span className="wp-proj-count-value">{p.ticketCount}</span>
                          </span>
                        </td>
                        <td className="text-right actions">
                          <div className="org-table-actions-inner org-table-actions-inner--proj d-inline-flex align-items-center">
                            <Link
                              to={`/projects/${projectToken}/edit`}
                              className="btn btn-sm btn-icon btn-icon--primary org-table-action-btn"
                              title="Modifier"
                            >
                              <i className="fas fa-pen" aria-hidden="true" />
                              <span className="sr-only">Modifier</span>
                            </Link>
                            {canDeleteAny ? (
                              <button
                                type="button"
                                className="btn btn-sm btn-icon btn-icon--danger org-table-action-btn ml-1"
                                title="Supprimer"
                                onClick={() => {
                                  setDeleteError('');
                                  setProjectToDelete(p);
                                }}
                              >
                                <i className="fas fa-trash" aria-hidden="true" />
                                <span className="sr-only">Supprimer</span>
                              </button>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </PageCard>
    </div>
  );
}
