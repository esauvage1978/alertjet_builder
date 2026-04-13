import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

export default function ForgotPasswordPage() {
  const { data } = useBootstrap();
  const { i18n, routes } = data;
  const [csrf, setCsrf] = useState('');
  const [email, setEmail] = useState('');

  useEffect(() => {
    fetchJson('/api/auth/csrf-forms').then((j) => setCsrf(j.forgot_password_form));
  }, []);

  async function onSubmit(e) {
    e.preventDefault();
    await postFormRedirect(routes.forgotPost, {
      'forgot_password_form[email]': email,
      'forgot_password_form[_token]': csrf,
    });
  }

  return (
    <>
      <header className="auth-card__head auth-card__head--structured">
        <Link to="/login" className="auth-brand auth-brand--panel auth-brand--on-light" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
        <h1 className="auth-panel__title">{i18n.auth_forgot_heading}</h1>
        <p className="auth-card__tag auth-card__tag--on-light">{i18n.auth_forgot_tagline}</p>
        <p className="auth-panel__trust" aria-hidden="true">
          {i18n.auth_trust_strip}
        </p>
      </header>
      <form className="auth-form stack-form" onSubmit={onSubmit}>
        <div className="auth-field">
          <label className="auth-field__label" htmlFor="email">
            {i18n.form_account_email}
          </label>
          <input id="email" className="auth-field__input" type="email" value={email} onChange={(ev) => setEmail(ev.target.value)} required autoComplete="email" />
        </div>
        <button type="submit" className="btn btn-primary btn-auth-submit" disabled={!csrf}>
          {i18n.auth_forgot_submit}
        </button>
      </form>
      <footer className="auth-card__links auth-card__links--footer auth-card__links--center">
        <Link to="/login" className="auth-card__links-cta">
          {i18n.auth_forgot_back}
        </Link>
      </footer>
    </>
  );
}
