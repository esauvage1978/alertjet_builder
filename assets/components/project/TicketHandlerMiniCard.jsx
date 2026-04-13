import { UserAvatar } from '../ui/UserAvatar.jsx';

const FALLBACK_BG = '#475569';
const FALLBACK_FG = '#f8fafc';

/**
 * Carte cliquable : initiales, nom, e-mail — sélection visuelle (bordure corail).
 */
export function TicketHandlerMiniCard({
  displayName,
  email,
  initials,
  avatarColor,
  avatarForegroundColor,
  selected,
  disabled,
  onToggle,
}) {
  return (
    <button
      type="button"
      className={`ticket-handler-mini-card${selected ? ' ticket-handler-mini-card--selected' : ''}`}
      onClick={() => !disabled && onToggle()}
      disabled={disabled}
      aria-pressed={selected}
    >
      {selected ? (
        <span className="ticket-handler-mini-card__tick" aria-hidden="true">
          <i className="fas fa-check" />
        </span>
      ) : null}
      <UserAvatar
        initials={initials}
        bg={avatarColor || FALLBACK_BG}
        fg={avatarForegroundColor || FALLBACK_FG}
        className="navbar-avatar navbar-avatar--xl ticket-handler-mini-card__avatar"
      />
      <div className="ticket-handler-mini-card__body">
        <span className="ticket-handler-mini-card__name">{displayName}</span>
        <span className="ticket-handler-mini-card__email" title={email}>
          {email}
        </span>
      </div>
    </button>
  );
}
