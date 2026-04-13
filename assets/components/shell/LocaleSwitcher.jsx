export function LocaleSwitcher({ routes, i18n }) {
  const fr = routes.localeSwitch.replace('__locale__', 'fr');
  const en = routes.localeSwitch.replace('__locale__', 'en');
  return (
    <div className="nav-item d-flex align-items-center mr-2">
      <a href={fr} className="btn btn-sm btn-outline-secondary mr-1">
        {i18n.locale_fr}
      </a>
      <a href={en} className="btn btn-sm btn-outline-secondary">
        {i18n.locale_en}
      </a>
    </div>
  );
}
