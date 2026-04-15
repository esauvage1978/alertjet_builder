import { useEffect, useMemo, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';

/**
 * Menu latéral (sections + entrées), structure type vitrine : admin-brand, admin-nav, pied d’org.
 *
 * @param {{ data: object }} props
 */
export default function MainSidebarNav({ data }) {
  const location = useLocation();
  const { routes, i18n, organizations, currentOrganization: org, flags, spaPaths } = data;
  const [collapsed, setCollapsed] = useState(() => {
    try {
      return window.localStorage.getItem('aj_sidebar_collapsed') === '1';
    } catch {
      return false;
    }
  });

  useEffect(() => {
    try {
      window.localStorage.setItem('aj_sidebar_collapsed', collapsed ? '1' : '0');
    } catch {
      // ignore
    }
    if (typeof document !== 'undefined') {
      document.body.classList.toggle('sidebar-is-collapsed', collapsed);
    }
  }, [collapsed]);

  const sections = useMemo(() => buildNavSections({ flags, spaPaths, i18n, organizations, org }), [
    flags,
    spaPaths,
    i18n,
    organizations,
    org,
  ]);

  return (
    <>
      <a href={routes.spa} className="admin-brand">
        <span className="admin-brand-mark" aria-hidden />
        <div className="admin-brand-text">
          <span className="app-brand-html d-block" dangerouslySetInnerHTML={{ __html: i18n.brand_html }} />
        </div>
      </a>

      <button
        type="button"
        className="admin-sidebar-toggle"
        onClick={() => setCollapsed((v) => !v)}
        aria-label={collapsed ? 'Déplier le menu' : 'Réduire le menu'}
        title={collapsed ? 'Déplier le menu' : 'Réduire le menu'}
      >
        <i className={`fas ${collapsed ? 'fa-angle-double-right' : 'fa-angle-double-left'}`} aria-hidden="true" />
      </button>

      <div className="sidebar sidebar--stack main-sidebar__charte">
        <nav className="admin-nav sidebar__nav" aria-label="Navigation principale">
          {sections.map((section, si) => (
            <div key={section.sectionLabel ?? `nav-section-${si}`} className="admin-nav-section">
              {section.sectionLabel ? (
                <p className="admin-nav-section-title">{section.sectionLabel}</p>
              ) : null}
              {section.items.map((item) => (
                <NavLink
                  key={item.id}
                  to={item.to}
                  end={item.end ?? false}
                  className={({ isActive }) => {
                    const hasManual = typeof item.isActive === 'function';
                    const manual = hasManual ? item.isActive(location.pathname) : false;
                    const active = hasManual ? manual : isActive;
                    return `admin-nav-item${active ? ' active' : ''}`;
                  }}
                >
                  <i className={`nav-icon fas ${item.icon}`} aria-hidden />
                  <span>{item.label}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>

        {organizations?.length > 1 ? (
          <div className="admin-sidebar-footer">
            <p className="admin-sidebar-footnote mb-0">
              <label className="admin-org-switch">
                <span className="admin-org-label">{i18n.nav_org_switcher}</span>
                <select
                  className="admin-org-select"
                  value={org?.publicToken || ''}
                  onChange={(e) => {
                    const tok = e.target.value;
                    if (!tok) return;
                    window.location.href = routes.orgContextSwitch.replace('__token__', tok);
                  }}
                  aria-label={i18n.nav_org_switcher}
                >
                  {organizations.map((o) => (
                    <option key={o.publicToken} value={o.publicToken}>
                      {o.name}
                    </option>
                  ))}
                </select>
              </label>
            </p>
          </div>
        ) : org ? (
          <div className="admin-sidebar-footer">
            <p className="admin-sidebar-footnote mb-0">
              <span className="admin-org-label">{i18n.nav_current_org}</span>
              <span className="admin-org-name">{org.name}</span>
            </p>
          </div>
        ) : null}
      </div>
    </>
  );
}

/**
 * @param {{
 *   flags: Record<string, boolean>;
 *   spaPaths: Record<string, string | null | undefined>;
 *   i18n: Record<string, string>;
 *   organizations: Array<{ publicToken: string; name: string }> | undefined;
 *   org: { name: string; publicToken: string } | null | undefined;
 * }} p
 */
function buildNavSections({ flags, spaPaths, i18n, organizations, org }) {
  const hasOrgContext = Boolean(org && organizations?.length);

  /** @type {Array<{ sectionLabel: string | null; items: NavItem[] }>} */
  const sections = [];

  const administrationItems = [
    {
      id: 'dashboard',
      to: '/',
      label: i18n.nav_dashboard,
      icon: 'fa-tachometer-alt',
      end: true,
    },
  ];

  if (hasOrgContext) {
    if (flags.canAccessOrganizationBillingPage && spaPaths.organizationBilling) {
      administrationItems.push({
        id: 'orgBilling',
        to: spaPaths.organizationBilling,
        label: i18n.nav_org_billing,
        icon: 'fa-building',
      });
    }
    if (flags.canEditCurrentOrganization && spaPaths.organizationUsers) {
      administrationItems.push({
        id: 'orgUsers',
        to: spaPaths.organizationUsers,
        label: i18n.nav_org_users,
        icon: 'fa-user-group',
      });
    }
    if (flags.canEditCurrentOrganization && spaPaths.organizationClients) {
      administrationItems.push({
        id: 'orgClients',
        to: spaPaths.organizationClients,
        label: i18n.nav_org_clients,
        icon: 'fa-id-card-alt',
      });
    }
  }

  sections.push({
    sectionLabel: i18n.nav_section_admin,
    items: administrationItems,
  });

  const ticketStackItems = [];
  if (hasOrgContext) {
    if (flags.showTicketCreateEntry && spaPaths.organizationTicketNew) {
      ticketStackItems.push({
        id: 'ticketNew',
        to: spaPaths.organizationTicketNew,
        label: i18n.nav_ticket_new,
        icon: 'fa-plus-circle',
        isActive: (pathname) => pathname === '/tickets/new' || pathname === '/tickets/new/',
      });
    }
    if (flags.canEditCurrentOrganization && spaPaths.organizationProjects) {
      ticketStackItems.push({
        id: 'projects',
        to: spaPaths.organizationProjects,
        label: i18n.nav_project,
        icon: 'fa-folder-open',
        isActive: (pathname) =>
          pathname === '/projects' ||
          pathname === '/projects/' ||
          /^\/projects\/[^/]+(\/edit)?\/?$/.test(pathname) ||
          /^\/organization\/[^/]+\/projects\/?$/.test(pathname) ||
          /^\/organization\/[^/]+\/projects\/[^/]+(\/edit)?\/?$/.test(pathname),
      });
    }
    if (spaPaths.organizationTickets) {
      ticketStackItems.push({
        id: 'tickets',
        to: spaPaths.organizationTickets,
        label: i18n.nav_tickets,
        icon: 'fa-ticket-alt',
        // Ne pas activer sur /tickets/new (sinon double sélection).
        isActive: (pathname) =>
          (pathname === '/tickets' ||
            pathname === '/tickets/' ||
            /^\/tickets\/\d+\/?$/.test(pathname)) &&
          !(pathname === '/tickets/new' || pathname === '/tickets/new/'),
      });
    }
  }
  if (ticketStackItems.length > 0) {
    sections.push({
      sectionLabel: i18n.nav_section_tickets,
      items: ticketStackItems,
    });
  }

  if (flags.canViewActivityLog && spaPaths.accountActivity) {
    sections.push({
      sectionLabel: null,
      items: [
        {
          id: 'activity',
          to: spaPaths.accountActivity,
          label: i18n.nav_activity,
          icon: 'fa-clipboard-list',
        },
      ],
    });
  }

  if (flags.isAdmin) {
    sections.push({
      sectionLabel: i18n.nav_admin_header,
      items: [
        {
          id: 'adminOrgs',
          to: spaPaths.adminOrganizations,
          label: i18n.nav_admin_orgs,
          icon: 'fa-sitemap',
        },
        {
          id: 'adminUsers',
          to: spaPaths.adminUsers,
          label: i18n.nav_admin_users,
          icon: 'fa-users',
        },
        {
          id: 'adminAuditActions',
          to: spaPaths.adminAuditActions,
          label: i18n.nav_admin_audit_actions,
          icon: 'fa-history',
        },
        {
          id: 'adminAuditErrors',
          to: spaPaths.adminAuditErrors,
          label: i18n.nav_admin_audit_errors,
          icon: 'fa-bug',
        },
        {
          id: 'adminOptions',
          to: spaPaths.adminOptions,
          label: i18n.nav_admin_options,
          icon: 'fa-sliders-h',
          isActive: (pathname) => pathname === '/admin/options' || pathname === '/admin/options/',
        },
            {
              id: 'adminImapFetchInbox',
              to: spaPaths.adminImapFetchInbox,
              label: i18n.nav_admin_imap_fetch_inbox,
              icon: 'fa-envelope-open-text',
              isActive: (pathname) =>
                pathname === '/admin/imap/fetch-inbox' ||
                pathname === '/admin/imap/fetch-inbox/' ||
                /^\/admin\/imap\/fetch-inbox\/\d+\/?$/.test(pathname),
            },
      ],
    });
  }

  return sections;
}
