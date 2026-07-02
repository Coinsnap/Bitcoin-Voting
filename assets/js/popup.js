// js/popup.js
var voteButton = null;
var walletHandler = null;
var votingModal = null;

const checkRequiredVotingFieds = (fields) => {
    let valid = true;
    fields.forEach((field) => {
        if (field && field.required && !field.value.trim()) {
            valid = false;
            field.classList.add('error');
            setTimeout(() => {
                field.classList.remove('error');
            }, 3000);
        }
    });
    return valid;
};

/**
 * Create a fullscreen iframe modal for payment (booking/donation style).
 * Clicking the backdrop re-shows the donor popup.
 */
const createVotingModal = (pollId) => {
    const existing = document.querySelector('.cbv-modal-backdrop');
    if (existing) { existing.remove(); }

    const backdrop = document.createElement('div');
    backdrop.className = 'cbv-modal-backdrop';

    const modal = document.createElement('div');
    modal.className = 'cbv-modal';

    const iframe = document.createElement('iframe');
    iframe.className = 'cbv-modal-iframe';

    modal.appendChild(iframe);
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    // Clicking outside the modal (on the dark backdrop) closes it
    // and restores the donor form so the user can try again.
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            backdrop.remove();
            votingModal = null;
            // Re-show qr-container with donor form
            showVotingElementsById(['blur-overlay', 'qr-container'], 'flex', 'coinsnap-bitcoin-voting-', pollId);
            showVotingElementById('public-donor-popup', 'flex', 'coinsnap-bitcoin-voting-', pollId);
            if (voteButton) { voteButton.disabled = false; }
        }
    });

    return { backdrop: backdrop, iframe: iframe };
};

const resetPopup = (id) => {
    // Close iframe modal if open
    if (votingModal && votingModal.backdrop) {
        votingModal.backdrop.remove();
        votingModal = null;
    }
    hideVotingElementsById(['qr-container', 'blur-overlay', 'payment-loading', 'payment-popup', 'thank-you-popup'], 'coinsnap-bitcoin-voting-', id);
    showVotingElementById('public-donor-popup', 'flex', 'coinsnap-bitcoin-voting-', id);
    if (voteButton) { voteButton.disabled = false; }
    const payInWalletBtn = document.getElementById(`coinsnap-bitcoin-voting-pay-in-wallet${id}`);
    if (walletHandler && payInWalletBtn) {
        payInWalletBtn.removeEventListener('click', walletHandler);
        walletHandler = null;
    }
};

const addWindowListeners = () => {
    window.addEventListener('click', function (event) {
        const votingForms = document.getElementsByClassName('coinsnap-bitcoin-voting-form');
        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            const pollId = votingForm.dataset.pollId;
            const qrContainer = document.getElementById(`coinsnap-bitcoin-voting-qr-container${pollId}`);
            if (!qrContainer) { continue; }
            const element = event.target;
            if (qrContainer.style.display === 'flex') {
                if (
                    element.classList.contains('close-popup') ||
                    (!qrContainer.contains(event.target) &&
                     !element.id.includes('pay') &&
                     !element.classList.contains('poll-option'))
                ) {
                    resetPopup(pollId);
                }
            }
        }
    });
};

