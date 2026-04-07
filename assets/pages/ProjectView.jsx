import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

const badge = (colorBg, colorText) => ({
  display: 'inline-block',
  fontSize: '0.7rem',
  fontWeight: 700,
  textTransform: 'uppercase',
  letterSpacing: '0.04em',
  padding: '0.2rem 0.45rem',
  borderRadius: '6px',
  background: colorBg,
  color: colorText,
});

const priorityColors = {
  low: badge('rgba(100,116,139,0.35)', '#cbd5e1'),
  medium: badge('rgba(34,211,238,0.2)', '#22d3ee'),
  high: badge('rgba(251,191,36,0.25)', '#fbbf24'),
  critical: badge('rgba(248,113,113,0.3)', '#fecaca'),
};

function PriorityBadge({ p }) {
  const st = priorityColors[p] || priorityColors.medium;
  const label = { low: 'Basse', medium: 'Moyenne', high: 'Haute', critical: 'Critique' }[p] || p;
  return <span style={st}>{label}</span>;
}

function StatusBadge({ s }) {
  const map = {
    open: badge('rgba(74,222,128,0.2)', '#86efac'),
    in_progress: badge('rgba(250,204,21,0.2)', '#fde047'),
    resolved: badge('rgba(96,165,250,0.25)', '#93c5fd'),
  };
  const labels = { open: 'Ouvert', in_progress: 'En cours', resolved: 'Résolu' };
  return <span style={map[s] || map.open}>{labels[s] || s}</span>;
}

