import { Controller } from '@hotwired/stimulus';

/*
 * Turns a long <select> into a text box with the browser's own suggestions
 * (<datalist>), so a volunteer can be found by any part of their name. The
 * server still renders the plain select: with JavaScript off it is what you
 * get, and the select stays the one control the form submits. This only sets
 * its value and fires its `change`, so whatever listens there (auto-submit)
 * runs exactly as before.
 *
 * ponytail: suggestion matching and styling are the browser's. If that falls
 * short, symfony/ux-autocomplete, wired by hand (ADR 0003).
 */
export default class extends Controller {
    connect() {
        const select = this.element;
        this.labels = new Map();
        this.datalist = document.createElement('datalist');
        this.datalist.id = `${select.id}-options`;
        this.input = document.createElement('input');

        for (const option of select.options) {
            if ('' === option.value) {
                this.input.placeholder = option.text.trim();
                continue;
            }
            // A datalist entry is its text, so two namesakes need telling apart.
            const base = option.text.trim();
            let label = base;
            for (let n = 2; this.labels.has(label); n++) {
                label = `${base} (${n})`;
            }
            this.labels.set(label, option.value);
            this.datalist.append(new Option(label));
            if (option.selected) {
                this.input.value = label;
            }
        }

        // The <label for> follows the id to the control that is visible.
        this.input.id = select.id;
        select.removeAttribute('id');
        this.input.type = 'search';
        this.input.autocomplete = 'off';
        this.input.className = select.className;
        this.input.setAttribute('list', this.datalist.id);
        this.input.addEventListener('input', this.pick);
        this.input.addEventListener('change', this.clear);
        this.input.addEventListener('search', this.clear);
        this.input.addEventListener('keydown', this.enter);
        select.after(this.input, this.datalist);
        select.hidden = true;

        // Turbo snapshots the page before disconnect() runs: undo first, or a
        // restored page gets a second box.
        document.addEventListener('turbo:before-cache', this.teardown);
    }

    disconnect() {
        this.teardown();
    }

    teardown = () => {
        document.removeEventListener('turbo:before-cache', this.teardown);
        if (!this.input.isConnected) {
            return;
        }
        this.element.id = this.input.id;
        this.element.hidden = false;
        this.input.remove();
        this.datalist.remove();
    };

    // A suggestion picked, or a name typed in full.
    pick = () => {
        const value = this.labels.get(this.input.value);
        if (undefined !== value) {
            this.apply(value);
        }
    };

    // Emptied: back to "All". Leaving the box, the "×" and Enter come here;
    // backspacing it empty while typing must not reload the page.
    clear = () => {
        if ('' === this.input.value.trim()) {
            this.apply('');
        }
    };

    // Enter would submit the form as is, empty select param included.
    enter = (event) => {
        if ('Enter' === event.key) {
            event.preventDefault();
            this.clear();
        }
    };

    apply(value) {
        if (this.element.value === value) {
            return;
        }
        this.element.value = value;
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
