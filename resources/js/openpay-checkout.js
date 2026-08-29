/**
 * OpenPay.js checkout glue.
 *
 * OpenPay's JS library is loaded from the CDN (window.OpenPay global).
 * It does three things we care about here:
 *   1. Tokenize a card client-side, so PAN/CVV never reach our server.
 *   2. Compute a device_session_id for anti-fraud heuristics.
 *   3. For OXXO/SPEI there's nothing to tokenize — the server just calls
 *      OpenPay with the appropriate method and gets a barcode / CLABE back.
 *
 * The server-side controller has already been wired for all three methods;
 * this file only handles the front-end half of card flows.
 */
const config = window.__OPENPAY__;
const form = document.getElementById('seat-form');
const submitButton = document.getElementById('seat-submit');
const submitLabel = document.getElementById('seat-submit-label');
const submitSpinner = document.getElementById('seat-submit-spinner');
const openpayError = document.getElementById('openpay-error');

const tokenInput = document.getElementById('openpay-token-input');
const deviceInput = document.getElementById('device-session-id-input');
const savedCardInput = document.getElementById('saved-card-id-input');

const cardHolderInput = document.getElementById('card-holder-name');
const cardNumberInput = document.getElementById('card-number');
const cardMonthInput = document.getElementById('card-exp-month');
const cardYearInput = document.getElementById('card-exp-year');
const cardCvvInput = document.getElementById('card-cvv');

const newCardForm = document.getElementById('new-card-form');
const savedCardRadios = document.querySelectorAll('.saved-card-radio');

let openpayReady = false;

function showOpenpayError(message) {
    if (!openpayError) return;
    openpayError.textContent = message;
    openpayError.classList.remove('hidden');
}

function clearOpenpayError() {
    if (!openpayError) return;
    openpayError.textContent = '';
    openpayError.classList.add('hidden');
}

function setSubmitting(isSubmitting) {
    if (!submitButton) return;
    submitButton.disabled = isSubmitting;
    submitLabel.textContent = isSubmitting ? 'Procesando…' : 'Pagar';
    submitSpinner.classList.toggle('hidden', !isSubmitting);
}

function initOpenPay() {
    if (!window.OpenPay) {
        showOpenpayError('No se pudo cargar OpenPay. Recarga la página para intentarlo de nuevo.');
        return;
    }
    if (!config?.merchantId || !config?.publicKey) {
        // Misconfigured server (missing keys in .env). Don't try to tokenize
        // — we'd just generate a confusing error during submit.
        showOpenpayError('Pagos no disponibles en este momento.');
        return;
    }

    OpenPay.setId(config.merchantId);
    OpenPay.setApiKey(config.publicKey);
    OpenPay.setSandboxMode(Boolean(config.sandbox));

    // deviceData adds a hidden <input name="device_session_id"> into
    // any form we tell it about. We use our own hidden input instead so
    // the form markup is predictable; OpenPay still computes the value
    // and we read it back from a transient field it inserts.
    try {
        const probe = document.createElement('form');
        probe.id = '__openpay_probe__';
        probe.style.display = 'none';
        document.body.appendChild(probe);
        OpenPay.deviceData.setup('__openpay_probe__');
        const probeSession = probe.querySelector('input[name="device_session_id"]');
        if (probeSession && deviceInput) {
            deviceInput.value = probeSession.value;
        }
        probe.remove();
    } catch (e) {
        // Non-fatal — anti-fraud heuristics degrade gracefully without it.
    }

    openpayReady = true;
}

function selectedPaymentMethod() {
    const checked = form?.querySelector('input[name="payment_method"]:checked');
    return checked ? checked.value : 'card';
}

function selectedSavedCard() {
    const checked = form?.querySelector('input[name="use_saved_card"]:checked');
    if (!checked) return null;
    if (checked.value === '0') return 'new';
    return {
        id: checked.value,
        openpayCardId: checked.dataset.cardId,
    };
}

function updatePaymentPanels() {
    const method = selectedPaymentMethod();
    form.querySelectorAll('[data-payment-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.paymentPanel !== method);
    });

    // Update the hidden saved_card_id input to match whatever the
    // customer has selected in the radio group. We leave it blank
    // (not "0") when "new card" is chosen so the server treats it as
    // "no saved card".
    if (savedCardInput) {
        const saved = selectedSavedCard();
        savedCardInput.value = saved && saved !== 'new' ? saved.id : '';
    }
}

function formatCardNumber(value) {
    return value.replace(/\D/g, '').replace(/(\d{4})(?=\d)/g, '$1 ').trim();
}

function tokenizeAndSubmit() {
    if (!openpayReady) {
        showOpenpayError('Pagos no disponibles en este momento.');
        return;
    }

    const holderName = (cardHolderInput?.value || '').trim();
    const cardNumber = (cardNumberInput?.value || '').replace(/\s+/g, '');
    const expMonth = (cardMonthInput?.value || '').padStart(2, '0');
    const expYear = (cardYearInput?.value || '').padStart(2, '0');
    const cvv = cardCvvInput?.value || '';

    if (!holderName || !cardNumber || !expMonth || !expYear || !cvv) {
        showOpenpayError('Completa todos los datos de la tarjeta.');
        return;
    }

    clearOpenpayError();
    setSubmitting(true);

    const tokenData = {
        holder_name: holderName,
        card_number: cardNumber,
        expiration_month: expMonth,
        expiration_year: expYear,
        cvv2: cvv,
    };

    OpenPay.token.create(tokenData, (response) => {
        if (tokenInput) tokenInput.value = response.data.id;
        form.submit();
    }, (error) => {
        setSubmitting(false);
        const message = error?.data?.description
            || error?.message
            || 'No se pudo tokenizar la tarjeta. Revisa los datos.';
        showOpenpayError(message);
    });
}

form?.addEventListener('submit', (event) => {
    // The seat-submit button is also disabled when no seat is selected
    // (handled by seat-picker.js's updateSummary), so by the time we
    // get here, a payment attempt is happening.
    const method = selectedPaymentMethod();

    if (method === 'card') {
        const saved = selectedSavedCard();
        // Saved card path: nothing to tokenize, the server uses the
        // openpay_card_id stored in our DB.
        if (saved && saved !== 'new') {
            clearOpenpayError();
            setSubmitting(true);
            return;
        }
        // New card path: tokenize, then re-submit with the token.
        event.preventDefault();
        tokenizeAndSubmit();
        return;
    }

    // OXXO / SPEI: nothing to tokenize — just submit. The server
    // creates the charge with the appropriate method and OpenPay
    // returns a barcode URL / CLABE that we surface on the pending page.
    clearOpenpayError();
    setSubmitting(true);
});

form?.querySelectorAll('input[name="payment_method"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        clearOpenpayError();
        updatePaymentPanels();
    });
});

savedCardRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
        if (!newCardForm) return;
        const saved = selectedSavedCard();
        newCardForm.classList.toggle('hidden', saved && saved !== 'new');
    });
});

if (cardNumberInput) {
    cardNumberInput.addEventListener('input', () => {
        cardNumberInput.value = formatCardNumber(cardNumberInput.value);
    });
}

[cardMonthInput, cardYearInput, cardCvvInput].forEach((input) => {
    if (!input) return;
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
    });
});

document.addEventListener('DOMContentLoaded', () => {
    initOpenPay();
    updatePaymentPanels();
});
