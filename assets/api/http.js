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

  /*
   * redirect: 'manual' provoque souvent status 0 / type opaqueredirect après POST→302 (réponse illisible).
   * 'follow' suit la chaîne jusqu’au GET final (PRG Symfony) ; on navigue vers res.url.
   */
  let res;
  try {
    res = await fetch(targetUrl, {
      method: 'POST',
      credentials: 'include',
      redirect: 'follow',
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

  console.info(debugTag, 'réponse', {
    status: res.status,
    ok: res.ok,
    type: res.type,
    redirected: res.redirected,
    finalUrl: res.url,
  });

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
    return {
      error: 'validation_failed',
      message: 'Réponse 422 non JSON (voir console).',
      formErrors: [],
      fieldErrors: {},
      rawPreview: fallbackText.slice(0, 300),
    };
  }

  if (res.status === 0 || res.type === 'opaque' || res.type === 'opaqueredirect') {
    console.error(debugTag, 'réponse encore opaque — anomalie réseau ou politique du navigateur', res.type);
    return {
      error: 'opaque_response',
      message:
        'Réponse HTTP illisible (status 0). Réessayez ou désactivez les extensions qui bloquent les requêtes.',
    };
  }

  if (res.ok) {
    const keys = options.clearSessionKeysOnRedirect;
    if (Array.isArray(keys) && keys.length > 0) {
      try {
        keys.forEach((k) => sessionStorage.removeItem(k));
      } catch (_) {
        /* ignore */
      }
    }

    let startPath = '';
    let endPath = '';
    try {
      startPath = new URL(targetUrl).pathname;
      endPath = new URL(res.url).pathname;
    } catch (_) {
      /* ignore */
    }

    if (res.redirected || startPath !== endPath) {
      console.info(debugTag, 'PRG / redirection suivie →', res.url);
      window.location.href = res.url;
      return { ok: true, redirectedTo: res.url };
    }

    console.warn(debugTag, '200 sur même chemin que le POST — reload');
    window.location.reload();
    return { ok: true, reloaded: true };
  }

  const textPreview = await res
    .text()
    .then((t) => t.slice(0, 600))
    .catch(() => '');

  console.error(debugTag, 'Réponse non gérée', {
    status: res.status,
    contentType: res.headers.get('Content-Type'),
    bodyPreview: textPreview,
  });

  const fallback = {
    error: 'unexpected_response',
    status: res.status,
    statusText: res.statusText,
    message: `Réponse HTTP ${res.status}. Voir la console (${debugTag}).`,
    bodyPreview: textPreview,
  };

  if (preferJsonErrors) {
    return fallback;
  }
  console.warn(debugTag, 'reload (fallback legacy, preferJsonErrors désactivé)');
  window.location.reload();
  return fallback;
}
