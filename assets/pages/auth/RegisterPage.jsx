import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

export default function RegisterPage() {
  const { data } = useBootstrap();
  const { i18n, routes } = data;
  const [csrf, setCsrf] = useState('');
  const [email, setEmail] = useState('');
  const [pw1, setPw1] = useState('');
  const [pw2, setPw2] = useState('');

  useEffect(() => {
    fetchJson('/api/auth/csrf-forms').then((j) => setCsrf(j.registration_form));
  }, []);

  async function onSubmit(e) {
    e.preventDefault();
    await postFormRedirect(routes.registerPost, {
      'registration_form[email]': email,
      'registration_form[plainPassword][first]': pw1,
      'registration_form[plainPassword][second]': pw2,
      'registration_form[_token]': csrf,
    });
  }

  return (
    <>
      <header className="auth-card__head auth-card__head--structured">
        <Link to="/login" className="auth-brand auth-brand--panel auth-brand--on-light" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
        <h1 className="auth-panel__title">{i18n.auth_register_heading}</h1>
        <p className="auth-card__tag auth-card__tag--on-light">{i18n.auth_register_tagline}</p>
        <p className="auth-panel__trust" aria-hidden="true">
          {i18n.auth_trust_strip}
        </p>
      </header>
      <form className="auth-form stack-form" onSubmit={onSubmit} autoComplete="on">
        <div className="auth-field">
          <label className="auth-field__label" htmlFor="email">
            {i18n.form_email}
          </label>
          <input id="email" className="auth-field__input" type="email" value={email} onChange={(ev) => setEmail(ev.target.value)} required autoComplete="email" />
        </div>
        <div className="auth-field">
          <label className="auth-field__label" htmlFor="p1">
            {i18n.form_password}
          </label>
          <input id="p1" className="auth-field__input" type="password" value={pw1} onChange={(ev) => setPw1(ev.target.value)} required autoComplete="new-password" />
        </div>
        <div className="auth-field">
          <label className="auth-field__label" htmlFor="p2">
            {i18n.form_password_confirm}
          </label>
          <input id="p2" className="auth-field__input" type="password" value={pw2} onChange={(ev) => setPw2(ev.target.value)} required autoComplete="new-password" />
        </div>
        <button type="submit" className="btn btn-primary btn-auth-submit" disabled={!csrf}>
          {i18n.auth_register_submit}
        </button>
      </form>
      <footer className="auth-card__links auth-card__links--footer">
        <span className="auth-card__links-label">{i18n.auth_register_already}</span>
        <Link to="/login" className="auth-card__links-cta">
          {i18n.auth_register_login_link}
        </Link>
      </footer>
    </>
  );
}
