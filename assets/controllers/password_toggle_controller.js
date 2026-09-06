import { Controller } from '@hotwired/stimulus';

/**
 * Flips a password field between hidden and plain text — the ordinary "show
 * what I am typing" affordance, worth having on a phone keyboard.
 *
 * It never reveals a stored password: the only PasswordType in the app is
 * UserFormType's unmapped `plainPassword`, which is always a *new* password
 * being typed. The stored one is a hash and nothing on that screen knows it.
 */
export default class extends Controller {
    static targets = ['input', 'label'];

    toggle(event) {
        const shown = this.inputTarget.type === 'text';

        this.inputTarget.type = shown ? 'password' : 'text';
        this.labelTarget.textContent = shown ? 'Show' : 'Hide';
        event.currentTarget.setAttribute('aria-pressed', String(!shown));
    }
}
