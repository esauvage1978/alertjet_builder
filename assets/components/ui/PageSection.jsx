/**
 * En-tête optionnel au-dessus du contenu principal de la page.
 */
export function PageSection({ title, description, actions, children }) {
  return (
    <div>
      {title || description || actions ? (
        <div
          className="mb-3 d-flex flex-wrap align-items-start justify-content-between"
          style={{ gap: '0.75rem' }}
        >
          <div>
            {title ? <h2 className="h4 mb-1 text-dark">{title}</h2> : null}
            {description ? <p className="text-muted small mb-0">{description}</p> : null}
          </div>
          {actions ? <div className="d-flex flex-wrap align-items-center">{actions}</div> : null}
        </div>
      ) : null}
      {children}
    </div>
  );
}
