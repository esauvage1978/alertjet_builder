import { useEffect, useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import SetupWizardBack from '../../components/setup/SetupWizardBack.jsx';

function previewInitials(displayName, custom) {
  const c = (custom || '').trim().toUpperCase();
  if (c) return c.slice(0, 3);
  const name = (displayName || '').trim();
  if (!name) return '?';
  const parts = name.split(/\s+/).filter(Boolean);
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

function firstErr(fieldErrors, key) {
  const arr = fieldErrors?.[key];
  if (!Array.isArray(arr) || !arr.length) return '';
  return arr.join(' ');
}

export default function SetupProfilePage() {
  const location = useLocation();
  const [meta, setMeta] = useState(null);
  const [displayName, setDisplayName] = useState('');
  const [initials, setInitials] = useState('');
  const [bg, setBg] = useState('');
  const [fg, setFg] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [formErrors, setFormErrors] = useState([]);
  const [summaryMessage, setSummaryMessage] = useState('');

  useEffect(() => {
    let cancelled = false;
    setMeta(null);
    fetchJson('/api/setup/profil').then((m) => {
      if (!cancelled) setMeta(m);
    });
    return () => {
      cancelled = true;
    };
  }, [location.pathname]);

  useEffect(() => {
    if (!meta) return;
    setDisplayName(meta.displayName || '');
    setInitials(meta.avatarInitialsCustom || '');
    const bgEntries = Object.entries(meta.avatarBgChoices || {});
    const fgEntries = Object.entries(meta.avatarFgChoices || {});
    const bgVal = meta.avatarColor || (bgEntries[0] ? String(bgEntries[0][1]) : '');
    const fgVal = meta.avatarForegroundColor || (fgEntries[0] ? String(fgEntries[0][1]) : '');
    setBg(bgVal);
    setFg(fgVal);
    setFieldErrors({});
    setFormErrors([]);
    setSummaryMessage('');
  }, [meta]);

  const preview = useMemo(() => previewInitials(displayName, initials), [displayName, initials]);

  if (!meta) return <p className="text-muted">…</p>;

  const bgEntries = Object.entries(meta.avatarBgChoices || {});
  const fgEntries = Object.entries(meta.avatarFgChoices || {});

  const dnErr = firstErr(fieldErrors, 'displayName');
  const initialsErr = firstErr(fieldErrors, 'avatarInitialsCustom');
  const bgErr = firstErr(fieldErrors, 'avatarColor');
  const fgErr = firstErr(fieldErrors, 'avatarForegroundColor');
  const showTopAlert = summaryMessage || (formErrors && formErrors.length > 0);

  async function onSubmit(e) {
    e.preventDefault();
    setFieldErrors({});
    setFormErrors([]);
    setSummaryMessage('');
    const raw = await postFormRedirect(
      meta.action,
      {
        'user_profile_form[displayName]': displayName,
        'user_profile_form[avatarInitialsCustom]': initials,
        'user_profile_form[avatarColor]': bg,
        'user_profile_form[avatarForegroundColor]': fg,
        'user_profile_form[_token]': meta.csrf,
      },
      { preferJsonErrors: true },
    );
    if (raw && raw.error === 'validation_failed') {
      setFieldErrors(raw.fieldErrors || {});
      setFormErrors(Array.isArray(raw.formErrors) ? raw.formErrors : []);
      setSummaryMessage(typeof raw.message === 'string' ? raw.message : '');
      if (typeof raw.csrf === 'string') {
        setMeta((m) => (m ? { ...m, csrf: raw.csrf } : m));
      }
    }
  }

  return (
    <div className="row justify-content-center">
      <div className="col-lg-8">
        <SetupWizardBack to="/initialisation/plan">Retour au choix de formule</SetupWizardBack>
        <h1 className="h3 mb-3">Profil</h1>
        <p className="text-muted">Étape 3 — nom affiché, initiales et couleurs comme dans le reste de l’application.</p>
        {(meta.displayName || '').trim() !== '' ? (
          <div className="alert alert-light border small mb-3 text-secondary" role="status">
            Profil déjà enregistré : valeurs rechargées depuis le serveur (vous pouvez les ajuster).
          </div>
        ) : null}
        <form className="card card-body shadow-sm" onSubmit={onSubmit} noValidate>
          {showTopAlert ? (
            <div className="alert alert-danger" role="alert">
              {summaryMessage ? <p className="mb-1 font-weight-bold">{summaryMessage}</p> : null}
              {formErrors && formErrors.length > 0 ? (
                <ul className="mb-0 pl-3">
                  {formErrors.map((line, i) => (
                    <li key={i}>{line}</li>
                  ))}
                </ul>
              ) : null}
            </div>
          ) : null}

          <div className="form-row align-items-start mb-4">
            <div className="form-group col-auto text-center mb-0">
              <div className="small text-muted mb-2">Aperçu</div>
              <span
                className="navbar-avatar d-inline-flex"
                style={{
                  width: '4.5rem',
                  height: '4.5rem',
                  fontSize: '1.1rem',
                  '--avatar-bg': bg || '#0C1929',
                  '--avatar-fg': fg || '#ffffff',
                }}
              >
                {preview}
              </span>
            </div>
            <div className="form-group col min-width-0 mb-0">
              <label>Nom affiché *</label>
              <input
                className={`form-control${dnErr ? ' is-invalid' : ''}`}
                value={displayName}
                onChange={(ev) => setDisplayName(ev.target.value)}
                required
                maxLength={120}
                autoComplete="nickname"
                aria-invalid={dnErr ? 'true' : undefined}
              />
              {dnErr ? <div className="invalid-feedback d-block">{dnErr}</div> : null}
              <label className="mt-3">Initiales (optionnel, max 3)</label>
              <input
                className={`form-control text-uppercase${initialsErr ? ' is-invalid' : ''}`}
                value={initials}
                onChange={(ev) => setInitials(ev.target.value)}
                maxLength={3}
                autoComplete="off"
                aria-invalid={initialsErr ? 'true' : undefined}
              />
              {initialsErr ? <div className="invalid-feedback d-block">{initialsErr}</div> : null}
            </div>
          </div>

          <div className="form-group">
            <label className="d-block">Couleur de fond</label>
            <div className={`d-flex flex-wrap${bgErr ? ' is-invalid' : ''}`} style={{ gap: '0.35rem' }}>
              {bgEntries.map(([k, hex]) => (
                <label key={k} className="mb-0" style={{ cursor: 'pointer' }} title={k}>
                  <input type="radio" className="sr-only" name="setup-av-bg" checked={bg === hex} onChange={() => setBg(hex)} />
                  <span
                    className="d-inline-block rounded"
                    style={{
                      width: 32,
                      height: 32,
                      background: hex,
                      border: bg === hex ? '3px solid #0f172a' : '1px solid #cbd5e1',
                    }}
                  />
                </label>
              ))}
            </div>
            {bgErr ? <div className="invalid-feedback d-block">{bgErr}</div> : null}
          </div>

          <div className="form-group">
            <label className="d-block">Couleur du texte</label>
            <div className={`d-flex flex-wrap${fgErr ? ' is-invalid' : ''}`} style={{ gap: '0.35rem' }}>
              {fgEntries.map(([k, hex]) => (
                <label key={k} className="mb-0" style={{ cursor: 'pointer' }} title={k}>
                  <input type="radio" className="sr-only" name="setup-av-fg" checked={fg === hex} onChange={() => setFg(hex)} />
                  <span
                    className="d-inline-block rounded"
                    style={{
                      width: 32,
                      height: 32,
                      background: hex,
                      border: fg === hex ? '3px solid #0f172a' : '1px solid #cbd5e1',
                    }}
                  />
                </label>
              ))}
            </div>
            {fgErr ? <div className="invalid-feedback d-block">{fgErr}</div> : null}
          </div>

          <button type="submit" className="btn btn-primary">
            Continuer
          </button>
        </form>
      </div>
    </div>
  );
}
