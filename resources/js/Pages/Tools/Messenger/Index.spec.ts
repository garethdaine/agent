import { mount } from '@vue/test-utils';
import MessengerIndex from './Index.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: { status: 'unknown', connectors: [], queue: { backlog_size: 0 } } } }),
        post: vi.fn().mockResolvedValue({ data: {} }),
        patch: vi.fn().mockResolvedValue({ data: {} }),
        delete: vi.fn().mockResolvedValue({ data: {} }),
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

describe('Messenger Index', () => {
    it('renders without error', () => {
        const wrapper = mount(MessengerIndex, {
            global: {
                mocks: {
                    route: (name: string) => `/${name}`,
                },
                stubs: {
                    AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
                    HelpHint: true,
                    MarkdownRenderer: true,
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
