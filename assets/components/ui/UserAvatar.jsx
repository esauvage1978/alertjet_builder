export function UserAvatar({
  initials,
  backgroundCssVar = '--avatar-bg',
  foregroundCssVar = '--avatar-fg',
  bg,
  fg,
  className = 'navbar-avatar',
}) {
  const style = {
    [backgroundCssVar]: bg,
    [foregroundCssVar]: fg,
  };
  return (
    <span className={className} style={style}>
      {initials}
    </span>
  );
}
