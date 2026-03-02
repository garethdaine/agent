import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import HelpHint from '@/Components/HelpHint.vue';

async function flushPromises(): Promise<void> {
    await Promise.resolve();
    await Promise.resolve();
}

describe('HelpHint', () => {
    it('supports keyboard focus and escape dismiss with ARIA labels', async () => {
        const wrapper = mount(HelpHint, {
            props: {
                uiKey: 'jobs.timeout',
                shortText: 'Timeout protects queue workers.',
                longText: 'Long jobs are stopped before they consume unbounded resources.',
                severity: 'warning',
            },
        });

        const trigger = wrapper.get('button');

        expect(trigger.attributes('aria-label')).toContain('Show help');

        await trigger.trigger('focus');
        expect(wrapper.get('[role="tooltip"]').text()).toContain('Timeout protects queue workers.');

        await trigger.trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="tooltip"]').exists()).toBe(false);
    });

    it('toggles tooltip on tap/click for mobile fallback', async () => {
        const wrapper = mount(HelpHint, {
            props: {
                uiKey: 'jobs.timeout',
                shortText: 'Tap to inspect helper text.',
            },
        });

        const trigger = wrapper.get('button');

        await trigger.trigger('click');
        expect(wrapper.find('[role="tooltip"]').exists()).toBe(true);

        await trigger.trigger('click');
        expect(wrapper.find('[role="tooltip"]').exists()).toBe(false);
    });

    it('falls back to API fragment lookup when only uiKey is provided', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    data: {
                        ui_key: 'jobs.timeout',
                        short_text: 'Fetched helper.',
                        long_text: null,
                        severity: 'info',
                        learn_more_slug: 'jobs-timeouts',
                    },
                }),
            })
        );

        const wrapper = mount(HelpHint, {
            props: {
                uiKey: 'jobs.timeout',
            },
        });

        await flushPromises();

        expect(wrapper.find('button').exists()).toBe(true);
        await wrapper.get('button').trigger('click');
        expect(wrapper.get('[role="tooltip"]').text()).toContain('Fetched helper.');
    });

    it('renders nothing when API returns not found (silent miss)', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: false,
                status: 404,
                json: async () => ({
                    error: { code: 'NOT_FOUND' },
                }),
            })
        );

        const wrapper = mount(HelpHint, {
            props: {
                uiKey: 'unknown.ui.key',
            },
        });

        await flushPromises();

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('renders short text without long text and stays silent on API timeout', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('timeout')));

        const missingWrapper = mount(HelpHint, {
            props: {
                uiKey: 'jobs.timeout',
            },
        });

        await flushPromises();
        expect(missingWrapper.find('button').exists()).toBe(false);

        const inlineWrapper = mount(HelpHint, {
            props: {
                uiKey: 'jobs.timeout',
                shortText: 'Inline helper',
                longText: null,
            },
        });

        await inlineWrapper.get('button').trigger('click');
        const tooltip = inlineWrapper.get('[role="tooltip"]').text();

        expect(tooltip).toContain('Inline helper');
        expect(tooltip).not.toContain('undefined');
    });
});
