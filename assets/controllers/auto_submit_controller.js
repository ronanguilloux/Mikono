import { Controller } from '@hotwired/stimulus';

/**
 * Submits the form as soon as a control inside it changes, so the page-size
 * selector doesn't need a visible "Apply" button. That button still exists,
 * screen-reader-only, so the selector keeps working with JavaScript off.
 *
 * Empty controls are dropped from the submission, because every form using
 * this controller has a "no value" option — "All volunteers", "Newest first" —
 * and a GET form would submit those as `?volunteer=` / `?sort=`. That empty
 * param is not merely ugly: the sort headers, the page links and these forms
 * all carry the rest of the query string through, so once it exists it is
 * copied into every link on the page and never goes away. The browser omits
 * disabled controls, and serializes the form synchronously inside
 * requestSubmit(), so re-enabling immediately after is safe and leaves the
 * page usable if the navigation is cancelled.
 */
export default class extends Controller {
    submit() {
        const empties = [...this.element.elements].filter(
            (element) => element.name && '' === element.value,
        );

        empties.forEach((element) => { element.disabled = true; });
        this.element.requestSubmit();
        empties.forEach((element) => { element.disabled = false; });
    }
}
