import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { fetchJson, postForm } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';

function normalizeStr(v) {
  return typeof v === 'string' ? v.trim() : '';
}

function asId(v) {
  const n = typeof v === 'number' ? v : parseInt(String(v || ''), 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

export default function AdminOptionsPage() {
  const [data, setData] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const [category, setCategory] = useState('');
  const [domain, setDomain] = useState('');
  const [optionName, setOptionName] = useState('');

  const [modalMode, setModalMode] = useState(null); // 'create' | 'edit' | null
  const [current, setCurrent] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const [formCategory, setFormCategory] = useState('');
  const [formDomain, setFormDomain] = useState('');
  const [formName, setFormName] = useState('');
  const [formValue, setFormValue] = useState('');
  const [formComment, setFormComment] = useState('');
  const formNameRef = useRef(null);

  const qs = useMemo(() => {
    const p = new URLSearchParams();
    if (category.trim()) p.set('category', category.trim());
    if (domain.trim()) p.set('domain', domain.trim());
    if (optionName.trim()) p.set('optionName', optionName.trim());
    const s = p.toString();
    return s ? `?${s}` : '';
  }, [category, domain, optionName]);

  async function reload() {
    setError('');
    const json = await fetchJson(`/api/admin/options${qs}`);
    setData(json);
  }

  useEffect(() => {
    reload().catch((e) => setError(e.message || 'Erreur'));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [qs]);

  useEffect(() => {
    const anyModal = modalMode != null || deleteTarget != null;
    if (!anyModal) return undefined;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const t = window.setTimeout(() => {
      if (modalMode) formNameRef.current?.focus();
    }, 80);
    function onKey(ev) {
      if (ev.key !== 'Escape' || busy) return;
      if (deleteTarget) setDeleteTarget(null);
      else setModalMode(null);
    }
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prev;
      window.clearTimeout(t);
      document.removeEventListener('keydown', onKey);
    };
  }, [modalMode, deleteTarget, busy]);

  function openCreate() {
    setCurrent(null);
    setFormCategory(category.trim());
    setFormDomain(domain.trim());
    setFormName('');
    setFormValue('');
    setFormComment('');
    setModalMode('create');
    setError('');
  }

  function openEdit(row) {
    setCurrent(row);
    setFormCategory(normalizeStr(row.category));
    setFormDomain(normalizeStr(row.domain));
    setFormName(normalizeStr(row.optionName));
    setFormValue(typeof row.optionValue === 'string' ? row.optionValue : String(row.optionValue ?? ''));
    setFormComment(normalizeStr(row.comment));
    setModalMode('edit');
    setError('');
  }

  function closeModal() {
    if (busy) return;
    setModalMode(null);
    setCurrent(null);
  }

  async function onSave(ev) {
    ev.preventDefault();
    setBusy(true);
    setError('');
    try {
      const payload = {
        optionName: formName.trim(),
        category: formCategory.trim(),
        domain: formDomain.trim() ? formDomain.trim() : null,
        optionValue: formValue,
        comment: formComment.trim() ? formComment.trim() : null,
      };

      if (modalMode === 'create') {
        const res = await fetch('/api/admin/options', {
          method: 'POST',
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });
        const ct = (res.headers.get('Content-Type') || '').toLowerCase();
        const raw = await res.text();
        if (!ct.includes('application/json')) throw new Error(raw.trim().slice(0, 280) || `HTTP ${res.status}`);
        const json = raw ? JSON.parse(raw) : null;
        if (!res.ok) {
          throw new Error(json?.error === 'validation' ? 'Validation: champ(s) invalide(s).' : json?.error || `HTTP ${res.status}`);
        }
        await reload();
        closeModal();
        return;
      }

      if (modalMode === 'edit') {
        const id = asId(current?.id);
        if (!id) throw new Error('Option invalide.');
        const res = await fetch(`/api/admin/options/${id}`, {
          method: 'PUT',
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });
        const ct = (res.headers.get('Content-Type') || '').toLowerCase();
        const raw = await res.text();
        if (!ct.includes('application/json')) throw new Error(raw.trim().slice(0, 280) || `HTTP ${res.status}`);
        const json = raw ? JSON.parse(raw) : null;
        if (!res.ok) {
          throw new Error(json?.error === 'validation' ? 'Validation: champ(s) invalide(s).' : json?.error || `HTTP ${res.status}`);
        }
        await reload();
        closeModal();
      }
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  async function onDeleteConfirmed() {
    const id = asId(deleteTarget?.id);
    if (!id) return;
    setBusy(true);
    setError('');
    try {
      const res = await fetch(`/api/admin/options/${id}`, {
        method: 'DELETE',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      const ct = (res.headers.get('Content-Type') || '').toLowerCase();
      const raw = await res.text();
      if (!ct.includes('application/json')) throw new Error(raw.trim().slice(0, 280) || `HTTP ${res.status}`);
      const json = raw ? JSON.parse(raw) : null;
      if (!res.ok) throw new Error(json?.error || `HTTP ${res.status}`);
      setDeleteTarget(null);
      await reload();
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setBusy(false);
    }
  }

  const rows = Array.isArray(data?.items) ? data.items : [];

  const toolbar = (
    <div className="d-flex align-items-end justify-content-between flex-wrap" style={{ gap: '0.75rem' }}>
      <div className="form-row" style={{ gap: '0.75rem' }}>
        <div>
          <label className="small text-muted d-block mb-1">Catégorie</label>
          <input className="form-control form-control-sm" value={category} onChange={(e) => setCategory(e.target.value)} placeholder="imap" />
        </div>
        <div>
          <label className="small text-muted d-block mb-1">Domaine (optionnel)</label>
          <input className="form-control form-control-sm" value={domain} onChange={(e) => setDomain(e.target.value)} placeholder="(vide)" />
        </div>
        <div>
          <label className="small text-muted d-block mb-1">Nom</label>
          <input className="form-control form-control-sm" value={optionName} onChange={(e) => setOptionName(e.target.value)} placeholder="fetch_inbox_report_retention_days" />
        </div>
      </div>
      <button type="button" className="btn btn-sm btn-primary" onClick={openCreate} disabled={busy}>
        <i className="fas fa-plus mr-1" aria-hidden="true" />
        Nouvelle option
      </button>
    </div>
  );

  const modal =
    modalMode &&
    typeof document !== 'undefined' &&
    createPortal(
      <>
        <div className="modal-backdrop fade show" role="presentation" style={{ zIndex: 1040 }} />
        <div
          className="modal fade show d-block op-new-modal webhook-projects-modal"
          tabIndex={-1}
          role="dialog"
          aria-modal="true"
          aria-labelledby="admin-option-modal-title"
          style={{ zIndex: 1050 }}
          onClick={closeModal}
        >
          <div className="modal-dialog modal-dialog-centered" role="document" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content shadow border-0 webhook-projects-modal__panel">
              <form className="op-new-form" onSubmit={onSave}>
                <div className="op-new-modal__header sc-modal-head">
                  <h2 className="op-new-modal__title wp-proj-modal-title d-flex align-items-center" id="admin-option-modal-title">
                    <i className="fas fa-sliders-h mr-2 flex-shrink-0" aria-hidden="true" />
                    <span>{modalMode === 'create' ? 'Nouvelle option' : 'Modifier l’option'}</span>
                  </h2>
                  <p className="op-new-modal__subtitle text-muted mb-0">
                    Paramètres système (admin). Attention : impact immédiat.
                  </p>
                </div>
                <div className="op-new-modal__body">
                  {error ? (
                    <div className="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                      {error}
                    </div>
                  ) : null}

                  <div className="form-group">
                    <label className="small font-weight-bold d-block mb-1">Nom</label>
                    <input
                      ref={formNameRef}
                      className="form-control form-control-sm"
                      value={formName}
                      onChange={(e) => setFormName(e.target.value)}
                      placeholder="fetch_inbox_report_retention_days"
                      disabled={busy}
                      autoComplete="off"
                    />
                  </div>
                  <div className="form-row">
                    <div className="form-group col-md-6">
                      <label className="small font-weight-bold d-block mb-1">Catégorie</label>
                      <input className="form-control form-control-sm" value={formCategory} onChange={(e) => setFormCategory(e.target.value)} placeholder="imap" disabled={busy} />
                    </div>
                    <div className="form-group col-md-6">
                      <label className="small font-weight-bold d-block mb-1">Domaine (optionnel)</label>
                      <input className="form-control form-control-sm" value={formDomain} onChange={(e) => setFormDomain(e.target.value)} placeholder="(vide)" disabled={busy} />
                    </div>
                  </div>
                  <div className="form-group">
                    <label className="small font-weight-bold d-block mb-1">Valeur</label>
                    <textarea className="form-control form-control-sm" rows={4} value={formValue} onChange={(e) => setFormValue(e.target.value)} disabled={busy} spellCheck={false} />
                    <p className="text-muted small mb-0 mt-1">
                      Stockée en texte (tu peux mettre un entier, JSON, etc. selon l’usage).
                    </p>
                  </div>
                  <div className="form-group mb-0">
                    <label className="small font-weight-bold d-block mb-1">Commentaire (optionnel)</label>
                    <textarea className="form-control form-control-sm" rows={3} value={formComment} onChange={(e) => setFormComment(e.target.value)} disabled={busy} />
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-sm btn-outline-secondary" onClick={closeModal} disabled={busy}>
                    Annuler
                  </button>
                  <button type="submit" className="btn btn-sm btn-primary" disabled={busy || !formName.trim() || !formCategory.trim()}>
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
                </div>
              </form>
            </div>
          </div>
        </div>
      </>,
      document.body,
    );

  const deleteModal =
    deleteTarget &&
    typeof document !== 'undefined' &&
    createPortal(
      <>
        <div className="modal-backdrop fade show" role="presentation" style={{ zIndex: 1060 }} />
        <div
          className="modal fade show d-block op-delete-modal webhook-projects-modal"
          tabIndex={-1}
          role="dialog"
          aria-modal="true"
          aria-labelledby="admin-option-delete-title"
          style={{ zIndex: 1070 }}
          onClick={() => (busy ? null : setDeleteTarget(null))}
        >
          <div className="modal-dialog modal-dialog-centered" role="document" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content shadow border-0 webhook-projects-modal__panel">
              <div className="op-new-modal__header sc-modal-head">
                <h2 className="op-new-modal__title wp-proj-modal-title d-flex align-items-center text-danger" id="admin-option-delete-title">
                  <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                  Supprimer l’option
                </h2>
                <p className="op-new-modal__subtitle text-muted mb-0">
                  {deleteTarget.category}.{deleteTarget.optionName} (domain: {deleteTarget.domain || '—'})
                </p>
              </div>
              <div className="op-new-modal__body">
                <p className="mb-0 small">
                  Cette action est définitive.
                </p>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-sm btn-outline-secondary" disabled={busy} onClick={() => setDeleteTarget(null)}>
                  Annuler
                </button>
                <button type="button" className="btn btn-sm btn-danger" disabled={busy} onClick={onDeleteConfirmed}>
                  {busy ? (
                    <>
                      <i className="fas fa-circle-notch fa-spin mr-1" aria-hidden="true" />
                      Suppression…
                    </>
                  ) : (
                    <>
                      <i className="fas fa-trash mr-1" aria-hidden="true" />
                      Supprimer
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      </>,
      document.body,
    );

  return (
    <div className="webhook-projects-page">
      <PageCard toolbar={toolbar} className="content-card">
        <div className="card-body">
          {error ? (
            <div className="alert alert-danger py-2 px-3 mb-3 small" role="alert">
              {error}
            </div>
          ) : null}

          <div className="table-responsive">
            <table className="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Catégorie</th>
                  <th>Domaine</th>
                  <th>Nom</th>
                  <th>Valeur</th>
                  <th>Commentaire</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-muted small">
                      Aucune option.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={r.id}>
                      <td>
                        <code className="small">{r.category}</code>
                      </td>
                      <td className="text-muted small">{r.domain || '—'}</td>
                      <td>
                        <code className="small">{r.optionName}</code>
                      </td>
                      <td style={{ maxWidth: 420 }}>
                        <span className="small text-truncate d-inline-block" style={{ maxWidth: '100%' }} title={r.optionValue}>
                          {typeof r.optionValue === 'string' && r.optionValue.length > 120 ? `${r.optionValue.slice(0, 120)}…` : r.optionValue}
                        </span>
                      </td>
                      <td style={{ maxWidth: 320 }}>
                        <span className="small text-muted text-truncate d-inline-block" style={{ maxWidth: '100%' }} title={r.comment || ''}>
                          {r.comment || '—'}
                        </span>
                      </td>
                      <td className="text-nowrap">
                        <button type="button" className="btn btn-sm btn-outline-secondary mr-2" onClick={() => openEdit(r)} disabled={busy}>
                          Modifier
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => setDeleteTarget(r)} disabled={busy}>
                          Supprimer
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </PageCard>
      {modal}
      {deleteModal}
    </div>
  );
}

