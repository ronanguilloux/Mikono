import { Controller } from '@hotwired/stimulus';

/**
 * Sits on a form row whose field picks from a Settings list, next to the
 * theme's "⚙ Manage" link (ManageLinkTypeExtension). That link opens the list
 * in a new tab so nothing typed here is lost; this brings back what was added
 * there. Once the link has been clicked, every return to this tab re-reads
 * the current page and swaps in the field's fresh options, keeping whatever
 * is selected now.
 *
 * A convenience, never a guard: if the fetch fails the list stays as it was,
 * and a reload does the same job. The request carries X-Sec-Purpose, like
 * Turbo's prefetches, so /usage doesn't count it as a page view (ADR 0021).
 */
export default class extends Controller {
    static values = { field: String };

    connect() {
        this.pending = false;
        this.busy = false;
        this.refresh = this.refresh.bind(this);
        document.addEventListener('visibilitychange', this.refresh);
        window.addEventListener('focus', this.refresh);
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.refresh);
        window.removeEventListener('focus', this.refresh);
    }

    opened() {
        this.pending = true;
    }

    async refresh() {
        // Both events fire on the way back; one fetch is enough.
        if (!this.pending || this.busy || document.visibilityState !== 'visible') {
            return;
        }
        const field = document.getElementById(this.fieldValue);
        if (!field) {
            return;
        }

        this.busy = true;
        try {
            const response = await fetch(window.location.href, {
                headers: { Accept: 'text/html', 'X-Sec-Purpose': 'prefetch' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = page.getElementById(this.fieldValue);
            if (fresh && fresh.innerHTML !== field.innerHTML) {
                this.swap(field, fresh);
            }
        } catch {
            // Offline or signed out: keep the list as it is.
        } finally {
            this.busy = false;
        }
    }

    swap(field, fresh) {
        const inputs = (root) => root.matches('select')
            ? Array.from(root.options)
            : Array.from(root.querySelectorAll('input[type=checkbox], input[type=radio]'));
        const chosen = new Set(
            inputs(field).filter((input) => input.selected ?? input.checked).map((input) => input.value),
        );
        const isSelect = field.matches('select');

        field.replaceChildren(...Array.from(fresh.childNodes, (node) => document.importNode(node, true)));
        for (const input of inputs(field)) {
            if (isSelect) {
                input.selected = chosen.has(input.value);
            } else {
                input.checked = chosen.has(input.value);
            }
        }

        field.dispatchEvent(new Event('change', { bubbles: true }));
        // For listeners on another field: program-types re-filters the
        // activity types it narrows, which arrive here unfiltered.
        this.dispatch('refreshed', { target: field });
    }
}
