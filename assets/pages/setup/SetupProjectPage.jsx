import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import SetupWizardBack from '../../components/setup/SetupWizardBack.jsx';

const PROJECT_NAME_DRAFT_KEY = 'alertjet-setup-wizard-project-name';

export default function SetupProjectPage() {
  const location = useLocation();
  const [meta, setMeta] = useState(null);
  const [projectName, setProjectName] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [formErrors, setFormErrors] = useState([]);
  const [summaryMessage, setSummaryMessage] = useState('');

  useEffect(() => {
    let cancelled = false;
    setMeta(null);
    fetchJson('/api/setup/projet').then((m) => {
      if (cancelled) return;
      setMeta(m);
      let draft = '';
      try {
        draft = sessionStorage.getItem(PROJECT_NAME_DRAFT_KEY) || '';
      } catch (_) {
        draft = '';
      }
      setProjectName(draft);
      setFieldErrors({});
      setFormErrors([]);
      setSummaryMessage('');
    });
    return () => {
      cancelled = true;
    };
  }, [location.pathname]);

  if (!meta) return <p className="text-muted">…</p>;

  const nameErr = Array.isArray(fieldErrors.name) && fieldErrors.name.length ? fieldErrors.name.join(' ') : '';

  async function onSubmit(e) {
    e.preventDefault();
    setFieldErrors({});
    setFormErrors([]);
    setSummaryMessage('');
    const raw = await postFormRedirect(
      meta.action,
      {
        'first_project_form[name]': projectName,
        'first_project_form[_token]': meta.csrf,
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

  const showTopAlert = summaryMessage || (formErrors && formErrors.length > 0);

  return (
    <div className="row justify-content-center">
      <div className="col-md-8">
        <SetupWizardBack to="/initialisation/profil">Retour au profil</SetupWizardBack>
        <h1 className="h3 mb-3">Projet</h1>
        <p className="text-muted">Étape 4 — premier projet (webhooks / tickets).</p>
        {projectName ? (
          <div className="alert alert-light border small mb-3 text-secondary" role="status">
            Brouillon conservé tant que vous n’avez pas terminé l’initialisation (même en revenant aux étapes précédentes).
          </div>
        ) : null}
        <form className="card card-body shadow-sm setup-wizard-form" onSubmit={onSubmit} noValidate>
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
          <div className="form-group">
            <label>Nom du projet *</label>
            <input
              className={`form-control${nameErr ? ' is-invalid' : ''}`}
              value={projectName}
              onChange={(ev) => {
                const v = ev.target.value;
                setProjectName(v);
                try {
                  sessionStorage.setItem(PROJECT_NAME_DRAFT_KEY, v);
                } catch (_) {
                  /* ignore */
                }
              }}
              required
              maxLength={180}
              aria-invalid={nameErr ? 'true' : undefined}
            />
            {nameErr ? <div className="invalid-feedback d-block">{nameErr}</div> : null}
          </div>
          <button type="submit" className="btn btn-primary">
            Terminer l’initialisation
          </button>
        </form>
      </div>
    </div>
  );
}
