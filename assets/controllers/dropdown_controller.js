import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'button'];

    toggle() {
        const expanded = this.menuTarget.classList.toggle('hidden') === false;
        this.buttonTarget.setAttribute('aria-expanded', String(expanded));
    }

    // Window listener: a click inside this dropdown is its own toggle's business,
    // a click anywhere else (including another dropdown's button) closes it.
    close(event) {
        if (event.type === 'click' && this.element.contains(event.target)) {
            return;
        }
        this.menuTarget.classList.add('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }
}
