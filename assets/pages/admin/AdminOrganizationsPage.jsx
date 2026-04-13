import { useEffect, useState } from 'react';
import { fetchJson } from '../../api/http.js';

export default function AdminOrganizationsPage() {
  const [data, setData] = useState(null);
  useEffect(() => {
    fetchJson('/api/admin/organisations').then(setData);
  }, []);
  if (!data) return <p>…</p>;
  return (
    <div className="card">
      <div className="card-header d-flex justify-content-between align-items-center">
        <span>Organisations</span>
        <a className="btn btn-sm btn-primary" href="/admin/organisations/nouvelle">
          Nouvelle
        </a>
      </div>
      <div className="card-body p-0 table-responsive">
        <table className="table table-striped mb-0">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Jeton</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {data.items.map((o) => (
              <tr key={o.id}>
                <td>{o.name}</td>
                <td>
                  <code className="small">{o.publicToken}</code>
                </td>
                <td>
                  <a href={`/admin/organisations/${o.publicToken}/modifier`}>Modifier</a>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
