type FreshnessPayload = {
    active_build_age_seconds: number | null | undefined;
    stale_after_seconds: number | null | undefined;
    active_build_is_stale?: boolean | null | undefined;
};

type FreshnessView = {
    ageSeconds: number | null;
    isStale: boolean | null;
    badgeLabel: string;
    badgeVariant: 'default' | 'secondary' | 'destructive';
    tooltip: string;
};

export function deriveActiveBuildFreshnessView(payload: FreshnessPayload): FreshnessView {
    const ageSeconds = typeof payload.active_build_age_seconds === 'number'
        ? payload.active_build_age_seconds
        : null;
    const staleAfterSeconds = typeof payload.stale_after_seconds === 'number'
        ? payload.stale_after_seconds
        : null;

    if (ageSeconds === null) {
        return {
            ageSeconds: null,
            isStale: null,
            badgeLabel: 'No active build',
            badgeVariant: 'secondary',
            tooltip: 'No active projection build is currently serving reads.',
        };
    }

    const staleByThreshold = staleAfterSeconds !== null ? ageSeconds > staleAfterSeconds : false;
    const isStale = typeof payload.active_build_is_stale === 'boolean'
        ? payload.active_build_is_stale
        : staleByThreshold;

    return {
        ageSeconds,
        isStale,
        badgeLabel: isStale ? 'Stale' : 'Fresh',
        badgeVariant: isStale ? 'destructive' : 'default',
        tooltip: staleAfterSeconds === null
            ? 'Projection freshness threshold is not configured.'
            : isStale
                ? `Projection freshness is stale: older than ${staleAfterSeconds}s threshold.`
                : `Projection freshness is within ${staleAfterSeconds}s threshold.`,
    };
}
