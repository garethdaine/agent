import { mount } from '@vue/test-utils';
import Dashboard from './Dashboard.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { metrics: {}, window: '24h' } }),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test' } },
            jetstream: { canManageTwoFactorAuthentication: false },
            skillsEnabled: false,
            connectorsUiEnabled: false,
        },
    }),
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn() },
}));

describe('Dashboard', () => {
    it('renders without error', () => {
        const wrapper = mount(Dashboard, {
            props: {
                jobMetrics: { pending: 0, completed: 0, failed: 0 },
                system: {
                    scheduler: { status: 'healthy', age_seconds: 10, last_seen_at: '2026-03-09' },
                    freshness: { active_build_age_seconds: null, active_build_is_stale: false },
                    activeProjectionBuildId: null,
                    rebuildingBuildId: null,
                    reasonState: 'idle',
                    signals: { delayed: [], unobservable: [] },
                },
                runtimePolicy: {},
                navigation: {
                    deployments: '/deployments',
                    escalations: '/escalations',
                    replayBuilds: '/replay-builds',
                    budgets: '/budgets',
                },
            },
            global: {
                mocks: {
                    route: (name: string) => `/${name}`,
                    $page: {
                        props: {
                            skillsEnabled: false,
                            connectorsUiEnabled: false,
                            flash: {},
                        },
                    },
                },
                stubs: {
                    AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
                    TunnelStatusCard: true,
                    SkillUsageCard: true,
                    SkillHealthCard: true,
                    ConnectorHealthWidget: true,
                    ConnectorUsageWidget: true,
                    HelpHint: true,
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
