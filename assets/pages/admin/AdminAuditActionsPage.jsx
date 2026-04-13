import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';

export default function AdminAuditActionsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = Math.max(1, parseInt(searchParams.get('page') || '1', 10) || 1);
  const action = searchParams.get('action') || '';
  const q = searchParams.get('q') || '';
  const [data, setData] = useState(null);

  const qs = useMemo(() => {
    const p = new URLSearchParams();
    if (action) p.set('action', action);
    if (q) p.set('q', q);
    if (page > 1) p.set('page', String(page));
    const s = p.toString();
    return s ? `?${s}` : '';
  }, [action, q, page]);

  useEffect(() => {
    fetchJson(`/api/admin/audit/actions${qs}`).then(setData);
  }, [qs]);

  if (!data) return <p>…</p>;

  function goPage(np) {
    const n = new URLSearchParams(searchParams);
    if (np <= 1) n.delete('page');
    else n.set('page', String(np));
    setSearchParams(n);
  }

  return (
    <div className="card">
      <div className="card-header">Journal des actions</div>
      <div className="card-body">
        <div className="form-row mb-3">
          <div className="col-md-4">
            <label>Action</label>
            <input className="form-control" value={action} onChange={(e) => setSearchParams({ action: e.target.value, q, page: '1' })} />
          </div>
          <div className="col-md-6">
            <label>Acteur / e-mail</label>
            <input className="form-control" value={q} onChange={(e) => setSearchParams({ action, q: e.target.value, page: '1' })} />
          </div>
        </div>
        <div className="table-responsive">
          <table className="table table-sm table-striped">
            <thead>
              <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Acteur</th>
              </tr>
            </thead>
            <tbody>
              {data.logs.map((log) => (
                <tr key={log.id}>
                  <td>{new Date(log.createdAt).toLocaleString()}</td>
                  <td>{log.action}</td>
                  <td>{log.actorEmail || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="d-flex justify-content-between">
          <button type="button" className="btn btn-outline-secondary btn-sm" disabled={page <= 1} onClick={() => goPage(page - 1)}>
            Précédent
          </button>
          <span className="text-muted small">
            Page {data.pagination.page} / {data.pagination.pageCount} ({data.pagination.total})
          </span>
          <button type="button" className="btn btn-outline-secondary btn-sm" disabled={page >= data.pagination.pageCount} onClick={() => goPage(page + 1)}>
            Suivant
          </button>
        </div>
      </div>
    </div>
  );
}
