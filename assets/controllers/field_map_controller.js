import { Controller } from '@hotwired/stimulus';

/**
 * Shows the panel of the box clicked on the data drawing. Every panel is in
 * the HTML, so without JavaScript they are simply all listed, and so they stay
 * on a phone, where the drawing is small and scrolls sideways.
 */
export default class extends Controller {
    static targets = ['panel'];

    connect() {
        if (window.matchMedia('(min-width: 768px)').matches) {
            this.show(this.panelTargets[0].dataset.id);
        }
    }

    pick(event) {
        const box = event.target.closest('.ent');

        if (box) {
            this.show(box.dataset.id);
        }
    }

    press(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.pick(event);
        }
    }

    show(id) {
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.id !== id;
        });
        this.element.querySelectorAll('.ent').forEach((box) => {
            box.classList.toggle('sel', box.dataset.id === id);
        });
    }
}
