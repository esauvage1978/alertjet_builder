import { Link } from 'react-router-dom';

/** Placeholder pour écrans encore servis en JSON/Twig côté Symfony. */
export default function StubPage({ title, hint }) {
  return (
    <div className="alert alert-secondary" role="status">
      <h2 className="h5">{title}</h2>
      <p className="mb-2">
        {hint || 'Données disponibles via l’API JSON (Accept: application/json) — interface React à finaliser.'}
      </p>
      <Link to="/" className="btn btn-sm btn-outline-primary">
        Tableau de bord
      </Link>
    </div>
  );
}
