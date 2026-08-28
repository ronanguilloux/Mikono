import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'menuButton', 'settingsMenu', 'settingsButton'];

    toggle() {
        const expanded = this.menuTarget.classList.toggle('hidden') === false;
        this.menuButtonTarget.setAttribute('aria-expanded', String(expanded));
    }

    toggleSettings(event) {
        event.stopPropagation();
        const expanded = this.settingsMenuTarget.classList.toggle('hidden') === false;
        this.settingsButtonTarget.setAttribute('aria-expanded', String(expanded));
    }

    closeSettings() {
        this.settingsMenuTarget.classList.add('hidden');
        this.settingsButtonTarget.setAttribute('aria-expanded', 'false');
    }
}
