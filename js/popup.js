// js/popup.js
var voteButton = null;
var walletHandler = null;

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
}

const resetPopup = (id) => {
    hideVotingElementsById(['qr-container', 'blur-overlay', 'payment-loading', 'payment-popup', 'thank-you-popup'], 'coinsnap-bitcoin-voting-', id)
    showVotingElementById('public-donor-popup', 'flex', 'coinsnap-bitcoin-voting-', id)
    voteButton.disabled = false;
    const payInWalletBtn = document.getElementById(`coinsnap-bitcoin-voting-pay-in-wallet${id}`);
    if (walletHandler) {
        payInWalletBtn.removeEventListener('click', walletHandler);
        walletHandler = null;
    }
}

const addWindowListeners = () => {
    window.addEventListener("click", function (event) {
        const votingForms = document.getElementsByClassName('coinsnap-bitcoin-voting-form');
        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            const pollId = votingForm.dataset.pollId
            const qrContainer = document.getElementById(`coinsnap-bitcoin-voting-qr-container${pollId}`);
            const element = event.target
            if (qrContainer.style.display == 'flex') {
                if (element.classList.contains('close-popup') || (!qrContainer.contains(event.target) && !element.id.includes('pay') && !element.classList.contains('poll-option'))) {
                    resetPopup(pollId)

                }
            }
        }
    });

}

