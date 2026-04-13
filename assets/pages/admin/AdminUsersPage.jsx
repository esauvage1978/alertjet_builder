import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';

export default function AdminUsersPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = Math.max(1, parseInt(searchParams.get('page') || '1', 10) || 1);
  const org = searchParams.get('organization') || '';
  const q = searchParams.get('q') || '';
  const [data, setData] = useState(null);

  const qs = useMemo(() => {
    const p = new URLSearchParams();
    if (org) p.set('organization', org);
    if (q) p.set('q', q);
    if (page > 1) p.set('page', String(page));
    const s = p.toString();
    return s ? `?${s}` : '';
  }, [org, q, page]);

  useEffect(() => {
    fetchJson(`/api/admin/utilisateurs${qs}`).then(setData);
  }, [qs]);

  if (!data) return <p>…</p>;

  function goPage(p) {
    const n = new URLSearchParams(searchParams);
    if (p <= 1) n.delete('page');
    else n.set('page', String(p));
    setSearchParams(n);
  }

  return (
    <div className="card">
      <div className="card-header">Utilisateurs</div>
      <div className="card-body">
        <div className="form-row mb-3">
          <div className="col-md-4">
            <label>Organisation</label>
            <select
              className="custom-select"
              value={org}
              onChange={(e) => {
                const v = e.target.value;
                setSearchParams((prev) => {
                  const n = new URLSearchParams(prev);
                  if (v) n.set('organization', v);
                  else n.delete('organization');
                  n.delete('page');
                  return n;
                });
              }}
            >
              <option value="">—</option>
              {data.organizations.map((o) => (
                <option key={o.id} value={String(o.id)}>
                  {o.name}
                </option>
              ))}
            </select>
          </div>
          <div className="col-md-6">
            <label>Recherche</label>
            <input
              className="form-control"
              value={q}
              onChange={(e) => {
                const v = e.target.value;
                setSearchParams((prev) => {
                  const n = new URLSearchParams(prev);
                  if (v) n.set('q', v);
                  else n.delete('q');
                  n.delete('page');
                  return n;
                });
              }}
              placeholder="E-mail…"
            />
          </div>
        </div>
        <div className="table-responsive">
          <table className="table table-sm table-striped">
            <thead>
              <tr>
                <th>E-mail</th>
                <th>Nom</th>
                <th>Rôle</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.users.map((u) => (
                <tr key={u.id}>
                  <td>{u.email}</td>
                  <td>{u.displayName || '—'}</td>
                  <td>{u.primaryRole}</td>
                  <td>
                    <a href={`/admin/utilisateurs/${u.id}/modifier`}>Modifier</a>
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
