import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'dateInput',
        'otherRadio',
        'otherDurationInput',
        'chips',
        'search',
        'suggestions',
        'count',
        'checkboxes',
    ];

    static values = { today: String, tomorrow: String };

    connect() {
        this.highlighted = -1;
        this.toggleOtherField();
        this.renderChips();
    }

    setToday() {
        this.dateInputTarget.value = this.todayValue;
    }

    setTomorrow() {
        this.dateInputTarget.value = this.tomorrowValue;
    }

    toggleOtherField() {
        this.otherDurationInputTarget.disabled = !this.otherRadioTarget.checked;
    }

    checkboxes() {
        return Array.from(this.checkboxesTarget.querySelectorAll('input[type="checkbox"]'));
    }

    renderChips() {
        const checked = this.checkboxes().filter((checkbox) => checkbox.checked);

        this.chipsTarget.innerHTML = '';
        checked.forEach((checkbox) => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1.5 rounded-full bg-brand-100 py-1 pl-3 pr-1.5 text-sm font-medium text-brand-700';

            const label = document.createElement('span');
            label.textContent = checkbox.dataset.name;
            chip.appendChild(label);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'flex h-4 w-4 items-center justify-center rounded-full bg-black/10 text-xs leading-none hover:bg-black/20';
            remove.setAttribute('aria-label', `Remove ${checkbox.dataset.name}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                checkbox.checked = false;
                this.renderChips();
                this.renderSuggestions();
            });
            chip.appendChild(remove);

            this.chipsTarget.appendChild(chip);
        });

        this.countTargets.forEach((el) => {
            el.textContent = String(checked.length);
        });
    }

    onSearchInput() {
        this.renderSuggestions();
    }

    onSearchFocus() {
        this.renderSuggestions();
    }

    onSearchBlur() {
        setTimeout(() => {
            this.suggestionsTarget.hidden = true;
            this.searchTarget.setAttribute('aria-expanded', 'false');
        }, 120);
    }

    onSearchKeydown(event) {
        const rows = Array.from(this.suggestionsTarget.querySelectorAll('[data-checkbox-id]'));

        if (event.key === 'ArrowDown' && rows.length) {
            event.preventDefault();
            this.highlighted = Math.min(this.highlighted + 1, rows.length - 1);
            this.highlightRows(rows);
        } else if (event.key === 'ArrowUp' && rows.length) {
            event.preventDefault();
            this.highlighted = Math.max(this.highlighted - 1, 0);
            this.highlightRows(rows);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const row = rows[this.highlighted] || rows[0];
            if (row) {
                this.addVolunteer(row.dataset.checkboxId);
            }
        } else if (event.key === 'Escape') {
            this.suggestionsTarget.hidden = true;
        }
    }

    highlightRows(rows) {
        rows.forEach((row, index) => {
            row.classList.toggle('bg-slate-100', index === this.highlighted);
        });
    }

    renderSuggestions() {
        const query = this.searchTarget.value.trim().toLowerCase();
        const available = this.checkboxes().filter((checkbox) => !checkbox.checked);
        const matches = available
            .filter((checkbox) => query === '' || checkbox.dataset.name.toLowerCase().includes(query))
            .slice(0, 6);

        this.highlighted = matches.length ? 0 : -1;
        this.suggestionsTarget.innerHTML = '';

        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-2 text-xs text-slate-500';
            empty.textContent = query ? `No volunteer matches "${this.searchTarget.value}"` : 'Everyone is already selected';
            this.suggestionsTarget.appendChild(empty);
        } else {
            matches.forEach((checkbox, index) => {
                const row = document.createElement('div');
                row.className = `flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-slate-100 ${
                    index === this.highlighted ? 'bg-slate-100' : ''
                }`;
                row.setAttribute('role', 'option');
                row.dataset.checkboxId = checkbox.id;

                const name = document.createElement('span');
                name.className = 'flex-1';
                name.textContent = checkbox.dataset.name;
                row.appendChild(name);

                row.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    this.addVolunteer(checkbox.id);
                });

                this.suggestionsTarget.appendChild(row);
            });
        }

        this.suggestionsTarget.hidden = false;
        this.searchTarget.setAttribute('aria-expanded', 'true');
    }

    addVolunteer(checkboxId) {
        const checkbox = this.checkboxesTarget.querySelector(`#${CSS.escape(checkboxId)}`);
        if (!checkbox) {
            return;
        }

        checkbox.checked = true;
        this.renderChips();
        this.searchTarget.value = '';
        this.searchTarget.focus();
        this.renderSuggestions();
    }
}
