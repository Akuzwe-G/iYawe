export function balanceIssue(value, otherValue = '') {
    const valid = input => typeof input === 'string' && /^[0-9]+$/.test(input) && Number(input) >= 0 && Number(input) <= 49999;
    if (!valid(value)) return 'format';
    if (valid(otherValue) && Number(value) !== Number(otherValue)) return 'different';
    return null;
}

export function initBalanceValidation() {
    for (const group of document.querySelectorAll('[data-card-balances]')) {
        const inputs = Array.from(group.querySelectorAll('[data-card-balance]'));
        const touched = new Set();
        const update = () => {
            for (const input of inputs) {
                const peer = inputs.find(other => other !== input);
                const issue = balanceIssue(input.value, peer?.value);
                const message = issue === 'different' ? group.dataset.equalMessage : issue ? group.dataset.formatMessage : '';
                input.setCustomValidity(message);
                if (touched.has(input)) {
                    const error = group.querySelector('#' + input.name + '_client_error');
                    error.textContent = message;
                    error.hidden = !message;
                    input.setAttribute('aria-invalid', String(!!message));
                }
            }
        };
        for (const input of inputs) {
            for (const event of ['input', 'blur', 'invalid']) {
                input.addEventListener(event, () => { touched.add(input); update(); });
            }
        }
        update();
    }
}
