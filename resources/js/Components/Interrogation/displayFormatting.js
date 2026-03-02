export const normalizeDisplayKey = (value) => {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, '_');
};

export const humanizeDisplayValue = (value, fallback = '') => {
    const normalized = String(value ?? '')
        .trim()
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .toLowerCase()
        .trim();

    if (normalized === '') {
        return fallback;
    }

    return normalized
        .split(' ')
        .map((segment) => (segment === '' ? '' : `${segment[0].toUpperCase()}${segment.slice(1)}`))
        .join(' ');
};

export const deriveInterrogationStatusKey = ({ status = '', buildStatus = '' } = {}) => {
    const normalizedStatus = normalizeDisplayKey(status);
    const normalizedBuildStatus = normalizeDisplayKey(buildStatus);

    if (normalizedStatus === 'build_rules' && normalizedBuildStatus === 'generating_tasks') {
        return 'generating_tasks';
    }

    return normalizedStatus;
};
