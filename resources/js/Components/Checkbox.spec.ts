import { render, screen } from '@testing-library/vue';
import Checkbox from './Checkbox.vue';

describe('Checkbox', () => {
    it('renders without error', () => {
        render(Checkbox, { props: { checked: false } });
    });

    it('has checkbox role', () => {
        render(Checkbox, { props: { checked: false } });
        expect(screen.getByRole('checkbox')).toBeTruthy();
    });
});
