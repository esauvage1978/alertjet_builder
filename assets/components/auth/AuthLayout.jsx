import { Outlet } from 'react-router-dom';
import { useBootstrap } from '../../context/BootstrapContext.jsx';
import { LocaleSwitcher } from '../shell/LocaleSwitcher.jsx';

function GuestFlashes({ flashes }) {
  if (!flashes || typeof flashes !== 'object') return null;
  const rows = Object.entries(flashes).flatMap(([type, messages]) =>
    (Array.isArray(messages) ? messages : []).map((m, i) => ({ type, m, i })),
  );
  if (!rows.length) return null;
  return (
    <>
      {rows.map(({ type, m, i }) => {
        const tone = type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'danger';
        return (
          <div key={`${type}-${i}`} className={`auth-alert auth-alert--${tone}`} role="alert">
            <span className="auth-alert__icon" aria-hidden="true">
              {tone === 'success' ? '✓' : tone === 'info' ? 'i' : '!'}
            </span>
            <span className="auth-alert__text">{m}</span>
          </div>
        );
      })}
    </>
  );
}

export default function AuthLayout() {
  const { data } = useBootstrap();
  const { i18n, routes } = data;

  return (
    <div className="auth-page">
      <div className="auth-page__locale">
        <LocaleSwitcher routes={routes} i18n={i18n} />
      </div>
      <div className="auth-split">
        <aside className="auth-split__hero" aria-label={i18n.auth_product_aria}>
          <div className="auth-split__heroPattern" />
          <div className="auth-split__heroInner">
            <p className="auth-split__productName" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
            <p className="auth-split__title">
              {i18n.auth_hero_title_part1}{' '}
              <span className="auth-split__titleDot" aria-hidden="true">
                ·
              </span>{' '}
              <span className="auth-split__titleAccent">{i18n.auth_hero_secure}</span>
            </p>
            <p className="auth-split__lead" dangerouslySetInnerHTML={{ __html: i18n.auth_hero_lead_html }} />
            <ul className="auth-split__bullets">
              <li>{i18n.auth_hero_bullet1}</li>
              <li>{i18n.auth_hero_bullet2}</li>
              <li>{i18n.auth_hero_bullet3}</li>
            </ul>
          </div>
        </aside>
        <div className="auth-split__panel">
          <div className="auth-split__panelInner auth-card auth-card--in-split auth-card--on-light">
            <GuestFlashes flashes={data.flashes} />
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  );
}
