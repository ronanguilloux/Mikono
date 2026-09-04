import { Controller } from '@hotwired/stimulus';

/**
 * Shows the partner-organization field only for a partner project, and marks
 * it required while it is shown.
 *
 * This is the hint, not the guard: Project::validatePartnerOrganizationName()
 * enforces the same rule server-side and still runs with JavaScript off, which
 * is why the field ships visible and this controller hides it on connect
 * rather than the markup shipping it hidden.
 */
export default class extends Controller {
    static targets = ['ownership', 'field'];
    static values = { requiredFor: String };

    connect() {
        this.toggle();
    }

    toggle() {
        const needed = this.ownershipTarget.value === this.requiredForValue;
        const input = this.fieldTarget.querySelector('input');

        this.fieldTarget.hidden = !needed;
        input.required = needed;

        // A name typed before switching to UCESCO-owned would otherwise be
        // saved on a project that has no partner. The server rule only checks
        // the partner case, so nothing else would catch it.
        if (!needed) {
            input.value = '';
        }
    }
}
