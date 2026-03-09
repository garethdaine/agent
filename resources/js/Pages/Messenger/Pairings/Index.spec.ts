import { mount } from '@vue/test-utils';
import PairingsIndex from './Index.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [] } }),
        post: vi.fn().mockResolvedValue({ data: {} }),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test' } },
            jetstream: { canManageTwoFactorAuthentication: false },
        },
    }),
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn() },
}));

describe('Pairings Index', () => {
    const globalOptions = {
        mocks: {
            route: (name: string) => `/${name}`,
        },
        stubs: {
            AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
            HelpHint: true,
        },
    };

    it('renders without error', () => {
        const wrapper = mount(PairingsIndex, { global: globalOptions });
        expect(wrapper.exists()).toBe(true);
    });

    it('shows Identity Pairings heading', () => {
        const wrapper = mount(PairingsIndex, { global: globalOptions });
        expect(wrapper.text()).toContain('Pairings');
    });
});
