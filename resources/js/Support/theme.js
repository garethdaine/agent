export const THEME_PREFERENCE_KEY = 'agent-theme-preference';

const VALID_THEME_PREFERENCES = new Set(['light', 'dark', 'system']);

const canUseDom = () => typeof window !== 'undefined' && typeof document !== 'undefined';

export const getStoredThemePreference = () => {
    if (!canUseDom()) {
        return 'system';
    }

    try {
        const stored = window.localStorage.getItem(THEME_PREFERENCE_KEY);
        return VALID_THEME_PREFERENCES.has(stored) ? stored : 'system';
    } catch {
        return 'system';
    }
};

export const setStoredThemePreference = (preference) => {
    if (!canUseDom()) {
        return;
    }

    const safePreference = VALID_THEME_PREFERENCES.has(preference) ? preference : 'system';

    try {
        window.localStorage.setItem(THEME_PREFERENCE_KEY, safePreference);
    } catch {
        // Ignore storage failures and continue with in-memory behavior.
    }
};

export const resolveTheme = (preference = 'system') => {
    if (preference === 'dark') {
        return 'dark';
    }

    if (preference === 'light') {
        return 'light';
    }

    if (canUseDom() && typeof window.matchMedia === 'function') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    return 'light';
};

export const applyThemePreference = (preference = 'system') => {
    if (!canUseDom()) {
        return 'light';
    }

    const safePreference = VALID_THEME_PREFERENCES.has(preference) ? preference : 'system';
    const resolvedTheme = resolveTheme(safePreference);
    const root = document.documentElement;

    root.classList.toggle('dark', resolvedTheme === 'dark');
    root.style.colorScheme = resolvedTheme;
    root.dataset.themePreference = safePreference;

    return resolvedTheme;
};

export const initializeTheme = () => {
    const preference = getStoredThemePreference();

    return {
        preference,
        resolvedTheme: applyThemePreference(preference),
    };
};
