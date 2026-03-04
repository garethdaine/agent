import { describe, expect, it } from 'vitest';
import { deriveActiveBuildFreshnessView } from '../freshness';

describe('deriveActiveBuildFreshnessView', () => {
    it('marks freshness as stale when age exceeds threshold', () => {
        const view = deriveActiveBuildFreshnessView({
            active_build_age_seconds: 301,
            stale_after_seconds: 300,
        });

        expect(view.isStale).toBe(true);
        expect(view.badgeLabel).toBe('Stale');
        expect(view.tooltip).toContain('older than 300s');
    });

    it('marks freshness as unavailable when no active build exists', () => {
        const view = deriveActiveBuildFreshnessView({
            active_build_age_seconds: null,
            stale_after_seconds: 300,
        });

        expect(view.isStale).toBeNull();
        expect(view.badgeLabel).toBe('No active build');
    });
});
