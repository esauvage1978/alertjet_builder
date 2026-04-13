import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';

const COUNTRIES = ['', 'FR', 'BE', 'CH', 'CA', 'LU'];

function firstMessages(fieldErrors, key) {
  const arr = fieldErrors?.[key];
  if (!Array.isArray(arr) || !arr.length) return '';
  return arr.join(' ');
}

export default function SetupOrganizationPage() {
  const location = useLocation();
  const [meta, setMeta] = useState(null);
  const [name, setName] = useState('');
  const [l1, setL1] = useState('');
  const [l2, setL2] = useState('');
  const [postal, setPostal] = useState('');
  const [city, setCity] = useState('');
  const [country, setCountry] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [formErrors, setFormErrors] = useState([]);
  const [summaryMessage, setSummaryMessage] = useState('');

  useEffect(() => {
    let cancelled = false;
    setMeta(null);
    fetchJson('/api/setup/organisation').then((m) => {
      if (cancelled) return;
      setMeta(m);
      const o = m.organization;
      if (o && typeof o === 'object') {
        setName(o.name ?? '');
        setL1(o.billingLine1 ?? '');
        setL2(o.billingLine2 ?? '');
        setPostal(o.billingPostalCode ?? '');
        setCity(o.billingCity ?? '');
        setCountry(o.billingCountry ?? '');
      } else {
        setName('');
        setL1('');
        setL2('');
        setPostal('');
        setCity('');
        setCountry('');
      }
      setFieldErrors({});
      setFormErrors([]);
      setSummaryMessage('');
    });
    return () => {
      cancelled = true;
    };
  }, [location.pathname]);

  if (!meta) return <p className="text-muted">…</p>;

  async function onSubmit(e) {
    e.preventDefault();
    setFieldErrors({});
    setFormErrors([]);
    setSummaryMessage('');

    const form = e.currentTarget;
    const val = (id) => {
      const el = form.querySelector(`#${id}`);
      return el && 'value' in el ? String(el.value ?? '') : '';
    };

    const nameV = val('setup-org-name');
    const l1V = val('setup-org-billing-line1');
    const l2V = val('setup-org-billing-line2');
    const postalV = val('setup-org-billing-postal');
    const cityV = val('setup-org-billing-city');
    const countryV = val('setup-org-billing-country');

    setName(nameV);
    setL1(l1V);
    setL2(l2V);
    setPostal(postalV);
    setCity(cityV);
    setCountry(countryV);

    const fields = {
      'organization[name]': nameV,
      'organization[billingLine1]': l1V,
      'organization[billingLine2]': l2V,
      'organization[billingPostalCode]': postalV,
      'organization[billingCity]': cityV,
      'organization[billingCountry]': countryV,
      'organization[_token]': meta.csrf,
    };
    const raw = await postFormRedirect(meta.action, fields, { preferJsonErrors: true, sendEmpty: true });
    if (raw && raw.error === 'validation_failed') {
      setFieldErrors(raw.fieldErrors || {});
      setFormErrors(Array.isArray(raw.formErrors) ? raw.formErrors : []);
      setSummaryMessage(typeof raw.message === 'string' ? raw.message : '');
      if (typeof raw.csrf === 'string') {
        setMeta((m) => (m ? { ...m, csrf: raw.csrf } : m));
      }
    }
  }

  const errName = firstMessages(fieldErrors, 'name');
  const errL1 = firstMessages(fieldErrors, 'billingLine1');
  const errL2 = firstMessages(fieldErrors, 'billingLine2');
  const errPostal = firstMessages(fieldErrors, 'billingPostalCode');
  const errCity = firstMessages(fieldErrors, 'billingCity');
  const errCountry = firstMessages(fieldErrors, 'billingCountry');

  const showTopAlert = summaryMessage || (formErrors && formErrors.length > 0);

  const hasSavedOrg = Boolean(meta.organization);

  return (
    <div className="row justify-content-center">
      <div className="col-md-8">
        <h1 className="h3 mb-3 d-flex align-items-center flex-wrap setup-wizard-page-heading">
          <i className="fas fa-building setup-wizard-page-icon" aria-hidden="true" />
          <span>Organisation</span>
        </h1>
        {!hasSavedOrg ? <p className="text-muted">Étape 1 — créez votre organisation principale.</p> : null}
        <form className="card card-body shadow-sm" onSubmit={onSubmit} noValidate autoComplete="off">
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
            <label htmlFor="setup-org-name">Nom de l’organisation *</label>
            <input
              id="setup-org-name"
              name="alertjet-setup-org-name"
              autoComplete="organization"
              className={`form-control${errName ? ' is-invalid' : ''}`}
              value={name}
              onChange={(ev) => setName(ev.target.value)}
              required
              maxLength={180}
              aria-invalid={errName ? 'true' : undefined}
              aria-describedby={errName ? 'err-org-name' : undefined}
            />
            {errName ? (
              <div id="err-org-name" className="invalid-feedback d-block">
                {errName}
              </div>
            ) : null}
          </div>
          <div className="setup-org-address-block">
            <div className="form-group mb-2">
              <label className="mb-1 small text-secondary" htmlFor="setup-org-billing-line1">
                Adresse (ligne 1)
              </label>
              <input
                id="setup-org-billing-line1"
                name="alertjet-org-billing-line1"
                autoComplete="billing address-line1"
                className={`form-control form-control-sm${errL1 ? ' is-invalid' : ''}`}
                value={l1}
                onChange={(ev) => setL1(ev.target.value)}
                aria-invalid={errL1 ? 'true' : undefined}
              />
              {errL1 ? <div className="invalid-feedback d-block small">{errL1}</div> : null}
            </div>
            <div className="form-group mb-2">
              <label className="mb-1 small text-secondary" htmlFor="setup-org-billing-line2">
                Adresse (ligne 2)
              </label>
              <input
                id="setup-org-billing-line2"
                name="alertjet-org-billing-line2"
                autoComplete="billing address-line2"
                className={`form-control form-control-sm${errL2 ? ' is-invalid' : ''}`}
                value={l2}
                onChange={(ev) => setL2(ev.target.value)}
                aria-invalid={errL2 ? 'true' : undefined}
              />
              {errL2 ? <div className="invalid-feedback d-block small">{errL2}</div> : null}
            </div>
            <div className="form-row">
              <div className="form-group col-md-4 mb-2">
                <label className="mb-1 small text-secondary" htmlFor="setup-org-billing-postal">
                  Code postal
                </label>
                <input
                  id="setup-org-billing-postal"
                  name="alertjet-org-billing-postal"
                  autoComplete="billing postal-code"
                  className={`form-control form-control-sm${errPostal ? ' is-invalid' : ''}`}
                  value={postal}
                  onChange={(ev) => setPostal(ev.target.value)}
                  aria-invalid={errPostal ? 'true' : undefined}
                />
                {errPostal ? <div className="invalid-feedback d-block small">{errPostal}</div> : null}
              </div>
              <div className="form-group col-md-8 mb-2">
                <label className="mb-1 small text-secondary" htmlFor="setup-org-billing-city">
                  Ville
                </label>
                <input
                  id="setup-org-billing-city"
                  name="alertjet-org-billing-city"
                  autoComplete="billing address-level2"
                  className={`form-control form-control-sm${errCity ? ' is-invalid' : ''}`}
                  value={city}
                  onChange={(ev) => setCity(ev.target.value)}
                  aria-invalid={errCity ? 'true' : undefined}
                />
                {errCity ? <div className="invalid-feedback d-block small">{errCity}</div> : null}
              </div>
            </div>
            <div className="form-group mb-2">
              <label className="mb-1 small text-secondary" htmlFor="setup-org-billing-country">
                Pays
              </label>
              <select
                id="setup-org-billing-country"
                name="alertjet-org-billing-country"
                autoComplete="billing country"
                className={`custom-select custom-select-sm${errCountry ? ' is-invalid' : ''}`}
                value={country}
                onChange={(ev) => setCountry(ev.target.value)}
                aria-invalid={errCountry ? 'true' : undefined}
              >
                {COUNTRIES.map((c) => (
                  <option key={c || 'none'} value={c}>
                    {c === '' ? '—' : c}
                  </option>
                ))}
              </select>
            {errCountry ? <div className="invalid-feedback d-block small">{errCountry}</div> : null}
            </div>
          </div>
          <div className="setup-wizard-form-actions">
            <button type="submit" className="btn btn-primary">
              Continuer
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
