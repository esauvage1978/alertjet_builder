import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';

export default function AdminAuditErrorsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = Math.max(1, parseInt(searchParams.get('page') || '1', 10) || 1);
  const cls = searchParams.get('class') || '';
  const q = searchParams.get('q') || '';
  const [data, setData] = useState(null);

  const qs = useMemo(() => {
    const p = new URLSearchParams();
    if (cls) p.set('class', cls);
    if (q) p.set('q', q);
    if (page > 1) p.set('page', String(page));
    const s = p.toString();
    return s ? `?${s}` : '';
  }, [cls, q, page]);

  useEffect(() => {
    fetchJson(`/api/admin/audit/erreurs${qs}`).then(setData);
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
      <div className="card-header">Erreurs applicatives</div>
      <div className="card-body">
        <div className="form-row mb-3">
          <div className="col-md-4">
            <label>Classe</label>
            <input className="form-control" value={cls} onChange={(e) => setSearchParams({ class: e.target.value, q, page: '1' })} />
          </div>
          <div className="col-md-6">
            <label>Message</label>
            <input className="form-control" value={q} onChange={(e) => setSearchParams({ class: cls, q: e.target.value, page: '1' })} />
          </div>
        </div>
        <div className="table-responsive">
          <table className="table table-sm table-striped">
            <thead>
              <tr>
                <th>Date</th>
                <th>Classe</th>
                <th>Message</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.errors.map((err) => (
                <tr key={err.id}>
                  <td>{new Date(err.createdAt).toLocaleString()}</td>
                  <td>
                    <code className="small">{err.exceptionClass}</code>
                  </td>
                  <td className="text-truncate" style={{ maxWidth: 320 }} title={err.message}>
                    {err.message}
                  </td>
                  <td>
                    <Link to={`/admin/audit/erreurs/${err.id}`}>Détail</Link>
                  </td>
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
