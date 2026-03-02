import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StatusCard from '@/Components/Interrogation/StatusCard.vue';

describe('StatusCard', () => {
    it('renders markdown formatting in recent activity entries', () => {
        const wrapper = mount(StatusCard, {
            props: {
                session: {
                    status: 'running',
                },
                phaseLabel: 'Discovery',
                activityEvents: [
                    {
                        sequence: 101,
                        event_type: 'discovery_activity',
                        payload: {
                            message: '**Starting project inspection** with `rg --files`',
                            at: '2026-03-02T17:34:59Z',
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('ul li strong').text()).toBe('Starting project inspection');
        expect(wrapper.find('ul li code').text()).toContain('rg --files');
    });
});
