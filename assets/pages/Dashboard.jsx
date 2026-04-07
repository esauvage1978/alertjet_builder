import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

const card = {
  background: 'rgba(15, 23, 42, 0.7)',
  border: '1px solid rgba(51, 65, 85, 0.8)',
  borderRadius: '12px',
  padding: '1.25rem',
};

export default function Dashboard() {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [name, setName] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await fetch('/api/projects');
      if (!res.ok) throw new Error('Chargement impossible');
      const data = await res.json();
      setProjects(data);
    } catch (e) {
      setError(e.message || 'Erreur réseau');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function createProject(ev) {
    ev.preventDefault();
    if (!name.trim()) return;
    setError('');
    try {
      const res = await fetch('/api/projects', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name.trim() }),
      });
      if (!res.ok) throw new Error('Création impossible');
      setName('');
      await load();
    } catch (e) {
      setError(e.message || 'Erreur');
    }
  }

  return (
    <div style={{ maxWidth: '880px', margin: '0 auto', padding: '1.5rem' }}>
      <header style={{ marginBottom: '2rem' }}>
        <p
          style={{
            fontSize: '0.75rem',
            textTransform: 'uppercase',
            letterSpacing: '0.08em',
            color: '#22d3ee',
            margin: '0 0 0.5rem',
          }}
        >
          AlertJet — MVP
        </p>
        <h1 style={{ margin: 0, fontSize: '1.75rem', color: '#f8fafc' }}>
          Gérez vos incidents sans usine à gaz
        </h1>
        <p style={{ margin: '0.5rem 0 0', color: '#94a3b8', lineHeight: 1.6 }}>
          Créez un projet en une étape, copiez l’URL du webhook, envoyez un POST JSON : un ticket
          apparaît ici.
        </p>
      </header>

      <section style={{ ...card, marginBottom: '1.5rem' }}>
        <h2 style={{ margin: '0 0 1rem', fontSize: '1rem', color: '#f1f5f9' }}>Nouveau projet</h2>
        <form onSubmit={createProject} style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem' }}>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Nom (ex. API production)"
            style={{
              flex: '1 1 200px',
              padding: '0.6rem 0.75rem',
              borderRadius: '8px',
              border: '1px solid #334155',
              background: '#020617',
              color: '#f8fafc',
            }}
          />
          <button
            type="submit"
            style={{
              padding: '0.6rem 1.25rem',
              borderRadius: '8px',
              border: 'none',
              background: 'linear-gradient(135deg, #22d3ee, #2563eb)',
              color: '#0f172a',
              fontWeight: 600,
              cursor: 'pointer',
            }}
          >
            Créer
          </button>
        </form>
      </section>

      {error ? (
        <p style={{ color: '#fca5a5', marginBottom: '1rem' }}>{error}</p>
      ) : null}

      <section style={card}>
        <h2 style={{ margin: '0 0 1rem', fontSize: '1rem', color: '#f1f5f9' }}>Vos projets</h2>
        {loading ? (
          <p style={{ color: '#64748b' }}>Chargement…</p>
        ) : projects.length === 0 ? (
          <p style={{ color: '#64748b', margin: 0 }}>Aucun projet pour le moment.</p>
        ) : (
          <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
            {projects.map((p) => (
              <li
                key={p.id}
                style={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: '0.75rem',
                  padding: '0.75rem 0',
                  borderBottom: '1px solid rgba(51,65,85,0.5)',
                }}
              >
                <div>
                  <strong style={{ color: '#e2e8f0' }}>{p.name}</strong>
                  <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.25rem' }}>
                    Webhook prêt — voir tickets
                  </div>
                </div>
                <Link
                  to={`/project/${p.id}`}
                  style={{
                    textDecoration: 'none',
                    padding: '0.45rem 1rem',
                    borderRadius: '8px',
                    border: '1px solid #334155',
                    color: '#22d3ee',
                    fontWeight: 600,
                    fontSize: '0.875rem',
                  }}
                >
                  Ouvrir →
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
