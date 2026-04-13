import { Link } from 'react-router-dom';

/** Lien « retour » vers une étape précédente du parcours (/app/…). */
export default function SetupWizardBack({ to, children }) {
  return (
    <div className="mb-3">
      <Link to={to} className="btn btn-outline-secondary btn-sm">
        ← {children}
      </Link>
    </div>
  );
}
