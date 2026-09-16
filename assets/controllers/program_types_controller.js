import { Controller } from '@hotwired/stimulus';

/**
 * Sits on an activity form's program select and narrows the same form's
 * activity-type select to the types that program offers (each option lists
 * its programs in data-programs). A hint, not the guard: the server refuses
 * a type the program doesn't offer, and without JavaScript the list is just
 * unfiltered. See ADR 0030.
 */
export default class extends Controller {
    connect() {
        this.filter();
    }

    filter() {
        const program = this.element.value;
        const types = this.element.form?.querySelector('select[name$="[activityType]"]');
        if (!types) {
            return;
        }

        for (const option of types.options) {
            if (option.value === '') {
                continue;
            }
            const offered = program === '' || (option.dataset.programs ?? '').split(' ').includes(program);
            // Safari ignores `hidden` on an <option>; `disabled` still holds.
            option.hidden = !offered;
            option.disabled = !offered;
        }

        if (types.selectedOptions[0]?.disabled) {
            types.value = '';
        }
    }
}
