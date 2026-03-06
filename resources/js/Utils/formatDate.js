/**
 * Format an ISO date/timestamp string into a human-readable format.
 *
 * @param {string|null} dateString - ISO 8601 date string
 * @param {object} options - Intl.DateTimeFormat options override
 * @returns {string}
 */
export function formatDateTime(dateString, options = {}) {
    if (!dateString) return '—';

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const defaults = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    };

    return date.toLocaleString(undefined, { ...defaults, ...options });
}

/**
 * Format a date-only value (no time).
 */
export function formatDate(dateString) {
    if (!dateString) return '—';

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Relative time description (e.g. "3m ago", "2h ago").
 */
export function relativeTime(dateString) {
    if (!dateString) return '—';

    const diff = Date.now() - new Date(dateString).getTime();
    if (diff < 0) return 'just now';
    if (diff < 60000) return `${Math.round(diff / 1000)}s ago`;
    if (diff < 3600000) return `${Math.round(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.round(diff / 3600000)}h ago`;
    return `${Math.round(diff / 86400000)}d ago`;
}
