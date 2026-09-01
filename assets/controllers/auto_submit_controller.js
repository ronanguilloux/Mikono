import { Controller } from '@hotwired/stimulus';

/**
 * Submits the form as soon as a control inside it changes, so the page-size
 * selector doesn't need a visible "Apply" button. That button still exists,
 * screen-reader-only, so the selector keeps working with JavaScript off.
 */
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}
