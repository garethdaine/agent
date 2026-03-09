import { mount } from '@vue/test-utils';
import TunnelStatusCard from './TunnelStatusCard.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({
            data: { status: 'active', hostname: 'test.example.com', uptime_seconds: 3600, connections: 2 },
        }),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    router: { post: vi.fn() },
}));

describe('TunnelStatusCard', () => {
    it('renders without error', () => {
        const wrapper = mount(TunnelStatusCard, {
            global: {
                mocks: {
                    route: (name: string) => `/${name}`,
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('shows Tunnel title', () => {
        const wrapper = mount(TunnelStatusCard, {
            global: {
                mocks: {
                    route: (name: string) => `/${name}`,
                },
            },
        });
        expect(wrapper.text()).toContain('Tunnel');
    });
});
