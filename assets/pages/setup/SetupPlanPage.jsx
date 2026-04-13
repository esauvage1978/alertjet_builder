import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import SetupWizardBack from '../../components/setup/SetupWizardBack.jsx';

function planAccentClass(accent) {
  const a = String(accent || '').toLowerCase();
  if (a === 'emerald' || a === 'blue' || a === 'violet') return a;
  return 'blue';
}

/** @param {unknown} v */
function asString(v) {
  return v == null ? '' : String(v);
}

/** @param {unknown} v */
function asFeatureList(v) {
  if (!Array.isArray(v)) return [];
  return v.map((x) => asString(x)).filter(Boolean);
}

export default function SetupPlanPage() {
  const location = useLocation();
  const [meta, setMeta] = useState(null);
  const [plan, setPlan] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [summaryMessage, setSummaryMessage] = useState('');

  useEffect(() => {
    let cancelled = false;
    setMeta(null);
    fetchJson('/api/setup/plans').then((m) => {
      if (cancelled) return;
      setMeta(m);
      const first = Array.isArray(m.plans) && m.plans[0] ? String(m.plans[0].id ?? '') : '';
      const sel = m.selectedPlanId != null && String(m.selectedPlanId) !== '' ? String(m.selectedPlanId) : '';
      const valid = Boolean(sel && Array.isArray(m.plans) && m.plans.some((p) => String(p.id) === sel));
      setPlan(valid ? sel : first);
      setFieldErrors({});
      setSummaryMessage('');
    });
    return () => {
      cancelled = true;
    };
  }, [location.pathname]);

  if (!meta) return <p className="text-muted">…</p>;

  const planErr = Array.isArray(fieldErrors.plan) && fieldErrors.plan.length ? fieldErrors.plan.join(' ') : '';

  async function onSubmit(e) {
    e.preventDefault();
    setFieldErrors({});
    setSummaryMessage('');
    const raw = await postFormRedirect(meta.action, { plan, _token: meta.csrf }, { preferJsonErrors: true });
    if (raw && raw.error === 'validation_failed') {
      setFieldErrors(raw.fieldErrors || {});
      setSummaryMessage(typeof raw.message === 'string' ? raw.message : '');
      if (typeof raw.csrf === 'string') {
        setMeta((m) => (m ? { ...m, csrf: raw.csrf } : m));
      }
    }
  }

  return (
    <div className="row justify-content-center">
      <div className="col-12 col-xl-11">
        <SetupWizardBack to="/initialisation/organisation">Retour à l’organisation</SetupWizardBack>
        <h1 className="h3 mb-3">Formule</h1>
        <p className="text-muted">Étape 2 — comparez les offres et choisissez celle qui vous convient.</p>
        {meta.selectedPlanId != null && String(meta.selectedPlanId) !== '' ? (
          <div className="alert alert-light border small mb-3 text-secondary" role="status">
            Votre formule enregistrée est présélectionnée ; vous pouvez en changer avant de continuer.
          </div>
        ) : null}
        <form className="card card-body shadow-sm" onSubmit={onSubmit}>
          {summaryMessage || planErr ? (
            <div className="alert alert-danger" role="alert">
              {summaryMessage ? <p className="mb-0 font-weight-bold">{summaryMessage}</p> : null}
              {!summaryMessage && planErr ? <p className="mb-0">{planErr}</p> : null}
            </div>
          ) : null}
          <div
            className={`row environment-plan-tiles${planErr ? ' border border-danger rounded p-2' : ''}`}
            role="radiogroup"
            aria-label="Choix de la formule"
          >
            {Array.isArray(meta.plans)
              ? meta.plans.map((p) => {
                  const id = asString(p.id);
                  const accent = planAccentClass(p.accent);
                  const name = asString(p.name ?? p.title ?? p.label ?? p.id);
                  const emoji = asString(p.emoji);
                  const price = asString(p.priceDisplay);
                  const period = asString(p.period);
                  const badge = p.badge != null && p.badge !== '' ? asString(p.badge) : null;
                  const highlight = Boolean(p.highlight);
                  const target = p.target != null && p.target !== '' ? asString(p.target) : null;
                  const targetLabel = p.targetLabel != null && p.targetLabel !== '' ? asString(p.targetLabel) : null;
                  const features = asFeatureList(p.features);
                  const footerNote = p.footerNote != null && p.footerNote !== '' ? asString(p.footerNote) : null;
                  const footerHighlight =
                    p.footerHighlight != null && p.footerHighlight !== '' ? asString(p.footerHighlight) : null;
                  const footerSubnote =
                    p.footerSubnote != null && p.footerSubnote !== '' ? asString(p.footerSubnote) : null;
                  const ctaLabel = asString(p.ctaLabel || 'Choisir cette offre');

                  return (
                    <div key={id || name} className="col-md-6 col-lg-4 mb-3 mb-lg-0 d-flex">
                      <label className={`plan-tile plan-tile--${accent} w-100 mb-0`}>
                        <input
                          className="plan-tile__input"
                          type="radio"
                          name="planchoice"
                          value={id}
                          checked={plan === id}
                          onChange={() => setPlan(id)}
                        />
                        <div className="plan-tile__card card h-100 shadow-sm p-3 d-flex flex-column">
                          {highlight ? <div className="plan-tile__ribbon">Recommandé</div> : null}
                          <div className="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div className="min-width-0">
                              <div className="d-flex align-items-center flex-wrap gap-2">
                                {emoji ? (
                                  <span className="plan-tile__emoji" aria-hidden="true">
                                    {emoji}
                                  </span>
                                ) : null}
                                <span className="plan-tile__name font-weight-bold text-dark mb-0">{name}</span>
                              </div>
                              {badge ? (
                                <span className="plan-tile__header-badge badge badge-pill mt-2 d-inline-block">{badge}</span>
                              ) : null}
                            </div>
                          </div>
                          <div className="mb-3">
                            {price ? (
                              <span className="plan-tile__price">
                                {price}
                                {period ? <span className="plan-tile__period text-muted">{period}</span> : null}
                              </span>
                            ) : null}
                          </div>
                          {target && targetLabel ? (
                            <div className="plan-tile__target border rounded small p-2 mb-3">
                              <span className="text-muted d-block small mb-0">{targetLabel}</span>
                              <span className="font-weight-bold text-dark">{target}</span>
                            </div>
                          ) : null}
                          {features.length > 0 ? (
                            <ul className="plan-tile__features list-unstyled small mb-3 flex-grow-1">
                              {features.map((line, i) => (
                                <li key={i} className="d-flex align-items-start mb-2">
                                  <span className="plan-tile__check mr-2" aria-hidden="true">
                                    ✓
                                  </span>
                                  <span>{line}</span>
                                </li>
                              ))}
                            </ul>
                          ) : null}
                          {(footerNote || footerHighlight || footerSubnote) && (
                            <div className="plan-tile__footer-box border rounded small p-2 mb-3 mt-auto">
                              {footerHighlight ? (
                                <p className="plan-tile__footer-italic font-weight-bold mb-1 small">{footerHighlight}</p>
                              ) : null}
                              {footerNote ? <p className="mb-1 small text-secondary">{footerNote}</p> : null}
                              {footerSubnote ? <p className="mb-0 small text-muted">{footerSubnote}</p> : null}
                            </div>
                          )}
                          <div className="plan-tile__cta btn btn-sm btn-block rounded-pill text-center py-2 mt-auto">
                            {ctaLabel}
                          </div>
                        </div>
                      </label>
                    </div>
                  );
                })
              : null}
          </div>
          {planErr ? <div className="invalid-feedback d-block mt-2">{planErr}</div> : null}
          <button type="submit" className="btn btn-primary mt-4" disabled={!plan}>
            Continuer avec cette formule
          </button>
        </form>
      </div>
    </div>
  );
}
