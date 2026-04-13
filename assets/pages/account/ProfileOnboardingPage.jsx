import { useEffect, useState } from 'react';
import { fetchJson, postFormRedirect } from '../../api/http.js';

export default function ProfileOnboardingPage() {
  const [meta, setMeta] = useState(null);
  const [displayName, setDisplayName] = useState('');
  const [initials, setInitials] = useState('');
  const [bg, setBg] = useState('');
  const [fg, setFg] = useState('');

  useEffect(() => {
    fetchJson('/api/account/profil-onboarding').then(setMeta);
  }, []);

  useEffect(() => {
    if (!meta) return;
    setDisplayName(meta.displayName || '');
    setInitials(meta.avatarInitialsCustom || '');
    setBg(meta.avatarColor || '');
    setFg(meta.avatarForegroundColor || '');
  }, [meta]);

  if (!meta) return <p className="text-muted">…</p>;

  async function onSubmit(e) {
    e.preventDefault();
    const fields = {
      'user_profile_form[displayName]': displayName,
      'user_profile_form[avatarInitialsCustom]': initials,
      'user_profile_form[avatarColor]': bg,
      'user_profile_form[avatarForegroundColor]': fg,
      'user_profile_form[_token]': meta.csrf,
    };
    await postFormRedirect(meta.action, fields);
  }

  const bgEntries = Object.entries(meta.avatarBgChoices || {});
  const fgEntries = Object.entries(meta.avatarFgChoices || {});

  return (
    <div className="row justify-content-center">
      <div className="col-lg-8">
        <h1 className="h3 mb-3">Finaliser votre profil</h1>
        <p className="text-muted">Nom affiché, initiales et couleurs d’avatar.</p>
        <form className="card card-body shadow-sm" onSubmit={onSubmit}>
          <div className="form-group">
            <label>Nom affiché *</label>
            <input className="form-control" value={displayName} onChange={(ev) => setDisplayName(ev.target.value)} required maxLength={120} />
          </div>
          <div className="form-group">
            <label>Initiales (optionnel, max 3)</label>
            <input className="form-control text-uppercase" value={initials} onChange={(ev) => setInitials(ev.target.value)} maxLength={3} />
          </div>
          <div className="form-group">
            <label>Fond</label>
            <div className="d-flex flex-wrap" style={{ gap: '0.35rem' }}>
              {bgEntries.map(([k, hex]) => (
                <label key={k} className="mb-0" style={{ cursor: 'pointer' }}>
                  <input type="radio" className="sr-only" name="av-bg" checked={bg === hex} onChange={() => setBg(hex)} />
                  <span className="d-inline-block rounded" style={{ width: 28, height: 28, background: hex, border: bg === hex ? '2px solid #333' : '1px solid #ccc' }} title={k} />
                </label>
              ))}
            </div>
          </div>
          <div className="form-group">
            <label>Texte</label>
            <div className="d-flex flex-wrap" style={{ gap: '0.35rem' }}>
              {fgEntries.map(([k, hex]) => (
                <label key={k} className="mb-0" style={{ cursor: 'pointer' }}>
                  <input type="radio" className="sr-only" name="av-fg" checked={fg === hex} onChange={() => setFg(hex)} />
                  <span className="d-inline-block rounded" style={{ width: 28, height: 28, background: hex, border: fg === hex ? '2px solid #333' : '1px solid #ccc' }} title={k} />
                </label>
              ))}
            </div>
          </div>
          <button type="submit" className="btn btn-primary">
            Enregistrer et continuer
          </button>
        </form>
      </div>
    </div>
  );
}
