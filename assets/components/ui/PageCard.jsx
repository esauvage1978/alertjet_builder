/**
 * Carte de page homogène (équivalent visuel aux listes membres / projets).
 */
export function PageCard({ toolbar, children, className = '' }) {
  return (
    <div className={`card ou-members-card shadow-sm border-0 ${className}`.trim()}>
      {toolbar ? <div className="ou-card-filter border-bottom px-3 py-2">{toolbar}</div> : null}
      {children}
    </div>
  );
}
