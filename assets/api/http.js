export async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });
  if (!res.ok) {
    const t = await res.text();
    throw new Error(t || res.statusText || String(res.status));
  }
  const ct = res.headers.get('Content-Type') || '';
  if (!ct.includes('application/json')) {
    return null;
  }
  return res.json();
}

/**
 * POST application/x-www-form-urlencoded (formulaires Symfony).
 * @param {string} url
 * @param {Record<string, string|number|undefined|null>} fields
 * @param {{ redirect?: RequestRedirect, headers?: Record<string, string> }} [options]
 */
export async function postForm(url, fields, options = {}) {
  const body = new URLSearchParams();
  Object.entries(fields).forEach(([k, v]) => {
    if (v != null && v !== '') body.set(k, String(v));
  });
  return fetch(url, {
    method: 'POST',
    credentials: 'include',
    cache: 'no-store',
    redirect: options.redirect ?? 'manual',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Accept: 'application/json',
      ...options.headers,
    },
    body: body.toString(),
  });
}

export function urlWithCurrentSearch(path) {
  const qs = window.location.search;
  return qs ? `${path}${qs}` : path;
}

/**
 * POST formulaire HTML Symfony ; suit une redirection 302/303 vers la même origine.
 * @param {string} url
 * @param {Record<string, string|number|undefined|null>} fields
 * @param {{ preferJsonErrors?: boolean, sendEmpty?: boolean, clearSessionKeysOnRedirect?: string[] }} [options]
 * @returns {Promise<void|Record<string, unknown>>} Avec `preferJsonErrors`, une réponse 422 JSON est retournée (sans recharger la page).
 */
export async function postFormRedirect(url, fields, options = {}) {
  const preferJsonErrors = options.preferJsonErrors === true;
  const sendEmpty = options.sendEmpty === true;
  const body = new URLSearchParams();
  Object.entries(fields).forEach(([k, v]) => {
    if (sendEmpty) {
      // Toujours envoyer la clé (ex. organization[billingLine2]=) pour que PHP / Symfony
      // reçoivent bien une chaîne vide et puissent effacer les champs optionnels.
      body.set(k, v == null ? '' : String(v));
    } else if (v != null && v !== '') {
      body.set(k, String(v));
    }
  });
  const headers = {
    'Content-Type': 'application/x-www-form-urlencoded',
    Accept: preferJsonErrors ? 'application/json' : 'text/html,application/json',
  };
  if (preferJsonErrors) {
    headers['X-Requested-With'] = 'XMLHttpRequest';
  }
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    redirect: 'manual',
    headers,
    body: body.toString(),
  });
  if (preferJsonErrors && res.status === 422) {
    const ct = res.headers.get('Content-Type') || '';
    if (ct.includes('application/json')) {
      try {
        return await res.json();
      } catch {
        return { error: 'validation_failed', message: res.statusText, fieldErrors: {}, formErrors: [] };
      }
    }
  }
  const redirectStatuses = new Set([301, 302, 303, 307, 308]);
  if (redirectStatuses.has(res.status)) {
    const loc = res.headers.get('Location');
    if (loc) {
      const keys = options.clearSessionKeysOnRedirect;
      if (Array.isArray(keys) && keys.length > 0) {
        try {
          keys.forEach((k) => sessionStorage.removeItem(k));
        } catch (_) {
          /* ignore */
        }
      }
      window.location.href = new URL(loc, window.location.origin).href;
      return;
    }
  }
  window.location.reload();
}
