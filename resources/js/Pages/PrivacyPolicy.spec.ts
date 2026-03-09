import { render, screen } from '@testing-library/vue';
import PrivacyPolicy from './PrivacyPolicy.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
}));

describe('PrivacyPolicy', () => {
    it('renders without error', () => {
        render(PrivacyPolicy, { props: { policy: '<p>Our privacy policy.</p>' } });
    });

    it('renders policy HTML content', () => {
        render(PrivacyPolicy, { props: { policy: '<p>We respect your privacy.</p>' } });
        expect(screen.getByText('We respect your privacy.')).toBeTruthy();
    });
});
