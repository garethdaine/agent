import { mount } from '@vue/test-utils';
import RuntimeIndex from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test' } },
            jetstream: { canManageTwoFactorAuthentication: false },
        },
    }),
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), reload: vi.fn() },
}));

describe('Runtime Index', () => {
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
        const wrapper = mount(RuntimeIndex, {
            props: {
                sessions: { data: [], total: 0, links: [] },
            },
            global: globalOptions,
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('shows empty state when no sessions', () => {
        const wrapper = mount(RuntimeIndex, {
            props: {
                sessions: { data: [], total: 0, links: [] },
            },
            global: globalOptions,
        });
        expect(wrapper.text()).toContain('No runtime sessions yet');
    });
});
