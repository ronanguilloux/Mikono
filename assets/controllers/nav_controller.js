import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'menuButton'];

    toggle() {
        const expanded = this.menuTarget.classList.toggle('hidden') === false;
        this.menuButtonTarget.setAttribute('aria-expanded', String(expanded));
    }
}
