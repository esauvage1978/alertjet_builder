import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { fetchJson } from '../api/http.js';

const BootstrapCtx = createContext(null);

export function BootstrapProvider({ children }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [reloadKey, setReloadKey] = useState(0);

  const reload = useCallback(() => {
    setData(null);
    setError(null);
    setReloadKey((k) => k + 1);
  }, []);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const json = await fetchJson('/api/ui/bootstrap');
        if (cancelled) return;
        if (json == null) {
          setError('Réponse invalide (session ou redirection). Rechargez la page.');
          return;
        }
        setData(json);
      } catch (e) {
        if (!cancelled) setError(e.message || 'Bootstrap');
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [reloadKey]);

  const value = useMemo(() => ({ data, error, reload }), [data, error, reload]);

  if (error) {
    return (
      <div className="p-5 text-center text-danger">
        <p>Impossible de charger la session ({error}).</p>
        <a href="/app/login">Connexion</a>
      </div>
    );
  }
  if (!data) {
    return (
      <div className="p-5 text-center text-muted">
        <i className="fas fa-circle-notch fa-spin mr-2" />
        Chargement…
      </div>
    );
  }

  return <BootstrapCtx.Provider value={value}>{children}</BootstrapCtx.Provider>;
}

export function useBootstrap() {
  const v = useContext(BootstrapCtx);
  if (!v) throw new Error('useBootstrap');
  return v;
}
