import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { fetchJson } from '../../api/http.js';
import { PageCard } from '../../components/ui/PageCard.jsx';
import { ErrorAlert } from '../../components/ui/ErrorAlert.jsx';
import { LoadingState } from '../../components/ui/LoadingState.jsx';
import { useAsyncResource } from '../../hooks/useAsyncResource.js';
import {
  contrastTextForBackground,
  darkenBorderHex,
  normalizeHex,
} from '../../js/projectAccentColors.js';

const STATUS_LABELS = {
  open: 'Ouvert',
  new: 'Nouveau',
  acknowledged: 'Pris en compte',
  in_progress: 'En cours',
  on_hold: 'En attente',
  resolved: 'Résolu',
  closed: 'Fermé',
  cancelled: 'Annulé',
};

const PRIORITY_LABELS = {
  low: 'Basse',
  medium: 'Moyenne',
  high: 'Haute',
  critical: 'Critique',
};

const TYPE_LABELS = {
  incident: 'Incident',
  problem: 'Problème',
  request: 'Demande',
};

const SOURCE_LABELS = {
  phone: 'Téléphone',
  email: 'E-mail',
  webhook: 'Webhook',
  client_form: 'Formulaire client',
  internal_form: 'Formulaire interne',
};

function label(map, key, fallback = '') {
  if (key == null || key === '') return fallback;
  return map[key] ?? key;
}

function formatDateTime(iso) {
  if (iso == null || iso === '') return '—';
  try {
    return new Date(iso).toLocaleString('fr-FR', {
      dateStyle: 'short',
      timeStyle: 'short',
    });
  } catch {
    return String(iso);
  }
}

function UserChip({ person, emptyLabel }) {
  if (!person) {
    return <span className="tickets-page__person tickets-page__person--empty">{emptyLabel}</span>;
  }
  const bg = person.avatarColor || '#64748b';
  const fg = person.avatarForegroundColor || contrastTextForBackground(normalizeHex(bg) || '#64748b');
  return (
    <span className="tickets-page__person">
      <span
        className="tickets-page__avatar"
        style={{
          backgroundColor: bg,
          color: fg,
        }}
        title={person.label}
      >
        {person.initials}
      </span>
      <span className="tickets-page__person-name">{person.label}</span>
    </span>
  );
}

