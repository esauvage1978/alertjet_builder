import { Link, NavLink, Outlet } from 'react-router-dom';
import { useBootstrap } from '../../context/BootstrapContext.jsx';
import SetupWizardStepper from '../setup/SetupWizardStepper.jsx';
import { LocaleSwitcher } from './LocaleSwitcher.jsx';

function FlashBanner({ flashes }) {
  if (!flashes || typeof flashes !== 'object') return null;
  const rows = Object.entries(flashes).flatMap(([type, messages]) =>
    (Array.isArray(messages) ? messages : []).map((m, i) => ({ type, m, i })),
  );
  if (rows.length === 0) return null;
  return (
    <div className="container-fluid pt-3">
      {rows.map(({ type, m, i }) => {
        const cls =
          type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : type === 'info' ? 'alert-info' : 'alert-danger';
        return (
          <div key={`${type}-${i}`} className={`alert ${cls}`} role="alert">
            {m}
          </div>
        );
      })}
    </div>
  );
}

export default function AppShell() {
  const { data } = useBootstrap();
  const { user, flags, routes, spaPaths, i18n, organizations, currentOrganization } = data;
  const hideSidebar = flags.hideAppSidebar;
  /** Parcours initialisation / finalisation profil : pas de barre supérieure ni fil d’Ariane global. */
  const hideMainHeader = flags.hideAppSidebar;
  const org = currentOrganization;

  const navCls = ({ isActive }) => `nav-link${isActive ? ' active' : ''}`;

  return (
    <div className={`wrapper${hideMainHeader ? ' app-shell--parcours' : ''}`}>
      {!hideMainHeader ? (
        <nav className="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0 elevation-1">
          <ul className="navbar-nav align-items-center flex-nowrap flex-grow-1 mr-2">
            {!hideSidebar ? (
              <li className="nav-item flex-shrink-0">
                <a className="nav-link" data-widget="pushmenu" href="#" role="button" aria-label={i18n.nav_toggle}>
                  <i className="fas fa-bars" />
                </a>
              </li>
            ) : null}
            <li className="nav-item d-flex align-items-center min-width-0 flex-grow-1 pl-3 main-header__breadcrumb-slot">
              <nav className="main-header-breadcrumb" aria-label="breadcrumb">
                <ol className="breadcrumb mb-0">
                  <li className="breadcrumb-item">
                    <Link to="/" className="main-header-breadcrumb__link">
                      {i18n.breadcrumb_home}
                    </Link>
                  </li>
                  <li
                    className="breadcrumb-item active text-truncate main-header-breadcrumb__current"
                    aria-current="page"
                    dangerouslySetInnerHTML={{ __html: i18n.brand_html }}
                  />
                </ol>
              </nav>
            </li>
          </ul>
          <ul className="navbar-nav ml-auto align-items-center">
            <LocaleSwitcher routes={routes} i18n={i18n} />
            <li className="nav-item dropdown">
              <a className="nav-link d-flex align-items-center py-1" data-toggle="dropdown" href="#" id="navbar-user-menu">
                <span
                  className="navbar-avatar"
                  style={{ '--avatar-bg': user.avatarColor, '--avatar-fg': user.avatarForegroundColor }}
                >
                  {user.initials}
                </span>
                <span className="ml-2 text-left d-none d-sm-block">
                  <span className="d-block small font-weight-bold text-dark text-nowrap">
                    {user.displayName || user.email}
                  </span>
                  <span className={`badge ${user.roleBadgeClass} navbar-role-badge`}>{user.primaryRoleKey}</span>
                </span>
              </a>
              <div className="dropdown-menu dropdown-menu-right">
                {flags.showMainNavDestinations ? (
                  <>
                    <a href={routes.profile} className="dropdown-item">
                      <i className="fas fa-user-edit mr-2" />
                      {i18n.nav_profile}
                    </a>
                    <div className="dropdown-divider" />
                    {flags.canViewActivityLog ? (
                      <Link to={spaPaths.accountActivity} className="dropdown-item">
                        <i className="fas fa-list-alt mr-2" />
                        {i18n.nav_activity}
                      </Link>
                    ) : null}
                    {organizations?.length > 0 && flags.canAccessOrganizationBillingPage ? (
                      <Link to={spaPaths.organizationBilling} className="dropdown-item">
                        <i className="fas fa-building mr-2" />
                        {i18n.nav_org_billing}
                      </Link>
                    ) : null}
                    <div className="dropdown-divider" />
                  </>
                ) : null}
                <form method="post" action={routes.logout} className="px-0 m-0">
                  <input type="hidden" name="_csrf_token" value={data.csrf?.logout || ''} />
                  <button type="submit" className="dropdown-item text-danger">
                    <i className="fas fa-sign-out-alt mr-2" />
                    {i18n.nav_logout}
                  </button>
                </form>
              </div>
            </li>
          </ul>
        </nav>
      ) : null}

      {!hideSidebar ? (
        <aside className="main-sidebar sidebar-dark-primary elevation-4">
          <a href={routes.spa} className="brand-link">
            <span className="brand-text font-weight-light app-brand-html" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
          </a>
          <div className="sidebar sidebar--stack">
            <nav className="mt-2 sidebar__nav">
              <ul
                className="nav nav-pills nav-sidebar flex-column nav-flat"
                data-widget="treeview"
                role="menu"
                data-accordion="false"
              >
                <li className="nav-item">
                  <NavLink to="/" end className={navCls}>
                    <i className="nav-icon fas fa-tachometer-alt" />
                    <p>{i18n.nav_dashboard}</p>
                  </NavLink>
                </li>
                {org && organizations?.length > 0 ? (
                  <>
                    <li className="nav-header">{i18n.nav_section_admin}</li>
                    {flags.canAccessOrganizationBillingPage ? (
                      <li className="nav-item">
                        <NavLink to={spaPaths.organizationBilling} className={navCls}>
                          <i className="nav-icon fas fa-building" />
                          <p>{i18n.nav_org_billing}</p>
                        </NavLink>
                      </li>
                    ) : null}
                    {flags.canEditCurrentOrganization ? (
                      <li className="nav-item">
                        <NavLink to={spaPaths.organizationUsers} className={navCls}>
                          <i className="nav-icon fas fa-user-group" />
                          <p>{i18n.nav_org_users}</p>
                        </NavLink>
                      </li>
                    ) : null}
                    <li className="nav-header">{i18n.nav_section_tickets}</li>
                    {flags.canEditCurrentOrganization && spaPaths.organizationProjects ? (
                      <li className="nav-item">
                        <NavLink to={spaPaths.organizationProjects} className={navCls}>
                          <i className="nav-icon fas fa-folder-open" />
                          <p>{i18n.nav_project}</p>
                        </NavLink>
                      </li>
                    ) : null}
                    <li className="nav-item">
                      <NavLink to={spaPaths.organizationTickets} className={navCls}>
                        <i className="nav-icon fas fa-ticket-alt" />
                        <p>{i18n.nav_tickets}</p>
                      </NavLink>
                    </li>
                  </>
                ) : null}
                {flags.canViewActivityLog ? (
                  <li className="nav-item">
                    <NavLink to={spaPaths.accountActivity} className={navCls}>
                      <i className="nav-icon fas fa-clipboard-list" />
                      <p>{i18n.nav_activity}</p>
                    </NavLink>
                  </li>
                ) : null}
                {flags.isAdmin ? (
                  <>
                    <li className="nav-header">{i18n.nav_admin_header}</li>
                    <li className="nav-item">
                      <NavLink to={spaPaths.adminOrganizations} className={navCls}>
                        <i className="nav-icon fas fa-sitemap" />
                        <p>{i18n.nav_admin_orgs}</p>
                      </NavLink>
                    </li>
                    <li className="nav-item">
                      <NavLink to={spaPaths.adminUsers} className={navCls}>
                        <i className="nav-icon fas fa-users" />
                        <p>{i18n.nav_admin_users}</p>
                      </NavLink>
                    </li>
                    <li className="nav-item">
                      <NavLink to={spaPaths.adminAuditActions} className={navCls}>
                        <i className="nav-icon fas fa-history" />
                        <p>{i18n.nav_admin_audit_actions}</p>
                      </NavLink>
                    </li>
                    <li className="nav-item">
                      <NavLink to={spaPaths.adminAuditErrors} className={navCls}>
                        <i className="nav-icon fas fa-bug" />
                        <p>{i18n.nav_admin_audit_errors}</p>
                      </NavLink>
                    </li>
                  </>
                ) : null}
              </ul>
            </nav>
            {organizations?.length > 1 ? (
              <div className="p-2 border-top border-secondary">
                <select
                  className="custom-select custom-select-sm"
                  value={org?.publicToken || ''}
                  onChange={(e) => {
                    const tok = e.target.value;
                    if (!tok) return;
                    window.location.href = routes.orgContextSwitch.replace('__token__', tok);
                  }}
                  aria-label={i18n.nav_section_admin}
                >
                  {organizations.map((o) => (
                    <option key={o.publicToken} value={o.publicToken}>
                      {o.name}
                    </option>
                  ))}
                </select>
              </div>
            ) : null}
          </div>
        </aside>
      ) : null}

      <div className="content-wrapper">
        {!hideMainHeader ? (
          <div className="content-header">
            <div className="container-fluid">
              <h1
                className="m-0 text-dark content-header__title app-brand-html"
                dangerouslySetInnerHTML={{ __html: i18n.brand_html }}
              />
            </div>
          </div>
        ) : (
          <div className="app-shell__parcours-brand border-bottom bg-white elevation-1">
            <div className="container-fluid py-3">
              <p
                className="mb-0 app-brand-html app-shell__parcours-brand-title"
                dangerouslySetInnerHTML={{ __html: i18n.brand_html }}
              />
              <SetupWizardStepper />
            </div>
          </div>
        )}
        <section className="content">
          <FlashBanner flashes={data.flashes} />
          <div className="container-fluid">
            <Outlet />
          </div>
        </section>
      </div>

      {!hideMainHeader ? (
        <footer className="main-footer text-sm">
          <strong className="app-brand-html" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} /> — {i18n.footer_tagline}
        </footer>
      ) : null}
    </div>
  );
}
