import { render, screen } from '@testing-library/vue';
import TermsOfService from './TermsOfService.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
}));

describe('TermsOfService', () => {
    it('renders without error', () => {
        render(TermsOfService, { props: { terms: '<p>Terms of service.</p>' } });
    });

    it('renders terms HTML content', () => {
        render(TermsOfService, { props: { terms: '<p>You agree to our terms.</p>' } });
        expect(screen.getByText('You agree to our terms.')).toBeTruthy();
    });
});
