import { describe, expect, it } from 'vitest';
import { mergeEventsInSequence, nextEventCursor } from '../eventStream';

describe('mergeEventsInSequence', () => {
    it('deduplicates by sequence and returns strictly ordered events', () => {
        const existing = [
            { sequence: 1, event_type: 'phase_transition' },
            { sequence: 3, event_type: 'task_started' },
        ];
        const incoming = [
            { sequence: 2, event_type: 'task_queued' },
            { sequence: 3, event_type: 'task_started_latest' },
            { sequence: 4, event_type: 'task_completed' },
        ];

        const merged = mergeEventsInSequence(existing, incoming);

        expect(merged.map((event) => event.sequence)).toEqual([1, 2, 3, 4]);
        expect(merged[2].event_type).toBe('task_started_latest');
    });
});

describe('nextEventCursor', () => {
    it('returns the greatest sequence from merged events', () => {
        const events = [
            { sequence: 2, event_type: 'snapshot_progress' },
            { sequence: 7, event_type: 'coverage_gate' },
            { sequence: 5, event_type: 'task_failed' },
        ];

        expect(nextEventCursor(events)).toBe(7);
    });

    it('returns zero when no events are available', () => {
        expect(nextEventCursor([])).toBe(0);
    });
});