const popupButtonListener = (exchangeRates, pollId, amount, publicDonor) => {

    document.getElementById(`coinsnap-bitcoin-voting-public-donors-pay${pollId}`)?.addEventListener('click', async () => {
        event.preventDefault();
        var retryId = '';

        const option = voteButton.dataset.option
        const optionName = document.querySelector(`.poll-option[data-option='${option}']`)?.textContent
        const firstNameField = document.getElementById(`coinsnap-bitcoin-voting-first-name${pollId}`);
        const lastNameField = document.getElementById(`coinsnap-bitcoin-voting-last-name${pollId}`);
        const emailField = document.getElementById(`coinsnap-bitcoin-voting-donor-email${pollId}`);
        const streetField = document.getElementById(`coinsnap-bitcoin-voting-street${pollId}`);
        const houseNumberField = document.getElementById(`coinsnap-bitcoin-voting-house-number${pollId}`);
        const postalCodeField = document.getElementById(`coinsnap-bitcoin-voting-postal${pollId}`);
        const cityField = document.getElementById(`coinsnap-bitcoin-voting-town${pollId}`);
        const countryField = document.getElementById(`coinsnap-bitcoin-voting-country${pollId}`);
        const address = `${streetField?.value ?? ''} ${houseNumberField?.value ?? ''}, ${postalCodeField?.value ?? ''} ${cityField?.value ?? ''}, ${countryField?.value ?? ''}`;
        const customField = document.getElementById(`coinsnap-bitcoin-voting-custom${pollId}`);
        const customNameField = document.getElementById(`coinsnap-bitcoin-voting-custom-name${pollId}`);
        const customContent = customNameField?.textContent && customField?.value ? `${customNameField.textContent}: ${customField.value}` : ''
        const validForm = !publicDonor || checkRequiredVotingFieds([firstNameField, lastNameField, emailField, streetField, houseNumberField, postalCodeField, cityField, countryField, customField]);
        const metadata = {
            donorName: `${firstNameField.value} ${lastNameField?.value ?? ''}`,
            donorEmail: emailField?.value,
            donorAddress: address != ' ,  , ' ? address : '',
            donorCustom: customContent,
            formType: 'Bitcoin Voting',
            amount: `${amount} sats`,
            publicDonor: publicDonor || 0,
            modal: true,
            optionId: option,
            option: optionName,
            pollId: pollId
        }
        if (!validForm) return;

        showVotingElementById('payment-loading', 'flex', 'coinsnap-bitcoin-voting-', pollId)
        hideVotingElementById('public-donor-popup', 'coinsnap-bitcoin-voting-', pollId)


        const res = await createVotingInvoice(amount, 'VOTED for {IDK}', 'SATS', undefined, 'Bitcoin Voting', false, metadata)

        if (res) {
            // Update addresses 
            const qrLightning = res.lightningInvoice
            const qrBitcoin = res.onchainAddress

            if (qrBitcoin) {
                showVotingElementsById(['btc-wrapper', 'qr-btc-container'], 'flex', 'coinsnap-bitcoin-voting-', pollId)
            }

            // Hide spinner and show qr code stuff
            showVotingElementsById(['qrCode', 'lightning-wrapper', 'qr-fiat', 'qrCodeBtc'], 'block', 'coinsnap-bitcoin-voting-', pollId)
            showVotingElementsById(['qr-summary', 'qr-lightning-container', 'pay-in-wallet'], 'flex', 'coinsnap-bitcoin-voting-', pollId)
            hideVotingElementById('payment-loading', 'coinsnap-bitcoin-voting-', pollId)
            showVotingElementById('payment-popup', 'flex', 'coinsnap-bitcoin-voting-', pollId)
            // Update actuall data
            document.getElementById(`coinsnap-bitcoin-voting-qrCode${pollId}`).src = res.qrCodes.lightningQR;
            document.getElementById(`coinsnap-bitcoin-voting-qr-lightning${pollId}`).textContent = `${qrLightning.substring(0, 20)}...${qrLightning.slice(-15)}`;
            document.getElementById(`coinsnap-bitcoin-voting-qr-btc${pollId}`).textContent = `${qrBitcoin.substring(0, 20)}...${qrBitcoin.slice(-15)}`;
            document.getElementById(`coinsnap-bitcoin-voting-qr-amount${pollId}`).textContent = `Amount: ${res.amount} sats`;

            // Copy address functionallity 
            const copyLightning = document.querySelector(`#coinsnap-bitcoin-voting-qr-lightning-container${pollId} .qr-copy-icon`);
            const copyBtc = document.querySelector(`#coinsnap-bitcoin-voting-qr-btc-container${pollId} .qr-copy-icon`);
            copyLightning.addEventListener('click', () => { navigator.clipboard.writeText(qrLightning); });
            copyBtc.addEventListener('click', () => { navigator.clipboard.writeText(qrBitcoin); });

            // Add fiat amount
            if (exchangeRates['EUR']) {
                document.getElementById(`coinsnap-bitcoin-voting-qr-fiat${pollId}`).textContent = `≈ ${res.amount * exchangeRates['EUR']} EUR`;
                // Store the handler function when adding the listener
                walletHandler = function () {
                    window.location.href = `lightning:${qrLightning}`;
                };
                document.getElementById(`coinsnap-bitcoin-voting-pay-in-wallet${pollId}`).addEventListener('click', walletHandler);
            }

            // Reset retry counter
            var retryNum = 0;
            retryId = res.id

            const checkPaymentStatus = () => {
                fetch(`/wp-json/voting/v1/check-payment-status/${res.id}`)
                    .then(response => response.json())
                    .then(data => {
                        const qrContainer = document.getElementById(`coinsnap-bitcoin-voting-qr-container${pollId}`);
                        if (data.status === 'completed') {
                            showVotingElementById('thank-you-popup', 'flex', 'coinsnap-bitcoin-voting-', pollId)
                            hideVotingElementById('payment-popup', 'coinsnap-bitcoin-voting-', pollId)
                            setCookie(`coinsnap_poll_${pollId}`, option, 30 * 24 * 60);
                            setTimeout(() => {
                                resetPopup(pollId);
                                document.getElementById(`check-results${pollId}`).click();
                            }, 2000);

                        } else if (qrContainer.style.display != 'flex') {
                            retryId = '';
                        }
                        else if (retryNum < 180 && retryId == res.id) {
                            retryNum++;
                            checkPaymentStatus();
                        } else {
                            //TODO Invoice expired
                        }
                    })
                    .catch(error => {
                        console.error('Error checking payment status:', error);
                        retryNum++;
                        if (retryId == res.id) {
                            setTimeout(checkPaymentStatus, 5000);
                        }
                    });
            }
            checkPaymentStatus()

        }
        else {
            console.error('Error creating invoice')
        }

    });
}

const addVotingPopupListener = (button, publicDonor, amount, pollId) => {
    button.addEventListener('click', async () => {
        button.disabled = true;
        event.preventDefault();
        voteButton = button
        if (publicDonor != 1) {
            const publicDonorsPay = document.getElementById(`coinsnap-bitcoin-voting-public-donors-pay${pollId}`);
            publicDonorsPay.click()
        }
        showVotingElementsById(['blur-overlay', 'qr-container'], 'flex', 'coinsnap-bitcoin-voting-', pollId)
    });


}
