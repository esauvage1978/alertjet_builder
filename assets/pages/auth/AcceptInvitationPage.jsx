import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchJson, postFormRedirect } from '../../api/http.js';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

export default function AcceptInvitationPage() {
  const { token } = useParams();
  const { data } = useBootstrap();
  const { i18n } = data;
  const [meta, setMeta] = useState(null);
  const [pw1, setPw1] = useState('');
  const [pw2, setPw2] = useState('');

  useEffect(() => {
    let c = true;
    fetchJson(`/api/auth/invitation/${encodeURIComponent(token)}`)
      .then((j) => {
        if (c) setMeta(j);
      })
      .catch(() => {
        if (c) setMeta({ valid: false });
      });
    return () => {
      c = false;
    };
  }, [token]);

  if (meta === null) {
    return <p className="text-muted">…</p>;
  }
  if (!meta.valid) {
    return (
      <>
        <header className="auth-card__head auth-card__head--structured">
          <Link to="/login" className="auth-brand auth-brand--panel auth-brand--on-light" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
          <h1 className="auth-panel__title">{i18n.auth_invitation_heading}</h1>
        </header>
        <p className="text-danger">{i18n.auth_invitation_invalid}</p>
        <Link to="/login" className="auth-card__links-cta">
          {i18n.auth_invitation_login_instead}
        </Link>
      </>
    );
  }

  const tagline = meta.organizationName
    ? i18n.auth_invitation_tagline_org.replace('%org%', meta.organizationName)
    : i18n.auth_invitation_tagline;

  const action = `/invitation/${encodeURIComponent(token)}`;

  async function onSubmit(e) {
    e.preventDefault();
    await postFormRedirect(action, {
      'reset_password_form[plainPassword][first]': pw1,
      'reset_password_form[plainPassword][second]': pw2,
      'reset_password_form[_token]': meta.csrf,
    });
  }

  return (
    <>
      <header className="auth-card__head auth-card__head--structured">
        <Link to="/login" className="auth-brand auth-brand--panel auth-brand--on-light" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
        <h1 className="auth-panel__title">{i18n.auth_invitation_heading}</h1>
        <p className="auth-card__tag auth-card__tag--on-light">{tagline}</p>
        <p className="auth-panel__trust" aria-hidden="true">
          {i18n.auth_trust_strip}
        </p>
      </header>
      <form className="auth-form stack-form" onSubmit={onSubmit}>
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
        <button type="submit" className="btn btn-primary btn-auth-submit">
          {i18n.auth_invitation_submit}
        </button>
      </form>
      <footer className="auth-card__links auth-card__links--footer auth-card__links--center">
        <Link to="/login" className="auth-card__links-cta">
          {i18n.auth_invitation_login_instead}
        </Link>
      </footer>
    </>
  );
}
