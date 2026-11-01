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

        const svg = this.element.querySelector('svg');
        const box = (name) => svg.querySelector(`.ent[data-id="${name}"]`);

        svg.classList.add('focusing');
        svg.querySelectorAll('.on, .sel').forEach((el) => el.classList.remove('on', 'sel'));
        box(id).classList.add('on', 'sel');
        svg.querySelectorAll('.edge').forEach((edge) => {
            if (edge.dataset.a === id || edge.dataset.b === id) {
                edge.classList.add('on');
                box(edge.dataset.a).classList.add('on');
                box(edge.dataset.b).classList.add('on');
            }
        });
    }
}
