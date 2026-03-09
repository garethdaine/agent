import { mount } from '@vue/test-utils';
import Welcome from './Welcome.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test' } },
        },
    }),
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn() },
}));

// IntersectionObserver is not available in jsdom
vi.stubGlobal('IntersectionObserver', class {
    observe() {}
    unobserve() {}
    disconnect() {}
});

describe('Welcome', () => {
    it('renders without error', () => {
        const wrapper = mount(Welcome, {
            props: {
                canLogin: true,
                canRegister: true,
                laravelVersion: '12.0.0',
                phpVersion: '8.3.0',
            },
            global: {
                mocks: {
                    route: (name: string) => `/${name}`,
                    $page: {
                        props: {
                            auth: { user: { name: 'Test' } },
                        },
                    },
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
