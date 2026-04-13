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
 * URL absolue même origine que la page — évite le blocage « mixed content » si l’API
 * renvoie http://… alors que la page est en https:// (Symfony derrière proxy, etc.).
 */
export function resolveSameOriginActionUrl(actionUrl) {
  if (actionUrl == null || actionUrl === '') {
    console.error('[AlertJet http] resolveSameOriginActionUrl: URL vide');
    return '';
  }
  try {
    const u = new URL(String(actionUrl), window.location.origin);
    u.protocol = window.location.protocol;
    u.host = window.location.host;
    return u.href;
  } catch (e) {
    console.error('[AlertJet http] resolveSameOriginActionUrl:', actionUrl, e);
    return '';
  }
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
  const targetUrl = resolveSameOriginActionUrl(url);

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

  const debugTag = '[AlertJet postFormRedirect]';

  if (!targetUrl) {
    const err = { error: 'bad_action_url', message: 'URL d’action invalide ou vide.' };
    console.error(debugTag, err, { urlReçue: url });
    return err;
  }

  console.info(debugTag, 'POST', targetUrl, { keys: Object.keys(fields), sendEmpty });

  let res;
  try {
    res = await fetch(targetUrl, {
      method: 'POST',
      credentials: 'include',
      redirect: 'manual',
      headers,
      body: body.toString(),
    });
  } catch (networkErr) {
    console.error(debugTag, 'fetch a échoué (réseau / mixed content / CORS)', networkErr);
    return {
      error: 'network',
      message: networkErr?.message || String(networkErr),
      details: String(networkErr),
    };
  }

  console.info(debugTag, 'réponse HTTP', res.status, res.statusText, 'Location:', res.headers.get('Location'));

  if (preferJsonErrors && res.status === 422) {
    const ct = res.headers.get('Content-Type') || '';
    if (ct.includes('application/json')) {
      try {
        const json = await res.json();
        console.info(debugTag, '422 JSON', json);
        return json;
      } catch (parseErr) {
        console.error(debugTag, '422 mais JSON illisible', parseErr);
        return { error: 'validation_failed', message: res.statusText, fieldErrors: {}, formErrors: [] };
      }
    }
    const fallbackText = await res.text().catch(() => '');
    console.warn(debugTag, '422 sans JSON', fallbackText.slice(0, 500));
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
      const nextHref = new URL(loc, window.location.origin).href;
      console.info(debugTag, 'redirection →', nextHref);
      window.location.href = nextHref;
      return { ok: true, redirectedTo: nextHref };
    }
    console.warn(debugTag, 'redirection sans en-tête Location', res.status);
  }

  const textPreview = await res
    .clone()
    .text()
    .then((t) => t.slice(0, 600))
    .catch(() => '');

  console.error(debugTag, 'Réponse non gérée — pas de redirect utilisable', {
    status: res.status,
    contentType: res.headers.get('Content-Type'),
    bodyPreview: textPreview,
  });

  return {
    error: 'unexpected_response',
    status: res.status,
    statusText: res.statusText,
    message: `Réponse HTTP ${res.status}. Voir la console (${debugTag}).`,
    bodyPreview: textPreview,
  };
}
