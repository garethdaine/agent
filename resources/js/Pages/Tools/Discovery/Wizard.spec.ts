import { mount } from '@vue/test-utils';
import Wizard from './Wizard.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [] } }),
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

describe('Discovery Wizard', () => {
    it('renders without error', () => {
        const wrapper = mount(Wizard, {
            props: {
                sessionId: 1,
            },
            global: {
                mocks: {
                    route: (name: string, params?: Record<string, unknown>) => `/${name}`,
                },
                stubs: {
                    AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
                    HelpHint: true,
                    AnswerInput: true,
                    PhaseStepper: true,
                    QuestionRenderer: true,
                    SessionStatusBadge: true,
                    StatusCard: true,
                    BuildPanel: true,
                    PlanViewer: true,
                    QaHistoryPanel: true,
                    StatsPanel: true,
                    GuardrailsPanel: true,
                    SummaryViewer: true,
                },
            },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
