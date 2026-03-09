import { render, screen } from '@testing-library/vue';
import QaHistoryPanel from './QaHistoryPanel.vue';

describe('QaHistoryPanel', () => {
    it('renders without error', () => {
        render(QaHistoryPanel, { props: { events: [] } });
    });

    it('shows empty state when no events', () => {
        render(QaHistoryPanel, { props: { events: [] } });
        expect(screen.getByText('No Q&A history yet.')).toBeTruthy();
    });

    it('shows unanswered count', () => {
        render(QaHistoryPanel, { props: { events: [] } });
        expect(screen.getByText('0 unanswered')).toBeTruthy();
    });
});
