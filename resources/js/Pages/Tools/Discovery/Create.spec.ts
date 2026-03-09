import { mount } from '@vue/test-utils';
import DiscoveryCreate from './Create.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [], default: '' } }),
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

describe('Discovery Create', () => {
    it('renders without error', () => {
        const wrapper = mount(DiscoveryCreate, {
            global: {
                mocks: {
                    route: (name: string, params?: Record<string, unknown>) => `/${name}`,
                },
                stubs: {
                    AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
                    HelpHint: true,
                    DirectoryPickerInput: true,
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
