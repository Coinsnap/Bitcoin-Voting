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
    hideVotingElementsById(['qr-container', 'blur-overlay', 'payment-loading', 'payment-popup', 'thank-you-popup'], 'coinsnap-bitcoin-voting-', id);
    showVotingElementById('public-donor-popup', 'flex', 'coinsnap-bitcoin-voting-', id);
    voteButton.disabled = false;
    const payInWalletBtn = document.getElementById(`coinsnap-bitcoin-voting-pay-in-wallet${id}`);
    if (walletHandler) {
        payInWalletBtn.removeEventListener('click', walletHandler);
        walletHandler = null;
    }
}

const addWindowListeners = () => {
    window.addEventListener("click", function (event){
        const votingForms = document.getElementsByClassName('coinsnap-bitcoin-voting-form');
        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            const pollId = votingForm.dataset.pollId;
            const qrContainer = document.getElementById(`coinsnap-bitcoin-voting-qr-container${pollId}`);
            const element = event.target;
            if (qrContainer.style.display === 'flex') {
                if(element.classList.contains('close-popup') || (!qrContainer.contains(event.target) && !element.id.includes('pay') && !element.classList.contains('poll-option'))){
                    resetPopup(pollId);

                }
            }
        }
    });

}

const popupButtonListener = (pollId, amount, amountFiat, currency, publicDonor) => {

    document.getElementById(`coinsnap-bitcoin-voting-public-donors-pay${pollId}`)?.addEventListener('click', async () => {
        event.preventDefault();
        var retryId = '';

        const option = voteButton.dataset.option;
        const optionName = document.querySelector(`.poll-option[data-option='${option}']`)?.textContent;
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
        const customContent = customNameField?.textContent && customField?.value ? `${customNameField.textContent}: ${customField.value}` : '';
        const validForm = !publicDonor || checkRequiredVotingFieds([firstNameField, lastNameField, emailField, streetField, houseNumberField, postalCodeField, cityField, countryField, customField]);
        const donor = {
            first_name: firstNameField?.value ?? '',
            last_name: lastNameField?.value ?? '',
            email: emailField?.value ?? '',
            street: streetField?.value ?? '',
            house_number: houseNumberField?.value ?? '',
            postal: postalCodeField?.value ?? '',
            town: cityField?.value ?? '',
            country: countryField?.value ?? '',
            custom: customField?.value ?? ''
        };
        
        if (!validForm){ return; }

        showVotingElementById('payment-loading', 'flex', 'coinsnap-bitcoin-voting-', pollId)
        hideVotingElementById('public-donor-popup', 'coinsnap-bitcoin-voting-', pollId)


        const res = await createVotingInvoice(pollId, option, donor);

        const errorBox = document.getElementById(`coinsnap-bitcoin-voting-invoice-error${pollId}`);
        if (errorBox) {
            errorBox.style.display = 'none';
        }
        if (!res || res.error) {
            hideVotingElementById('payment-loading', 'coinsnap-bitcoin-voting-', pollId);
            showVotingElementById('public-donor-popup', 'flex', 'coinsnap-bitcoin-voting-', pollId);
            if (errorBox) {
                errorBox.textContent = (res && res.error) || errorBox.dataset.defaultMessage || '';
                errorBox.style.display = 'block';
            }
            return;
        }

        if (res) {
            const view = votingPaymentView(res);
            const short = (text) => text.length > 40 ? `${text.substring(0, 20)}...${text.slice(-15)}` : text;

            hideVotingElementsById(['lightning-wrapper', 'qr-lightning-container', 'btc-wrapper', 'qr-btc-container', 'qrCode', 'qrCodeBtc', 'checkout-link'], 'coinsnap-bitcoin-voting-', pollId);
            if (view.lightning) {
                showVotingElementById('lightning-wrapper', 'block', 'coinsnap-bitcoin-voting-', pollId);
                showVotingElementById('qr-lightning-container', 'flex', 'coinsnap-bitcoin-voting-', pollId);
            }
            if (view.address) {
                showVotingElementsById(['btc-wrapper', 'qr-btc-container'], 'flex', 'coinsnap-bitcoin-voting-', pollId);
            }
            if (view.qr) {
                showVotingElementsById(['qrCode', 'qrCodeBtc'], 'block', 'coinsnap-bitcoin-voting-', pollId);
            }

            // Hide spinner and show qr code stuff
            showVotingElementById('qr-fiat', 'block', 'coinsnap-bitcoin-voting-', pollId);
            showVotingElementById('qr-summary', 'flex', 'coinsnap-bitcoin-voting-', pollId);
            hideVotingElementById('payment-loading', 'coinsnap-bitcoin-voting-', pollId);
            showVotingElementById('payment-popup', 'flex', 'coinsnap-bitcoin-voting-', pollId);

            // Update actual data
            document.getElementById(`coinsnap-bitcoin-voting-qrCode${pollId}`).src = view.qr;
            document.getElementById(`coinsnap-bitcoin-voting-qr-lightning${pollId}`).textContent = short(view.lightning);
            document.getElementById(`coinsnap-bitcoin-voting-qr-btc${pollId}`).textContent = short(view.address);
            document.getElementById(`coinsnap-bitcoin-voting-qr-amount${pollId}`).textContent = `Amount: ${res.amount} sats`;

            // Copy address functionallity 
            const copyLightning = document.querySelector(`#coinsnap-bitcoin-voting-qr-lightning-container${pollId} .qr-copy-icon`);
            const copyBtc = document.querySelector(`#coinsnap-bitcoin-voting-qr-btc-container${pollId} .qr-copy-icon`);
            copyLightning.onclick = () => { navigator.clipboard.writeText(view.lightning); };
            copyBtc.onclick = () => { navigator.clipboard.writeText(view.address); };

            // Add fiat amount
            document.getElementById(`coinsnap-bitcoin-voting-qr-fiat${pollId}`).textContent = `≈ ${amountFiat} ${currency}`;
            const payInWallet = document.getElementById(`coinsnap-bitcoin-voting-pay-in-wallet${pollId}`);
            if (view.walletHref) {
                payInWallet.setAttribute('href', view.walletHref);
                showVotingElementById('pay-in-wallet', 'flex', 'coinsnap-bitcoin-voting-', pollId);
            } else {
                payInWallet.removeAttribute('href');
                hideVotingElementById('pay-in-wallet', 'coinsnap-bitcoin-voting-', pollId);
            }
            const checkoutLink = document.getElementById(`coinsnap-bitcoin-voting-checkout-link${pollId}`);
            if (checkoutLink && view.checkoutLink && view.checkoutLink !== view.walletHref) {
                checkoutLink.setAttribute('href', view.checkoutLink);
                showVotingElementById('checkout-link', 'block', 'coinsnap-bitcoin-voting-', pollId);
            }

            // Reset retry counter
            var retryNum = 0;
            retryId = res.id

            const checkPaymentStatus = () => {
                fetch(`${Coinsnap_Bitcoin_Voting_sharedData.statusUrl}${encodeURIComponent(res.id)}`)
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
                            setTimeout(() => {
                                document.getElementById(`return-button${pollId}`).click();
                            }, 7000);


                        } else if (qrContainer.style.display !== 'flex') {
                            retryId = '';
                        }
                        else if (retryNum < 180 && retryId === res.id) {
                            retryNum++;
                            checkPaymentStatus();
                        } else {
                            //TODO Invoice expired
                        }
                    })
                    .catch(() => {
                        retryNum++;
                        if (retryId === res.id) {
                            setTimeout(checkPaymentStatus, 5000);
                        }
                    });
            }
            checkPaymentStatus();

        }

    });
};

const addVotingPopupListener = (button, publicDonor, amount, amountFiat, currency, pollId) => {
    button.addEventListener('click', async () => {
        button.disabled = true;
        event.preventDefault();
        voteButton = button;
        if (publicDonor !== 1) {
            const publicDonorsPay = document.getElementById(`coinsnap-bitcoin-voting-public-donors-pay${pollId}`);
            publicDonorsPay.click();
        }
        showVotingElementsById(['blur-overlay', 'qr-container'], 'flex', 'coinsnap-bitcoin-voting-', pollId);
    });
};
