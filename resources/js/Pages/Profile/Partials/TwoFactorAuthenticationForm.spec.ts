import { mount } from '@vue/test-utils';
import TwoFactorAuthenticationForm from './TwoFactorAuthenticationForm.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { svg: '<svg></svg>' } }),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test', two_factor_enabled: false } },
            jetstream: { canManageTwoFactorAuthentication: true },
        },
    }),
    useForm: (data: Record<string, unknown>) => ({
        ...data,
        reset: vi.fn(),
        clearErrors: vi.fn(),
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }),
    router: { post: vi.fn(), delete: vi.fn() },
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
}));

describe('TwoFactorAuthenticationForm', () => {
    it('renders without error', () => {
        const wrapper = mount(TwoFactorAuthenticationForm, {
            props: {
                requiresConfirmation: false,
            },
            global: {
                mocks: {
                    route: (name: string) => `/${name}`,
                },
                stubs: {
                    ActionSection: { template: '<div><slot /><slot name="title" /><slot name="description" /><slot name="content" /><slot name="actions" /></div>' },
                    ConfirmsPassword: { template: '<div><slot /></div>' },
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
