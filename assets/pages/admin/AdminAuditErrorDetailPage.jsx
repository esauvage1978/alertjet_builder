import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';

export default function AdminAuditErrorDetailPage() {
  const { id } = useParams();
  const [log, setLog] = useState(null);
  const [err, setErr] = useState(null);

  useEffect(() => {
    fetchJson(`/api/admin/audit/erreurs/${encodeURIComponent(id)}`)
      .then(setLog)
      .catch((e) => setErr(e.message));
  }, [id]);

  if (err) return <p className="text-danger">{err}</p>;
  if (!log) return <p>…</p>;

  return (
    <div className="card">
      <div className="card-header d-flex justify-content-between">
        <span>Erreur #{log.id}</span>
        <Link to="/admin/audit/erreurs">Retour</Link>
      </div>
      <div className="card-body">
        <p>
          <strong>Classe :</strong> <code>{log.exceptionClass}</code>
        </p>
        <p>
          <strong>Message :</strong> {log.message}
        </p>
        <p>
          <strong>Route :</strong> {log.route || '—'}
        </p>
        <p>
          <strong>URI :</strong> {log.requestUri || '—'}
        </p>
        <pre className="bg-light p-3 small" style={{ maxHeight: 420, overflow: 'auto' }}>
          {log.trace}
        </pre>
      </div>
    </div>
  );
}
