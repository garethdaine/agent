import { mount } from '@vue/test-utils';
import DocsIndex from './Index.vue';

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

describe('Docs Index', () => {
    const globalOptions = {
        mocks: {
            route: (name: string, params?: Record<string, unknown>) => `/${name}`,
        },
        stubs: {
            AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
            MarkdownRenderer: true,
            HelpHint: true,
        },
    };

    it('renders without error', () => {
        const wrapper = mount(DocsIndex, {
            props: {
                entries: [],
                activeEntry: null,
                filters: { q: '', domain: '', section: '' },
            },
            global: globalOptions,
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('shows empty message when no entries match', () => {
        const wrapper = mount(DocsIndex, {
            props: {
                entries: [],
                activeEntry: null,
                filters: { q: '', domain: '', section: '' },
            },
            global: globalOptions,
        });
        expect(wrapper.text()).toContain('No docs found for these filters');
    });
});
