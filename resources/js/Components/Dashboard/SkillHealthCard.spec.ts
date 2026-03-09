import { mount } from '@vue/test-utils';
import SkillHealthCard from './SkillHealthCard.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [] } }),
    },
}));

describe('SkillHealthCard', () => {
    it('renders without error', () => {
        const wrapper = mount(SkillHealthCard);
        expect(wrapper.exists()).toBe(true);
    });

    it('shows "Skill Health" title', () => {
        const wrapper = mount(SkillHealthCard);
        expect(wrapper.text()).toContain('Skill Health');
    });
});
