import { Controller } from '@hotwired/stimulus';

/**
 * Records a gesture that leaves no request behind, so /usage can see it.
 *
 * Only for things the access log structurally cannot show: a clipboard copy,
 * a typeahead nobody submitted, a form left without saving. Anything that
 * loads a page is already counted — wiring it here would double-count it.
 *
 * Fire-and-forget on purpose. Measuring a feature must never break it, so
 * every failure path here is a no-op, and `once` guarantees a keystroke
 * handler cannot turn into one request per character.
 */
export default class extends Controller {
    static values = {
        url: String,
        token: String,
        name: String,
    };

    connect() {
        this.sent = new Set();
        this.dirty = false;
    }

    /** Records this controller's own `nameValue`. */
    record() {
        this.send(this.nameValue);
    }

    /** The reader typed something into this form. */
    markDirty() {
        this.dirty = true;
    }

    /**
     * They submitted it, so whatever happens next is not abandonment. Turbo
     * navigates on the redirect that follows a successful save, and without
     * this every completed form would be counted as given up on.
     */
    markSaved() {
        this.dirty = false;
    }

    /**
     * Half-filled and navigated away from. Bound to turbo:before-visit@window,
     * which covers in-app navigation — closing the tab outright is not caught,
     * and is not worth an unload handler to chase.
     */
    recordIfAbandoned() {
        if (this.dirty) {
            this.send(this.nameValue);
        }
    }

    /**
     * Records the name on the element that triggered the action, so one
     * controller on a wrapper can serve several buttons inside it.
     */
    recordNamed(event) {
        const name = event.params?.name ?? event.currentTarget?.dataset?.usageEventName;

        if (name) {
            this.send(name);
        }
    }

    /** Once per page load, whatever happens — see the class comment. */
    send(name) {
        if (!name || this.sent.has(name) || !this.hasUrlValue) {
            return;
        }

        this.sent.add(name);

        const body = new FormData();
        body.append('name', name);
        body.append('_token', this.tokenValue);

        // keepalive so a gesture on the way out of the page still lands.
        fetch(this.urlValue, { method: 'POST', body, keepalive: true }).catch(() => {});
    }
}
