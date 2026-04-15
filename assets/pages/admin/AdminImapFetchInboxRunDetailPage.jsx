import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';

function groupByOrganization(projects) {
  const map = new Map();
  for (const p of projects) {
    const key = p.organizationName || '—';
    const prev = map.get(key) || [];
    prev.push(p);
    map.set(key, prev);
  }
  return Array.from(map.entries()).map(([orgName, items]) => ({ orgName, items }));
}

export default function AdminImapFetchInboxRunDetailPage() {
  const { id } = useParams();
  const [data, setData] = useState(null);

  const runId = useMemo(() => (id ? parseInt(id, 10) : null), [id]);

  useEffect(() => {
    if (!runId) return;
    fetchJson(`/api/admin/imap/fetch-inbox/runs/${runId}`).then(setData);
  }, [runId]);

  if (!data) return <p>…</p>;

  const run = data.run;
  const groups = groupByOrganization(data.projects || []);

  return (
    <div>
      <div className="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h1 className="h5 mb-1">IMAP — Détail du rapport</h1>
          <div className="text-muted small">
            {run?.startedAt ? new Date(run.startedAt).toLocaleString() : '—'} — {run?.durationMs != null ? `${Math.round(run.durationMs / 100) / 10}s` : '—'}
          </div>
        </div>
        <Link className="btn btn-sm btn-outline-secondary" to="/admin/imap/fetch-inbox">
          Retour à la liste
        </Link>
      </div>

      <div className="card mb-3">
        <div className="card-body d-flex flex-wrap" style={{ gap: '1rem' }}>
          <div>
            <div className="text-muted small">Organisations</div>
            <div className="font-weight-bold">{run?.totalOrganizations ?? '—'}</div>
          </div>
          <div>
            <div className="text-muted small">Projets</div>
            <div className="font-weight-bold">{run?.totalProjects ?? '—'}</div>
          </div>
          <div>
            <div className="text-muted small">Mails non lus</div>
            <div className="font-weight-bold">{run?.totalUnseen ?? '—'}</div>
          </div>
          <div>
            <div className="text-muted small">Tickets créés</div>
            <div className="font-weight-bold">{run?.totalTickets ?? '—'}</div>
          </div>
          <div>
            <div className="text-muted small">Échecs</div>
            <div className={`font-weight-bold ${run?.totalFailures > 0 ? 'text-danger' : ''}`}>{run?.totalFailures ?? '—'}</div>
          </div>
          <div className="ml-auto text-muted small">
            Rétention appliquée : {run?.retentionDays ?? '—'} jour(s)
          </div>
        </div>
      </div>

      {groups.map((g) => (
        <div key={g.orgName} className="card mb-3">
          <div className="card-header d-flex justify-content-between align-items-center">
            <span>{g.orgName}</span>
            <span className="text-muted small">{g.items.length} projet(s)</span>
          </div>
          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>Projet</th>
                    <th>Serveur</th>
                    <th>Dossier</th>
                    <th>Mails non lus</th>
                    <th>Tickets</th>
                    <th>Échecs</th>
                    <th>Détails</th>
                  </tr>
                </thead>
                <tbody>
                  {g.items.map((p) => (
                    <tr key={p.id}>
                      <td className="font-weight-bold">{p.projectName}</td>
                      <td className="small text-muted">
                        {p.imapHost}:{p.imapPort}
                        {p.imapTls ? ' (TLS)' : ''}
                      </td>
                      <td>
                        <code className="small">{p.imapMailbox}</code>
                      </td>
                      <td>{p.unseenCount}</td>
                      <td>{p.ticketsCreated}</td>
                      <td>{p.failureCount > 0 ? <span className="text-danger font-weight-bold">{p.failureCount}</span> : <span className="text-muted">0</span>}</td>
                      <td style={{ maxWidth: 420 }}>
                        {p.connectionError || p.mailboxError || (Array.isArray(p.failures) && p.failures.length > 0) ? (
                          <details>
                            <summary className="small text-muted" style={{ cursor: 'pointer' }}>
                              Voir
                            </summary>
                            {p.connectionError ? (
                              <div className="small mt-2">
                                <strong>Connexion</strong>: <span className="text-danger">{p.connectionError}</span>
                              </div>
                            ) : null}
                            {p.mailboxError ? (
                              <div className="small mt-2">
                                <strong>Dossier</strong>: <span className="text-danger">{p.mailboxError}</span>
                              </div>
                            ) : null}
                            {Array.isArray(p.failures) && p.failures.length > 0 ? (
                              <pre
                                className="small mb-0 mt-2 p-2 bg-light rounded border"
                                style={{ fontSize: '0.7rem', maxHeight: '14rem', overflow: 'auto', whiteSpace: 'pre-wrap' }}
                              >
                                {JSON.stringify(p.failures, null, 2)}
                              </pre>
                            ) : null}
                          </details>
                        ) : (
                          <span className="text-muted small">—</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

