import { Controller } from '@hotwired/stimulus';

/**
 * Copies a read-only textarea to the clipboard, with a fallback that just
 * selects the text — the roster still gets sent even when the browser refuses
 * clipboard access (no HTTPS, an old Android WebView, a denied permission).
 */
export default class extends Controller {
    static targets = ['details', 'source', 'label', 'status'];

    reveal() {
        this.detailsTarget.open = true;
        this.detailsTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    copy() {
        const text = this.sourceTarget.value;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(
                () => this.#done('Copied!'),
                () => this.#fallback(),
            );

            return;
        }

        this.#fallback();
    }

    #fallback() {
        this.sourceTarget.focus();
        this.sourceTarget.select();
        this.statusTarget.textContent = 'Copy is blocked here — the text is selected, press Ctrl/⌘ + C to copy it.';
        this.#done('Select & copy');
    }

    #done(label) {
        this.labelTarget.textContent = label;
        setTimeout(() => {
            this.labelTarget.textContent = 'Copy';
        }, 1600);
    }
}
