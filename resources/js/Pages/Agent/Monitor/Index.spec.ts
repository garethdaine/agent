import { mount } from '@vue/test-utils';
import MonitorIndex from './Index.vue';

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
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), reload: vi.fn() },
}));

describe('Monitor Index', () => {
    it('renders without error', () => {
        const wrapper = mount(MonitorIndex, {
            global: {
                mocks: {
                    route: (name: string, params?: Record<string, unknown>) => `/${name}`,
                },
                stubs: {
                    AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
                    ConfirmationModal: true,
                    HelpHint: true,
                    MarkdownRenderer: true,
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
