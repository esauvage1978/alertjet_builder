import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { useBootstrap } from '../../context/BootstrapContext.jsx';
import SetupWizardStepper from '../setup/SetupWizardStepper.jsx';
import MainSidebarNav from './MainSidebarNav.jsx';
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
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const { user, flags, routes, spaPaths, i18n, organizations, currentOrganization } = data;
  const hideSidebar = flags.hideAppSidebar;
  /** Parcours initialisation / finalisation profil : pas de barre supérieure ni fil d’Ariane global. */
  const hideMainHeader = flags.hideAppSidebar;
  const org = currentOrganization;

  const path = location.pathname;
  const orgProjectsListMatch = path.match(/^\/organization\/([^/]+)\/projects\/?$/);
  const orgProjectsEditMatch = path.match(/^\/organization\/([^/]+)\/projects\/([^/]+)\/edit\/?$/);
  const shortProjectsEditMatch = path.match(/^\/projects\/([^/]+)\/edit\/?$/);
  const isShortProjectsList = path === '/projects' || path === '/projects/';
  const isOrgProjectsList = orgProjectsListMatch !== null || isShortProjectsList;
  const isProjectEditPage = orgProjectsEditMatch !== null || shortProjectsEditMatch !== null;
  const projectEditToken = shortProjectsEditMatch?.[1] ?? orgProjectsEditMatch?.[2] ?? null;
  /** Org. courante (URL courte) ou segment URL longue pour le lien « Projets ». */
  const projectsListOrgToken = org?.publicToken ?? orgProjectsListMatch?.[1] ?? orgProjectsEditMatch?.[1] ?? null;
  /** Liste projets (URL courte ou `/organization/:token/projects`) : titre injecté par portail dans le content-header. */
  const isProjectsListPage = path === '/projects' || path === '/projects/';

  useEffect(() => {
    setSidebarOpen(false);
  }, [location.pathname]);

  const wrapperClass = [
    'wrapper',
    hideMainHeader ? 'app-shell--parcours' : '',
    !hideSidebar && sidebarOpen ? 'app-shell--sidebar-open' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={wrapperClass}>
      {!hideSidebar && sidebarOpen ? (
        <div
          className="app-shell__backdrop"
          aria-hidden
          onClick={() => setSidebarOpen(false)}
        />
      ) : null}
      {!hideMainHeader ? (
        <nav className="main-header app-topbar navbar navbar-expand navbar-white navbar-light border-bottom-0">
          <ul className="navbar-nav align-items-center flex-nowrap flex-grow-1 mr-2">
            {!hideSidebar ? (
              <li className="nav-item flex-shrink-0 d-lg-none">
                <button
                  type="button"
                  className="nav-link btn btn-link border-0 p-2 app-spa__sidebar-toggle"
                  aria-expanded={sidebarOpen}
                  aria-label={i18n.nav_toggle}
                  onClick={() => setSidebarOpen((v) => !v)}
                >
                  <i className="fas fa-bars" />
                </button>
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
                  {isProjectEditPage && projectsListOrgToken && projectEditToken ? (
                    <>
                      <li className="breadcrumb-item">
                        <Link to="/projects" className="main-header-breadcrumb__link">
                          {i18n.breadcrumb_org_projects}
                        </Link>
                      </li>
                      <li
                        className="breadcrumb-item text-truncate text-monospace main-header-breadcrumb__current"
                        style={{ fontSize: '0.8125rem', maxWidth: '11rem' }}
                        title={projectEditToken}
                      >
                        {projectEditToken}
                      </li>
                      <li
                        className="breadcrumb-item active text-truncate main-header-breadcrumb__current"
                        aria-current="page"
                      >
                        {i18n.breadcrumb_org_projects_edit}
                      </li>
                    </>
                  ) : isOrgProjectsList ? (
                    <li className="breadcrumb-item active text-truncate main-header-breadcrumb__current" aria-current="page">
                      {i18n.breadcrumb_org_projects}
                    </li>
                  ) : (
                    <li
                      className="breadcrumb-item active text-truncate main-header-breadcrumb__current"
                      aria-current="page"
                      dangerouslySetInnerHTML={{ __html: i18n.brand_html }}
                    />
                  )}
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
        <aside className="main-sidebar sidebar-dark-primary shadow">
          <MainSidebarNav data={data} />
        </aside>
      ) : null}

      <div className="content-wrapper">
        {!hideMainHeader ? (
          isProjectsListPage ? (
            <div className="content-header content-header--projects">
              <div className="container-fluid" id="spa-projects-content-header" />
            </div>
          ) : null
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
