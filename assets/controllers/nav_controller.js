import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'settingsMenu'];

    toggle() {
        this.menuTarget.classList.toggle('hidden');
    }

    toggleSettings(event) {
        event.stopPropagation();
        this.settingsMenuTarget.classList.toggle('hidden');
    }

    closeSettings() {
        this.settingsMenuTarget.classList.add('hidden');
    }
}
