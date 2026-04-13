import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

export default function LoginPage() {
  const { data } = useBootstrap();
  const { i18n, routes } = data;
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [ctx, setCtx] = useState(null);

  useEffect(() => {
    let c = true;
    fetchJson('/api/auth/login-context').then((j) => {
      if (c) {
        setCtx(j);
        setEmail((e) => e || j.lastUsername || '');
      }
    });
    return () => {
      c = false;
    };
  }, []);

  const csrf = ctx?.csrf;
  const errorMessage = ctx?.errorMessage;

  async function onSubmit(e) {
    e.preventDefault();
    if (!csrf) return;
    await postFormRedirect(routes.loginPost, {
      _username: email,
      _password: password,
      _csrf_token: csrf,
    });
  }

  return (
    <>
      <header className="auth-card__head auth-card__head--structured">
        <Link to="/login" className="auth-brand auth-brand--panel auth-brand--on-light" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
        <h1 className="auth-panel__title">{i18n.auth_login_heading}</h1>
        <p className="auth-card__tag auth-card__tag--on-light">{i18n.auth_login_tagline}</p>
        <p className="auth-panel__trust" aria-hidden="true">
          {i18n.auth_trust_strip}
        </p>
      </header>
      {errorMessage ? (
        <div className="auth-alert auth-alert--danger" role="alert">
          <span className="auth-alert__icon" aria-hidden="true">
            !
          </span>
          <span className="auth-alert__text">{errorMessage}</span>
        </div>
      ) : null}
      <form className="auth-form stack-form" onSubmit={onSubmit} autoComplete="on">
        <div className="auth-field">
          <label className="auth-field__label" htmlFor="username">
            {i18n.form_email}
          </label>
          <input
            id="username"
            className="auth-field__input"
            type="email"
            value={email}
            onChange={(ev) => setEmail(ev.target.value)}
            required
            autoComplete="username"
            placeholder="nom@entreprise.com"
          />
        </div>
        <div className="auth-field">
          <div className="auth-field__label-row">
            <label className="auth-field__label" htmlFor="password">
              {i18n.form_password}
            </label>
            <Link className="auth-field__side-link" to="/mot-de-passe-oublie">
              {i18n.auth_login_forgot_short}
            </Link>
          </div>
          <input
            id="password"
            className="auth-field__input"
            type="password"
            value={password}
            onChange={(ev) => setPassword(ev.target.value)}
            required
            autoComplete="current-password"
          />
        </div>
        <button type="submit" className="btn btn-primary btn-auth-submit" disabled={!csrf}>
          {i18n.auth_login_submit}
        </button>
      </form>
      <footer className="auth-card__links auth-card__links--footer">
        <span className="auth-card__links-label">{i18n.auth_login_no_account}</span>
        <Link to="/inscription" className="auth-card__links-cta">
          {i18n.auth_login_register}
        </Link>
      </footer>
    </>
  );
}