export default function TicketsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const queryString = searchParams.toString();

  const loadFn = useCallback(async () => {
    const url = `/mon-organisation/tickets${queryString ? `?${queryString}` : ''}`;
    return fetchJson(url);
  }, [queryString]);

  const { data, error, loading, reload } = useAsyncResource(loadFn);

  const qFromUrl = searchParams.get('q') || '';
  const [localQ, setLocalQ] = useState(qFromUrl);

  useEffect(() => {
    setLocalQ(qFromUrl);
  }, [qFromUrl]);

  const pagination = data?.pagination ?? { page: 1, perPage: 15, total: 0, totalPages: 1 };
  const filterOptions = data?.filterOptions ?? { projects: [], assignees: [], perPageChoices: [10, 15, 25, 50] };

  const start = pagination.total === 0 ? 0 : (pagination.page - 1) * pagination.perPage + 1;
  const end = Math.min(pagination.page * pagination.perPage, pagination.total);

  const mergeParams = useCallback(
    (updates, { resetPage = true } = {}) => {
      const next = new URLSearchParams(searchParams);
      Object.entries(updates).forEach(([k, v]) => {
        if (v === '' || v == null) {
          next.delete(k);
        } else {
          next.set(k, String(v));
        }
      });
      if (resetPage) {
        next.set('page', '1');
      }
      setSearchParams(next);
    },
    [searchParams, setSearchParams],
  );

  const goToPage = useCallback(
    (p) => {
      const next = new URLSearchParams(searchParams);
      next.set('page', String(Math.max(1, p)));
      setSearchParams(next);
    },
    [searchParams, setSearchParams],
  );

  const applySearch = useCallback(() => {
    mergeParams({ q: localQ.trim() });
  }, [localQ, mergeParams]);

  const tickets = useMemo(() => (Array.isArray(data?.tickets) ? data.tickets : []), [data?.tickets]);

  if (!data && loading) {
    return <LoadingState />;
  }
  if (!data) {
    return <ErrorAlert message={error || 'Impossible de charger les tickets'} onRetry={reload} />;
  }

  const toolbar = (
    <div className="tickets-page__filters">
      <div className="tickets-page__filters-row tickets-page__filters-row--search">
        <div className="tickets-page__search">
          <input
            type="search"
            className="form-control form-control-sm"
            placeholder="Rechercher (titre, contenu, motifs, e-mail…)…"
            value={localQ}
            onChange={(e) => setLocalQ(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                applySearch();
              }
            }}
            aria-label="Recherche texte"
          />
          <button type="button" className="btn btn-sm btn-primary" onClick={applySearch}>
            Rechercher
          </button>
        </div>
      </div>
      <div className="tickets-page__filters-grid" role="group" aria-label="Filtres">
        <select
          className="form-control form-control-sm tickets-page__filter-select"
          value={searchParams.get('status') || ''}
          onChange={(e) => mergeParams({ status: e.target.value })}
          aria-label="Statut"
        >
          <option value="">Tous les statuts</option>
          {Object.entries(STATUS_LABELS).map(([v, lab]) => (
            <option key={v} value={v}>
              {lab}
            </option>
          ))}
        </select>
        <select
          className="form-control form-control-sm tickets-page__filter-select"
          value={searchParams.get('priority') || ''}
          onChange={(e) => mergeParams({ priority: e.target.value })}
          aria-label="Priorité"
        >
          <option value="">Toutes les priorités</option>
          {Object.entries(PRIORITY_LABELS).map(([v, lab]) => (
            <option key={v} value={v}>
              {lab}
            </option>
          ))}
        </select>
        <select
          className="form-control form-control-sm tickets-page__filter-select"
          value={searchParams.get('project') || ''}
          onChange={(e) => mergeParams({ project: e.target.value })}
          aria-label="Projet"
        >
          <option value="">Tous les projets</option>
          {filterOptions.projects?.map((p) => (
            <option key={p.id} value={String(p.id)}>
              {p.name}
            </option>
          ))}
        </select>
        <select
          className="form-control form-control-sm tickets-page__filter-select"
          value={searchParams.get('source') || ''}
          onChange={(e) => mergeParams({ source: e.target.value })}
          aria-label="Origine"
        >
          <option value="">Toutes les origines</option>
          {Object.entries(SOURCE_LABELS).map(([v, lab]) => (
            <option key={v} value={v}>
              {lab}
            </option>
          ))}
        </select>
        <select
          className="form-control form-control-sm tickets-page__filter-select"
          value={searchParams.get('type') || ''}
          onChange={(e) => mergeParams({ type: e.target.value })}
          aria-label="Type"
        >
          <option value="">Tous les types</option>
          {Object.entries(TYPE_LABELS).map(([v, lab]) => (
            <option key={v} value={v}>
              {lab}
            </option>
          ))}
        </select>
        <select
          className="form-control form-control-sm tickets-page__filter-select"
          value={searchParams.get('assignee') || ''}
          onChange={(e) => mergeParams({ assignee: e.target.value })}
          aria-label="Assignation"
        >
          <option value="">Tous les tickets</option>
          <option value="unassigned">Non assignés</option>
          <option value="me">Assignés à moi</option>
          {filterOptions.assignees?.map((a) => (
            <option key={a.id} value={String(a.id)}>
              {a.label}
            </option>
          ))}
        </select>
        <select
          className="form-control form-control-sm tickets-page__filter-select tickets-page__filter-select--narrow"
          value={String(pagination.perPage)}
          onChange={(e) => mergeParams({ perPage: e.target.value })}
          aria-label="Par page"
        >
          {(filterOptions.perPageChoices || [10, 15, 25, 50]).map((n) => (
            <option key={n} value={String(n)}>
              {n} / page
            </option>
          ))}
        </select>
      </div>
    </div>
  );

  return (
    <div className="tickets-page webhook-projects-page op-project-edit">
      {error ? (
        <div className="mb-3">
          <ErrorAlert message={error} onRetry={reload} />
        </div>
      ) : null}

      <header className="op-project-edit__header mb-3">
        <div className="op-project-edit__header-row">
          <div>
            <h1 className="op-project-edit__title h4 m-0 d-flex align-items-center">
              <i className="fas fa-ticket-alt op-project-edit__title-icon mr-2" aria-hidden="true" />
              Tickets
            </h1>
            <p className="op-project-edit__meta small mb-0 mt-2">
              {data.organization?.name ? <span className="font-weight-bold">{data.organization.name}</span> : null}
              {pagination.total != null ? (
                <span className="text-muted"> · {pagination.total} ticket{pagination.total !== 1 ? 's' : ''}</span>
              ) : null}
            </p>
          </div>
          <Link to="/tickets/new" className="btn btn-sm btn-primary">
            <i className="fas fa-plus mr-1" aria-hidden="true" />
            Nouveau ticket
          </Link>
        </div>
      </header>

      <PageCard toolbar={toolbar} className="tickets-page__card">
        {tickets.length === 0 ? (
          <div className="tickets-page__empty">
            <i className="fas fa-inbox tickets-page__empty-icon" aria-hidden="true" />
            <p className="mb-1 font-weight-bold">Aucun ticket à afficher</p>
            <p className="text-muted small mb-0">
              Ajustez les filtres ou créez un ticket. L’<strong>origine</strong> indique le canal (e-mail, webhook,
              etc.) ; la <strong>prise en charge</strong> correspond au membre assigné sur le ticket.
            </p>
          </div>
        ) : (
          <div className="tickets-page__table-wrap org-table-wrap">
            <div className="table-responsive">
              <table className="table table-borderless org-table tickets-page__table mb-0 w-100">
                <thead className="tickets-page__thead">
                  <tr>
                    <th>Projet</th>
                    <th>Ticket</th>
                    <th className="text-center">Statut</th>
                    <th className="text-center">Priorité</th>
                    <th className="text-center">Type</th>
                    <th>Canal</th>
                    <th>Assigné</th>
                    <th>Créé</th>
                    <th className="text-right" aria-label="Actions" />
                  </tr>
                </thead>
                <tbody>
                  {tickets.map((t) => {
                    const accentBg = normalizeHex(t.project?.accentColor) || '#64748b';
                    const accentFg =
                      normalizeHex(t.project?.accentTextColor) || contrastTextForBackground(accentBg);
                    const accentBd =
                      normalizeHex(t.project?.accentBorderColor) || darkenBorderHex(accentBg);
                    const sourceReadable = t.sourceLabel || label(SOURCE_LABELS, t.source, t.source);

                    return (
                      <tr key={t.id ?? t.publicId} className="tickets-page__row">
                        <td className="tickets-page__cell-project">
                          {t.project ? (
                            <span
                              className="tickets-page__project-pill"
                              style={{
                                backgroundColor: accentBg,
                                color: accentFg,
                                borderColor: accentBd,
                              }}
                              title={t.project.name}
                            >
                              {t.project.name}
                            </span>
                          ) : (
                            <span className="text-muted small">Sans projet</span>
                          )}
                        </td>
                        <td className="tickets-page__cell-title">
                          <div className="tickets-page__title-wrap">
                            <div className="tickets-page__title-line">
                              {t.id != null ? <Link to={`/tickets/${t.id}`}>{t.title}</Link> : t.title}
                              {t.silenced ? (
                                <span className="badge badge-light border ml-2" title="Silencieux">
                                  <i className="fas fa-bell-slash text-muted" aria-hidden="true" />
                                </span>
                              ) : null}
                            </div>
                            <div className="tickets-page__subtitle text-muted small font-monospace">#{t.publicId}</div>
                          </div>
                        </td>
                        <td className="text-center">
                          <span
                            className={`tickets-page__badge tickets-page__badge--status tickets-page__badge--st-${t.status}`}
                          >
                            {label(STATUS_LABELS, t.status, t.status)}
                          </span>
                        </td>
                        <td className="text-center">
                          <span
                            className={`tickets-page__badge tickets-page__badge--priority tickets-page__badge--pr-${t.priority}`}
                          >
                            {label(PRIORITY_LABELS, t.priority, t.priority)}
                          </span>
                        </td>
                        <td className="text-center">
                          <span
                            className={`tickets-page__badge tickets-page__badge--type tickets-page__badge--type-${t.type || 'unknown'}`}
                          >
                            <i
                              className={`fas ${
                                t.type === 'incident'
                                  ? 'fa-exclamation-triangle'
                                  : t.type === 'problem'
                                    ? 'fa-bug'
                                    : t.type === 'request'
                                      ? 'fa-handshake'
                                      : 'fa-tag'
                              } mr-1`}
                              aria-hidden="true"
                            />
                            {label(TYPE_LABELS, t.type, t.type)}
                          </span>
                        </td>
                        <td>
                          <span className="tickets-page__mono">{sourceReadable}</span>
                        </td>
                        <td>
                          <UserChip person={t.assignee} emptyLabel="—" />
                        </td>
                        <td>
                          <span className="tickets-page__mono">{formatDateTime(t.createdAt)}</span>
                        </td>
                        <td className="text-right">
                          {t.id != null ? (
                            <Link to={`/tickets/${t.id}`} className="btn btn-sm btn-primary">
                              Ouvrir
                            </Link>
                          ) : null}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {pagination.total > 0 ? (
          <div className="tickets-page__pagination">
            <p className="tickets-page__range small text-muted mb-0">
              {start}–{end} sur {pagination.total}
            </p>
            <div className="tickets-page__pager">
              <button
                type="button"
                className="btn btn-sm btn-outline-secondary"
                disabled={pagination.page <= 1}
                onClick={() => goToPage(pagination.page - 1)}
              >
                Précédent
              </button>
              <span className="tickets-page__page-indicator small">
                Page {pagination.page} / {pagination.totalPages}
              </span>
              <button
                type="button"
                className="btn btn-sm btn-outline-secondary"
                disabled={pagination.page >= pagination.totalPages}
                onClick={() => goToPage(pagination.page + 1)}
              >
                Suivant
              </button>
            </div>
          </div>
        ) : null}
      </PageCard>
    </div>
  );
}
