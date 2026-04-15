import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';

export default function AdminImapFetchInboxRunsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = Math.max(1, parseInt(searchParams.get('page') || '1', 10) || 1);
  const [data, setData] = useState(null);

  const qs = useMemo(() => {
    const p = new URLSearchParams();
    if (page > 1) p.set('page', String(page));
    const s = p.toString();
    return s ? `?${s}` : '';
  }, [page]);

  useEffect(() => {
    fetchJson(`/api/admin/imap/fetch-inbox/runs${qs}`).then(setData);
  }, [qs]);

  function goPage(np) {
    const n = new URLSearchParams(searchParams);
    if (np <= 1) n.delete('page');
    else n.set('page', String(np));
    setSearchParams(n);
  }

  if (!data) return <p>…</p>;

  return (
    <div className="card">
      <div className="card-header">IMAP — Rapports « fetch-inbox »</div>
      <div className="card-body">
        <p className="text-muted small mb-3">
          Chaque exécution de la commande <code>app:project:fetch-inbox</code> génère un rapport détaillé (par organisation et projet).
        </p>
        <div className="table-responsive">
          <table className="table table-sm table-striped">
            <thead>
              <tr>
                <th>Date</th>
                <th>Durée</th>
                <th>Orgs</th>
                <th>Projets</th>
                <th>Mails (non lus)</th>
                <th>Tickets créés</th>
                <th>Échecs</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {(data.runs || []).map((r) => (
                <tr key={r.id}>
                  <td>{r.startedAt ? new Date(r.startedAt).toLocaleString() : '—'}</td>
                  <td className="text-nowrap">{typeof r.durationMs === 'number' ? `${Math.round(r.durationMs / 100) / 10}s` : '—'}</td>
                  <td>{r.totalOrganizations ?? '—'}</td>
                  <td>{r.totalProjects ?? '—'}</td>
                  <td>{r.totalUnseen ?? '—'}</td>
                  <td>{r.totalTickets ?? '—'}</td>
                  <td>
                    {r.totalFailures > 0 ? (
                      <span className="text-danger font-weight-bold">{r.totalFailures}</span>
                    ) : (
                      <span className="text-muted">0</span>
                    )}
                  </td>
                  <td className="text-nowrap">
                    <Link to={`/admin/imap/fetch-inbox/${r.id}`}>Détail</Link>
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
            Page {data.pagination?.page ?? page} / {data.pagination?.pageCount ?? 1}
          </span>
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm"
            disabled={data.pagination?.pageCount ? page >= data.pagination.pageCount : true}
            onClick={() => goPage(page + 1)}
          >
            Suivant
          </button>
        </div>
      </div>
    </div>
  );
}

