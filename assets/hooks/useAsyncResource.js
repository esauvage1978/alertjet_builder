import { useCallback, useEffect, useState } from 'react';

/**
 * Charge une ressource JSON une fois au montage puis expose reload().
 * @param {() => Promise<any>} loadFn — doit être mémorisé (useCallback) si elle dépend de props/params.
 */
export function useAsyncResource(loadFn) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  const reload = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      const json = await loadFn();
      setData(json);
    } catch (e) {
      setError(e.message || 'Erreur');
    } finally {
      setLoading(false);
    }
  }, [loadFn]);

  useEffect(() => {
    reload();
  }, [reload]);

  return { data, error, loading, reload, setData, setError };
}
