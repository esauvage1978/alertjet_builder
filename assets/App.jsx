import { Suspense, lazy } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { BootstrapProvider, useBootstrap } from './context/BootstrapContext.jsx';
import AppShell from './components/shell/AppShell.jsx';
import AuthLayout from './components/auth/AuthLayout.jsx';
import { LoadingState } from './components/ui/LoadingState.jsx';
import LoginPage from './pages/auth/LoginPage.jsx';
import RegisterPage from './pages/auth/RegisterPage.jsx';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage.jsx';
import ResetPasswordPage from './pages/auth/ResetPasswordPage.jsx';
import AcceptInvitationPage from './pages/auth/AcceptInvitationPage.jsx';
import SetupOrganizationPage from './pages/setup/SetupOrganizationPage.jsx';
import SetupPlanPage from './pages/setup/SetupPlanPage.jsx';
import SetupProfilePage from './pages/setup/SetupProfilePage.jsx';
import SetupProjectPage from './pages/setup/SetupProjectPage.jsx';
import ProfileOnboardingPage from './pages/account/ProfileOnboardingPage.jsx';
import AdminOrganizationsPage from './pages/admin/AdminOrganizationsPage.jsx';
import AdminUsersPage from './pages/admin/AdminUsersPage.jsx';
import AdminAuditActionsPage from './pages/admin/AdminAuditActionsPage.jsx';
import AdminAuditErrorsPage from './pages/admin/AdminAuditErrorsPage.jsx';
import AdminAuditErrorDetailPage from './pages/admin/AdminAuditErrorDetailPage.jsx';

const DashboardPage = lazy(() => import('./pages/dashboard/DashboardPage.jsx'));
const OrganizationUsersPage = lazy(() => import('./pages/organization/OrganizationUsersPage.jsx'));
const ProjectsPage = lazy(() => import('./pages/organization/ProjectsPage.jsx'));
const ProjectEditPage = lazy(() => import('./pages/organization/ProjectEditPage.jsx'));
const ProjectViewPage = lazy(() => import('./pages/organization/ProjectViewPage.jsx'));
const TicketsPage = lazy(() => import('./pages/organization/TicketsPage.jsx'));
const TicketCreatePage = lazy(() => import('./pages/organization/TicketCreatePage.jsx'));
const OrganizationClientsPage = lazy(() => import('./pages/organization/OrganizationClientsPage.jsx'));
const ActivityPage = lazy(() => import('./pages/account/ActivityPage.jsx'));
const StubPage = lazy(() => import('./pages/account/StubPage.jsx'));

function RequireUser() {
  const { data } = useBootstrap();
  if (data.guest) {
    return <Navigate to="/login" replace />;
  }
  return <Outlet />;
}

function GuestAuthGuard() {
  const { data } = useBootstrap();
  if (!data.guest) {
    return <Navigate to="/" replace />;
  }
  return <Outlet />;
}

function WildcardRedirect() {
  const { data } = useBootstrap();
  return <Navigate to={data.guest ? '/login' : '/'} replace />;
}

export default function App() {
  return (
    <BrowserRouter basename="/app">
      <BootstrapProvider>
        <Routes>
          <Route element={<AuthLayout />}>
            <Route element={<GuestAuthGuard />}>
              <Route path="login" element={<LoginPage />} />
              <Route path="inscription" element={<RegisterPage />} />
              <Route path="mot-de-passe-oublie" element={<ForgotPasswordPage />} />
              <Route path="reinitialiser-mot-de-passe/:token" element={<ResetPasswordPage />} />
              <Route path="invitation/:token" element={<AcceptInvitationPage />} />
            </Route>
          </Route>

          <Route element={<RequireUser />}>
            <Route element={<AppShell />}>
              <Route
                path="/"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <DashboardPage />
                  </Suspense>
                }
              />
              <Route
                path="organization/users"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <OrganizationUsersPage />
                  </Suspense>
                }
              />
              <Route
                path="organization/billing"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <StubPage title="Organisation & facturation" />
                  </Suspense>
                }
              />
              <Route
                path="tickets/new"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <TicketCreatePage />
                  </Suspense>
                }
              />
              <Route
                path="tickets"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <TicketsPage />
                  </Suspense>
                }
              />
              <Route
                path="organization/clients"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <OrganizationClientsPage />
                  </Suspense>
                }
              />
              <Route
                path="organization/tickets"
                element={<Navigate to="/tickets" replace />}
              />
              <Route
                path="organization/:orgToken/projects"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ProjectsPage />
                  </Suspense>
                }
              />
              {/* URL courte (Symfony / bootstrap → /app/projects) — même page, org. depuis le contexte */}
              <Route
                path="projects/:projectId/edit"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ProjectEditPage />
                  </Suspense>
                }
              />
              <Route
                path="projects/:projectId"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ProjectViewPage />
                  </Suspense>
                }
              />
              <Route
                path="projects"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ProjectsPage />
                  </Suspense>
                }
              />
              <Route
                path="organization/:orgToken/projects/:projectId/edit"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ProjectEditPage />
                  </Suspense>
                }
              />
              <Route
                path="organization/:orgToken/projects/:projectId"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ProjectViewPage />
                  </Suspense>
                }
              />
              <Route
                path="account/profile"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <StubPage title="Profil" />
                  </Suspense>
                }
              />
              <Route
                path="account/activity"
                element={
                  <Suspense fallback={<LoadingState message="Chargement de la page…" />}>
                    <ActivityPage />
                  </Suspense>
                }
              />
              <Route path="initialisation/organisation" element={<SetupOrganizationPage />} />
              <Route path="initialisation/plan" element={<SetupPlanPage />} />
              <Route path="initialisation/profil" element={<SetupProfilePage />} />
              <Route path="initialisation/projet" element={<SetupProjectPage />} />
              <Route path="compte/finaliser-profil" element={<ProfileOnboardingPage />} />
              <Route path="admin/organisations" element={<AdminOrganizationsPage />} />
              <Route path="admin/utilisateurs" element={<AdminUsersPage />} />
              <Route path="admin/audit/actions" element={<AdminAuditActionsPage />} />
              <Route path="admin/audit/erreurs" element={<AdminAuditErrorsPage />} />
              <Route path="admin/audit/erreurs/:id" element={<AdminAuditErrorDetailPage />} />
            </Route>
          </Route>

          <Route path="*" element={<WildcardRedirect />} />
        </Routes>
      </BootstrapProvider>
    </BrowserRouter>
  );
}
