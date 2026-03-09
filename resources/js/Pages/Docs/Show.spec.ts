import { mount } from '@vue/test-utils';
import DocsShow from './Show.vue';

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

describe('Docs Show', () => {
    const defaultProps = {
        entry: {
            title: 'Test Doc',
            slug: 'test-doc',
            domain: 'general',
            section: 'overview',
            summary: 'A test document.',
            body_markdown: '## Heading\nContent here.',
            body_html: '',
        },
        entries: [],
        filters: { q: '', domain: '', section: '' },
    };

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
        const wrapper = mount(DocsShow, { props: defaultProps, global: globalOptions });
        expect(wrapper.exists()).toBe(true);
    });

    it('displays the entry title', () => {
        const wrapper = mount(DocsShow, {
            props: {
                ...defaultProps,
                entry: { ...defaultProps.entry, title: 'Getting Started' },
            },
            global: globalOptions,
        });
        expect(wrapper.text()).toContain('Getting Started');
    });
});
