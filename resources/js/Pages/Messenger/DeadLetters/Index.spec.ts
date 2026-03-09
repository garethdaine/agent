import { mount } from '@vue/test-utils';
import DeadLettersIndex from './Index.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: {} }),
        post: vi.fn().mockResolvedValue({ data: [] }),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test' } },
            flash: {},
            jetstream: { canManageTwoFactorAuthentication: false },
        },
    }),
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), delete: vi.fn(), reload: vi.fn() },
}));

describe('DeadLetters Index', () => {
    const globalOptions = {
        mocks: {
            route: (name: string, params?: Record<string, unknown>) => `/${name}`,
            $page: {
                props: {
                    flash: {},
                },
            },
        },
        stubs: {
            AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
            HelpHint: true,
        },
    };

    it('renders without error', () => {
        const wrapper = mount(DeadLettersIndex, {
            props: {
                deadLetters: { data: [], total: 0, links: [] },
                connectors: [],
                currentConnector: '',
            },
            global: globalOptions,
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('shows empty state message', () => {
        const wrapper = mount(DeadLettersIndex, {
            props: {
                deadLetters: { data: [], total: 0, links: [] },
                connectors: [],
                currentConnector: '',
            },
            global: globalOptions,
        });
        expect(wrapper.text()).toContain('No failed messages found');
    });
});