const popupButtonListener = (pollId, amount, amountFiat, currency, publicDonor) => {

    document.getElementById(`coinsnap-bitcoin-voting-public-donors-pay${pollId}`)?.addEventListener('click', async (event) => {
        event.preventDefault();
        var retryId = '';

        const option = voteButton.dataset.option;
        const optionName = document.querySelector(`.poll-option[data-option='${option}']`)?.textContent;
        const firstNameField   = document.getElementById(`coinsnap-bitcoin-voting-first-name${pollId}`);
        const lastNameField    = document.getElementById(`coinsnap-bitcoin-voting-last-name${pollId}`);
        const emailField       = document.getElementById(`coinsnap-bitcoin-voting-donor-email${pollId}`);
        const streetField      = document.getElementById(`coinsnap-bitcoin-voting-street${pollId}`);
        const houseNumberField = document.getElementById(`coinsnap-bitcoin-voting-house-number${pollId}`);
        const postalCodeField  = document.getElementById(`coinsnap-bitcoin-voting-postal${pollId}`);
        const cityField        = document.getElementById(`coinsnap-bitcoin-voting-town${pollId}`);
        const countryField     = document.getElementById(`coinsnap-bitcoin-voting-country${pollId}`);
        const address = `${streetField?.value ?? ''} ${houseNumberField?.value ?? ''}, ${postalCodeField?.value ?? ''} ${cityField?.value ?? ''}, ${countryField?.value ?? ''}`;
        const customField     = document.getElementById(`coinsnap-bitcoin-voting-custom${pollId}`);
        const customNameField = document.getElementById(`coinsnap-bitcoin-voting-custom-name${pollId}`);
        const customContent   = customNameField?.textContent && customField?.value
            ? `${customNameField.textContent}: ${customField.value}` : '';
        const validForm = !parseInt(publicDonor) || checkRequiredVotingFieds([
            firstNameField, lastNameField, emailField,
            streetField, houseNumberField, postalCodeField,
            cityField, countryField, customField
        ]);
        const metadata = {
            donorName:    `${firstNameField?.value ?? ''} ${lastNameField?.value ?? ''}`.trim(),
            donorEmail:   emailField?.value,
            donorAddress: (address !== ' ,  , ') ? address : '',
            donorCustom:  customContent,
            formType:     'Coinsnap Bitcoin Voting',
            amount:       `${amount} SATS`,
            amountFiat:   `${amountFiat} ${currency}`,
            publicDonor:  publicDonor || 0,
            modal:        true,
            optionId:     option,
            option:       optionName,
            pollId:       pollId,
            pollTitle:    document.querySelector(`.coinsnap-bitcoin-voting-form[data-poll-id="${pollId}"]`)?.dataset?.pollTitle || ''
        };

        if (!validForm) { return; }

        // Show spinner, hide donor form
        showVotingElementById('payment-loading', 'flex', 'coinsnap-bitcoin-voting-', pollId);
        hideVotingElementById('public-donor-popup', 'coinsnap-bitcoin-voting-', pollId);

        const res = await createVotingInvoice(amount, 'VOTED for {IDK}', amountFiat, currency, undefined, 'Coinsnap Bitcoin Voting', false, metadata);

        if (res && res.checkoutLink) {
            retryId = res.id;

            // Hide spinner and the QR container; open fullscreen iframe modal
            hideVotingElementById('payment-loading', 'coinsnap-bitcoin-voting-', pollId);
            hideVotingElementsById(['qr-container', 'blur-overlay'], 'coinsnap-bitcoin-voting-', pollId);

            votingModal = createVotingModal(pollId);
            votingModal.iframe.src = res.checkoutLink;
            votingModal.backdrop.style.display = 'flex';

            var retryNum = 0;

            const checkPaymentStatus = () => {
                fetch(`${Coinsnap_Bitcoin_Voting_sharedData?.rest_url?.replace(/\/$/, '') || ''}/voting/v1/check-payment-status/${res.id}`, {
                        headers: { 'X-WP-Nonce': Coinsnap_Bitcoin_Voting_sharedData?.nonce || '' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            // Close iframe modal if still open (shouldn't normally happen
                            // since Coinsnap does a top-level redirect to confirmation page,
                            // but handle edge cases where polling detects completed first).
                            if (votingModal && votingModal.backdrop) {
                                votingModal.backdrop.remove();
                                votingModal = null;
                            }
                            setCookie(`coinsnap_poll_${pollId}`, option, 30 * 24 * 60);

                        } else if (!votingModal || !document.body.contains(votingModal.backdrop)) {
                            // Modal was closed by user � stop polling
                            retryId = '';
                        } else if (retryNum < 180 && retryId === res.id) {
                            retryNum++;
                            setTimeout(checkPaymentStatus, 1000);
                        }
                        // else: invoice expired � do nothing
                    })
                    .catch(error => {
                        console.error('Error checking payment status:', error);
                        retryNum++;
                        if (retryId === res.id) {
                            setTimeout(checkPaymentStatus, 5000);
                        }
                    });
            };

            checkPaymentStatus();

        } else {
            // Invoice creation failed � restore donor form
            hideVotingElementById('payment-loading', 'coinsnap-bitcoin-voting-', pollId);
            showVotingElementById('public-donor-popup', 'flex', 'coinsnap-bitcoin-voting-', pollId);
            console.error('Error creating invoice or missing checkoutLink');
        }
    });
};

const addVotingPopupListener = (button, publicDonor, amount, amountFiat, currency, pollId) => {
    button.addEventListener('click', async (event) => {
        button.disabled = true;
        event.preventDefault();
        voteButton = button;
        if (!parseInt(publicDonor)) {
            // No donor info needed — auto-click pay and show loading state
            const publicDonorsPay = document.getElementById(`coinsnap-bitcoin-voting-public-donors-pay${pollId}`);
            publicDonorsPay?.click();
        }
        showVotingElementsById(['blur-overlay', 'qr-container'], 'flex', 'coinsnap-bitcoin-voting-', pollId);
    });
};