export default function ProjectView() {
  const { projectId } = useParams();
  const [project, setProject] = useState(null);
  const [tickets, setTickets] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selected, setSelected] = useState(null);
  const [note, setNote] = useState('');

  const load = useCallback(async () => {
    if (!projectId) return;
    setLoading(true);
    setError('');
    try {
      const [plist, tlist, st] = await Promise.all([
        fetch('/api/projects').then((r) => r.json()),
        fetch(`/api/projects/${projectId}/tickets`).then((r) => {
          if (!r.ok) throw new Error('Tickets introuvables');
          return r.json();
        }),
        fetch(`/api/projects/${projectId}/stats`).then((r) => r.json()),
      ]);
      const p = plist.find((x) => String(x.id) === String(projectId));
      setProject(p || null);
      setTickets(tlist);
      setStats(st);
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setLoading(false);
    }
  }, [projectId]);

  useEffect(() => {
    load();
  }, [load]);

  async function patchTicket(id, body) {
    try {
      const res = await fetch(`/api/tickets/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      if (!res.ok) throw new Error('Mise à jour impossible');
      const updated = await res.json();
      setTickets((prev) => prev.map((t) => (t.id === id ? updated : t)));
      setSelected((cur) => (cur && cur.id === id ? updated : cur));
    } catch (e) {
      setError(e.message);
    }
  }

  async function submitNote() {
    if (!selected || !note.trim()) return;
    await patchTicket(selected.id, { note: note.trim() });
    setNote('');
  }

  function copyWebhook() {
    if (project?.webhookUrl) {
      navigator.clipboard.writeText(project.webhookUrl);
    }
  }

  return (
    <div style={{ maxWidth: '960px', margin: '0 auto', padding: '1.5rem' }}>
      <Link
        to="/"
        style={{
          fontSize: '0.875rem',
          color: '#94a3b8',
          textDecoration: 'none',
          display: 'inline-block',
          marginBottom: '1rem',
        }}
      >
        ← Retour aux projets
      </Link>

      {loading ? (
        <p style={{ color: '#64748b' }}>Chargement…</p>
      ) : error && !project ? (
        <p style={{ color: '#fca5a5' }}>{error}</p>
      ) : (
        <>
          <header style={{ marginBottom: '1.5rem' }}>
            <h1 style={{ margin: '0 0 0.5rem', color: '#f8fafc', fontSize: '1.5rem' }}>
              {project?.name || 'Projet'}
            </h1>
            {stats ? (
              <p style={{ margin: 0, fontSize: '0.875rem', color: '#94a3b8' }}>
                Tickets : {stats.totalTickets} · critiques ouverts : {stats.openCritical}
                {stats.avgResolutionSeconds != null
                  ? ` · résolution moy. ${Math.round(stats.avgResolutionSeconds / 60)} min`
                  : ''}
              </p>
            ) : null}
            {project?.webhookUrl ? (
              <div
                style={{
                  marginTop: '1rem',
                  padding: '0.75rem',
                  background: 'rgba(15,23,42,0.8)',
                  borderRadius: '8px',
                  border: '1px solid #334155',
                  fontSize: '0.8rem',
                  wordBreak: 'break-all',
                  color: '#cbd5e1',
                }}
              >
                <strong style={{ color: '#22d3ee' }}>Webhook (POST JSON)</strong>
                <div style={{ marginTop: '0.35rem' }}>{project.webhookUrl}</div>
                <button
                  type="button"
                  onClick={copyWebhook}
                  style={{
                    marginTop: '0.5rem',
                    padding: '0.35rem 0.75rem',
                    borderRadius: '6px',
                    border: '1px solid #475569',
                    background: 'transparent',
                    color: '#e2e8f0',
                    cursor: 'pointer',
                  }}
                >
                  Copier l’URL
                </button>
                <pre
                  style={{
                    marginTop: '0.75rem',
                    fontSize: '0.7rem',
                    overflow: 'auto',
                    color: '#64748b',
                  }}
                >
                  {`Ex. curl -X POST \"${project.webhookUrl}\" \\\\\n  -H \"Content-Type: application/json\" \\\\\n  -d '{\"title\":\"Erreur paiement\",\"message\":\"Timeout\",\"priority\":\"high\",\"dedupe_key\":\"pay-timeout\"}'`}
                </pre>
              </div>
            ) : null}
          </header>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: '1rem' }}>
            <section
              style={{
                background: 'rgba(15, 23, 42, 0.7)',
                border: '1px solid rgba(51, 65, 85, 0.8)',
                borderRadius: '12px',
                overflow: 'hidden',
              }}
            >
              <h2 style={{ margin: 0, padding: '1rem', fontSize: '0.95rem', borderBottom: '1px solid #334155' }}>
                Tickets
              </h2>
              {tickets.length === 0 ? (
                <p style={{ padding: '1rem', color: '#64748b', margin: 0 }}>Envoie ton premier webhook.</p>
              ) : (
                <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                  {tickets.map((t) => (
                    <li key={t.id}>
                      <button
                        type="button"
                        onClick={() => setSelected(t)}
                        style={{
                          width: '100%',
                          textAlign: 'left',
                          padding: '0.85rem 1rem',
                          border: 'none',
                          borderBottom: '1px solid rgba(51,65,85,0.5)',
                          background:
                            selected?.id === t.id ? 'rgba(34,211,238,0.08)' : 'transparent',
                          color: '#e2e8f0',
                          cursor: 'pointer',
                        }}
                      >
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.35rem', alignItems: 'center' }}>
                          <StatusBadge s={t.status} />
                          <PriorityBadge p={t.priority} />
                          {t.eventCount > 1 ? (
                            <span style={{ fontSize: '0.7rem', color: '#94a3b8' }}>×{t.eventCount}</span>
                          ) : null}
                        </div>
                        <div style={{ marginTop: '0.35rem', fontWeight: 600 }}>{t.title}</div>
                        <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.2rem' }}>
                          #{t.id} · {(t.publicId || '').slice(0, 8)}…
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <aside
              style={{
                background: 'rgba(15, 23, 42, 0.7)',
                border: '1px solid rgba(51, 65, 85, 0.8)',
                borderRadius: '12px',
                padding: '1rem',
                alignSelf: 'start',
              }}
            >
              {selected ? (
                <>
                  <h3 style={{ margin: '0 0 0.75rem', fontSize: '1rem' }}>{selected.title}</h3>
                  <p style={{ fontSize: '0.8rem', color: '#94a3b8', whiteSpace: 'pre-wrap' }}>
                    {selected.description || '—'}
                  </p>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginTop: '1rem' }}>
                    <button
                      type="button"
                      onClick={() => patchTicket(selected.id, { status: 'in_progress' })}
                      style={btnSecondary}
                    >
                      En cours
                    </button>
                    <button
                      type="button"
                      onClick={() => patchTicket(selected.id, { status: 'resolved' })}
                      style={btnPrimary}
                    >
                      Résoudre
                    </button>
                    <button
                      type="button"
                      onClick={() => patchTicket(selected.id, { status: 'open' })}
                      style={btnSecondary}
                    >
                      Rouvrir
                    </button>
                    <button
                      type="button"
                      onClick={() => patchTicket(selected.id, { priority: 'critical' })}
                      style={btnSecondary}
                    >
                      Critique
                    </button>
                    <button
                      type="button"
                      onClick={() => patchTicket(selected.id, { silenced: !selected.silenced })}
                      style={btnSecondary}
                    >
                      {selected.silenced ? 'Réactiver' : 'Silence'}
                    </button>
                  </div>
                  <div style={{ marginTop: '1rem' }}>
                    <textarea
                      value={note}
                      onChange={(e) => setNote(e.target.value)}
                      placeholder="Note interne…"
                      rows={3}
                      style={{
                        width: '100%',
                        borderRadius: '8px',
                        border: '1px solid #334155',
                        background: '#020617',
                        color: '#f8fafc',
                        padding: '0.5rem',
                      }}
                    />
                    <button type="button" onClick={submitNote} style={{ ...btnSecondary, marginTop: '0.5rem' }}>
                      Ajouter la note
                    </button>
                  </div>
                  <div style={{ marginTop: '1.25rem' }}>
                    <h4 style={{ margin: '0 0 0.5rem', fontSize: '0.8rem', color: '#94a3b8' }}>Journal</h4>
                    <ul style={{ listStyle: 'none', padding: 0, margin: 0, maxHeight: '240px', overflow: 'auto' }}>
                      {(selected.logs || []).map((log) => (
                        <li
                          key={log.id}
                          style={{
                            fontSize: '0.75rem',
                            padding: '0.5rem 0',
                            borderBottom: '1px solid rgba(51,65,85,0.4)',
                            color: '#cbd5e1',
                          }}
                        >
                          <span style={{ color: '#64748b' }}>{log.type}</span> — {log.message}
                          <div style={{ color: '#475569', fontSize: '0.65rem', marginTop: '0.2rem' }}>
                            {log.createdAt}
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </>
              ) : (
                <p style={{ margin: 0, color: '#64748b', fontSize: '0.875rem' }}>
                  Sélectionnez un ticket pour agir en un clic.
                </p>
              )}
            </aside>
          </div>
        </>
      )}
    </div>
  );
}

const btnPrimary = {
  padding: '0.4rem 0.75rem',
  borderRadius: '8px',
  border: 'none',
  background: 'linear-gradient(135deg, #22d3ee, #2563eb)',
  color: '#0f172a',
  fontWeight: 600,
  fontSize: '0.8rem',
  cursor: 'pointer',
};

const btnSecondary = {
  padding: '0.4rem 0.75rem',
  borderRadius: '8px',
  border: '1px solid #475569',
  background: 'transparent',
  color: '#e2e8f0',
  fontSize: '0.8rem',
  cursor: 'pointer',
};
