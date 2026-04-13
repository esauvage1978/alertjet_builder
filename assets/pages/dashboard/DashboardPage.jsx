import { Link } from 'react-router-dom';
import { useBootstrap } from '../../context/BootstrapContext.jsx';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { PageSection } from '../../components/ui/PageSection.jsx';

export default function DashboardPage() {
  const { data } = useBootstrap();
  const { spaPaths, user, flags } = data;

  return (
    <PageSection
      title={`Bonjour ${user.displayName || user.email}`}
      description="Tableau de bord — interface React (/app)."
    >
      <PageCard>
        <div className="card-body">
          {flags.showMainNavDestinations ? (
            <ul className="list-unstyled mb-0">
              {spaPaths.organizationUsers ? (
                <li className="mb-2">
                  <Link to={spaPaths.organizationUsers}>Membres de l’organisation</Link>
                </li>
              ) : null}
              {spaPaths.organizationProjects ? (
                <li className="mb-2">
                  <Link to={spaPaths.organizationProjects}>Projets</Link>
                </li>
              ) : null}
              {spaPaths.organizationTickets ? (
                <li className="mb-2">
                  <Link to={spaPaths.organizationTickets}>Tickets</Link>
                </li>
              ) : null}
            </ul>
          ) : (
            <p className="text-muted small mb-0">Complétez la configuration de votre compte pour accéder aux raccourcis.</p>
          )}
        </div>
      </PageCard>
    </PageSection>
  );
}
