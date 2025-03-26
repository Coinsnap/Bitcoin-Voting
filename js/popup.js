// js/popup.js
var voteButton = null;
var walletHandler = null;

const checkRequiredFieds = (fields) => {
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

const resetPopup = () => {
    hideElementsById(['qr-container', 'blur-overlay', 'payment-loading', 'payment-popup', 'thank-you-popup'], 'bitcoin-voting-', '')
    showElementById('public-donor-popup', 'flex', 'bitcoin-voting-', '')
    voteButton.disabled = false;
    const payInWalletBtn = document.getElementById(`bitcoin-voting-pay-in-wallet`);
    if (walletHandler) {
        payInWalletBtn.removeEventListener('click', walletHandler);
        walletHandler = null;
    }
}

const addWindowListeners = () => {
    window.addEventListener("click", function (event) {
        const qrContainer = document.getElementById(`bitcoin-voting-qr-container`);
        const element = event.target
        if (qrContainer.style.display == 'flex') {
            if (element.classList.contains('close-popup') || (!qrContainer.contains(event.target) && !element.id.includes('pay') && !element.classList.contains('poll-option'))) {
                resetPopup('bitcoin-voting-', '')
            }
        }
    });

}

const popupButtonListener = (exchangeRates, pollId, amount, publicDonor) => {

    document.getElementById(`bitcoin-voting-public-donors-pay`).addEventListener('click', async () => {
        event.preventDefault();
        var retryId = '';

        const option = voteButton.dataset.option
        const optionName = document.querySelector(`.poll-option[data-option='${option}']`)?.textContent
        const firstNameField = document.getElementById(`bitcoin-voting-first-name`);
        const lastNameField = document.getElementById(`bitcoin-voting-last-name`);
        const emailField = document.getElementById(`bitcoin-voting-donor-email`);
        const streetField = document.getElementById(`bitcoin-voting-street`);
        const houseNumberField = document.getElementById(`bitcoin-voting-house-number`);
        const postalCodeField = document.getElementById(`bitcoin-voting-postal`);
        const cityField = document.getElementById(`bitcoin-voting-town`);
        const countryField = document.getElementById(`bitcoin-voting-country`);
        const address = `${streetField?.value ?? ''} ${houseNumberField?.value ?? ''}, ${postalCodeField?.value ?? ''} ${cityField?.value ?? ''}, ${countryField?.value ?? ''}`;
        const customField = document.getElementById(`bitcoin-voting-custom`);
        const customNameField = document.getElementById(`bitcoin-voting-custom-name`);
        const customContent = customNameField?.textContent && customField?.value ? `${customNameField.textContent}: ${customField.value}` : ''
        const validForm = !publicDonor || checkRequiredFieds([firstNameField, lastNameField, emailField, streetField, houseNumberField, postalCodeField, cityField, countryField, customField]);
        console.log(address)
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

        showElementById('payment-loading', 'flex', 'bitcoin-voting-', '')
        hideElementById('public-donor-popup', 'bitcoin-voting-', '')


        const res = await createInvoice(amount, 'VOTED for {IDK}', 'SATS', undefined, 'Bitcoin Voting', false, metadata)

        if (res) {
            // Update addresses 
            const qrLightning = res.lightningInvoice
            const qrBitcoin = res.onchainAddress

            if (qrBitcoin) {
                showElementsById(['btc-wrapper', 'qr-btc-container'], 'flex', 'bitcoin-voting-', '')
            }

            // Hide spinner and show qr code stuff
            showElementsById(['qrCode', 'lightning-wrapper', 'qr-fiat', 'qrCodeBtc'], 'block', 'bitcoin-voting-', '')
            showElementsById(['qr-summary', 'qr-lightning-container', 'pay-in-wallet'], 'flex', 'bitcoin-voting-', '')
            hideElementById('payment-loading', 'bitcoin-voting-', '')
            showElementById('payment-popup', 'flex', 'bitcoin-voting-', '')
            // Update actuall data
            document.getElementById(`bitcoin-voting-qrCode`).src = res.qrCodes.lightningQR;
            document.getElementById(`bitcoin-voting-qr-lightning`).textContent = `${qrLightning.substring(0, 20)}...${qrLightning.slice(-15)}`;
            document.getElementById(`bitcoin-voting-qr-btc`).textContent = `${qrBitcoin.substring(0, 20)}...${qrBitcoin.slice(-15)}`;
            document.getElementById(`bitcoin-voting-qr-amount`).textContent = `Amount: ${res.amount} sats`;

            // Copy address functionallity 
            const copyLightning = document.querySelector(`#bitcoin-voting-qr-lightning-container .qr-copy-icon`);
            const copyBtc = document.querySelector(`#bitcoin-voting-qr-btc-container .qr-copy-icon`);
            copyLightning.addEventListener('click', () => { navigator.clipboard.writeText(qrLightning); });
            copyBtc.addEventListener('click', () => { navigator.clipboard.writeText(qrBitcoin); });

            // Add fiat amount
            if (exchangeRates['EUR']) {
                document.getElementById(`bitcoin-voting-qr-fiat`).textContent = `≈ ${res.amount * exchangeRates['EUR']} EUR`;
                // Store the handler function when adding the listener
                walletHandler = function () {
                    window.location.href = `lightning:${qrLightning}`;
                };
                document.getElementById(`bitcoin-voting-pay-in-wallet`).addEventListener('click', walletHandler);
            }

            // Reset retry counter
            var retryNum = 0;
            retryId = res.id

            const checkPaymentStatus = () => {
                fetch(`/wp-json/my-plugin/v1/check-payment-status/${res.id}`)
                    .then(response => response.json())
                    .then(data => {
                        const qrContainer = document.getElementById(`bitcoin-voting-qr-container`);

                        if (data.status === 'completed') {
                            showElementById('thank-you-popup', 'flex', 'bitcoin-voting-', '')
                            hideElementById('payment-popup', 'bitcoin-voting-', '')
                            document.getElementById('check-results').click();
                            setCookie(`coinsnap_poll_${pollId}`, option, 30 * 24 * 60);
                            setTimeout(() => {
                                resetPopup('bitcoin-voting-', '');
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

const addVotingPopupListener = (button, publicDonor) => {
    button.addEventListener('click', async () => {
        button.disabled = true;
        event.preventDefault();
        voteButton = button
        if (publicDonor != 1) {
            const publicDonorsPay = document.getElementById(`bitcoin-voting-public-donors-pay`)
            publicDonorsPay.click()
        }
        showElementsById(['blur-overlay', 'qr-container'], 'flex', 'bitcoin-voting-', '')
    });


}
