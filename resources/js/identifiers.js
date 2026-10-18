export function normaliseCard(value) {
    return value.toUpperCase();
}

export function cardIssue(value, otherValue = '') {
    const normalised = normaliseCard(value);
    if (!/^[0-9A-F]{8}$/.test(normalised)) return 'format';
    if (normalised === normaliseCard(otherValue)) return 'different';
    return null;
}

export function identificationIssue(value, type, specifications) {
    if (!specifications[type]) return 'type';
    if (!value) return 'required';
    const spec = specifications[type];
    return value.length >= spec.min && value.length <= spec.max && new RegExp('^(?:' + spec.pattern + ')$').test(value) ? null : 'format';
}

export function initIdentifierValidation() {
    for (const group of document.querySelectorAll('[data-identification]')) {
        const type = group.querySelector('[name=identification_type]');
        const number = group.querySelector('[name=national_id]');
        const specifications = JSON.parse(group.querySelector('[data-identifier-specifications]').textContent);
        const typeError = group.querySelector('#identification_type_error');
        const numberError = group.querySelector('#national_id_error');
        const hint = group.querySelector('#national_id_hint');
        const chooseMessage = type.dataset.requiredMessage;
        const update = (show = false) => {
            const spec = specifications[type.value];
            number.inputMode = type.value === 'rwandan_national_id' ? 'numeric' : 'text';
            // Keep oversized pasted values visible instead of truncating them into a different valid number.
            number.maxLength = 80;
            if (spec) { number.pattern = spec.pattern; number.minLength = spec.min; }
            else { number.removeAttribute('pattern'); number.removeAttribute('minlength'); }
            hint.textContent = spec?.message ?? chooseMessage;
            type.setCustomValidity(spec ? '' : chooseMessage);
            const issue = identificationIssue(number.value, type.value, specifications);
            const message = issue === 'type' ? chooseMessage : issue === 'required' ? number.dataset.requiredMessage : issue === 'format' ? spec.message : '';
            number.setCustomValidity(message);
            if (show) {
                numberError.textContent = message; numberError.hidden = !message;
                number.setAttribute('aria-invalid', String(!!message));
                typeError.textContent = spec ? '' : chooseMessage; typeError.hidden = !!spec;
                type.setAttribute('aria-invalid', String(!spec));
            }
        };
        type.addEventListener('change', () => update(true));
        number.addEventListener('input', () => update(true));
        number.addEventListener('blur', () => update(true));
        type.addEventListener('invalid', () => update(true));
        number.addEventListener('invalid', () => update(true));
        update();
    }
    for (const form of document.querySelectorAll('form[data-workflow]')) {
        const cards = Array.from(form.querySelectorAll('[data-tapgo-card]'));
        const touched = new Set();
        const update = () => {
            for (const card of cards) {
                const start = card.selectionStart, end = card.selectionEnd;
                const upper = normaliseCard(card.value);
                if (card.value !== upper) { card.value = upper; card.setSelectionRange(start, end); }
            }
            for (const card of cards) {
                const peer = cards.find(other => other !== card);
                const issue = cardIssue(card.value, peer?.value);
                const message = issue === 'different' ? card.dataset.differentMessage : issue ? card.dataset.formatMessage : '';
                card.setCustomValidity(message);
                if (touched.has(card)) {
                    const error = form.querySelector('#' + card.name + '_client_error');
                    error.textContent = message; error.hidden = !message;
                    card.setAttribute('aria-invalid', String(!!message));
                }
            }
        };
        for (const card of cards) {
            for (const event of ['input', 'blur', 'invalid']) card.addEventListener(event, () => { touched.add(card); update(); });
        }
        update();
    }
}
