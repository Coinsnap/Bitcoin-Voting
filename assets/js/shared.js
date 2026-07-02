function deleteCookie(name) {
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/;';
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

function setCookie(name, value, minutes) {
    const d = new Date();
    d.setTime(d.getTime() + (minutes * 60 * 1000));
    const expires = "expires=" + d.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

async function generateQRCodeDataURL(text) {
    try {
        const response = await fetch(`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(text)}`);
        const blob = await response.blob();
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        });
    } catch (error) {
        console.error('Error generating QR code:', error);
        return null;
    }
}

const createActualVotingInvoice = async (amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata) => {
    deleteCookie('coinsnap_invoice_voting');

    var orderId = 'VTNG_' + (Date.now()).toString(36);
    
    var provider = (coinsnap === true)? 'coinsnap' : 'btcpay';
    
    if(provider === 'btcpay'){
        metadata.orderId = orderId;
    }

    const requestData = {
        amount: amount,
        currency: lastInputCurrency,
        buyerEmail: metadata.donorEmail,
        redirectAutomatically: true,
        checkout: {
            redirectAutomatically: true
        },
        orderId: orderId,
        walletMessage: message,
        metadata: {
            orderNumber: message,
            referralCode: 'D19833',
            type: type,
            name: name,
            ...metadata
        }
    };
    
    requestData.provider = (provider === 'coinsnap')? 'coinsnap' : 'btcpay';
    
    if (type === 'Coinsnap Bitcoin Voting') {
        const confirmationUrl = Coinsnap_Bitcoin_Voting_sharedData?.confirmation_url;
        if (confirmationUrl) {
            const cbvParams = new URLSearchParams({
                amount:     metadata.amount || '',
                currency:   'SATS',
                option:     metadata.option || '',
                poll_id:    metadata.pollId || '',
                poll_title: metadata.pollTitle || '',
            });
            requestData.redirectUrl = requestData.checkout.redirectUrl = confirmationUrl.replace(/\/$/, '') + '?' + cbvParams.toString();
        } else {
            requestData.redirectUrl = requestData.checkout.redirectUrl = window.location.href;
        }
        requestData.metadata.orderNumber = `Voted for ${metadata.option}`;
    }
    
    if (window.location.href.includes("localhost")) {
        requestData.redirectUrl = requestData.checkout.redirectUrl = "https://coinsnap.io";
    }

    if (provider === 'coinsnap') {
        requestData.referralCode = 'D19833';
    }

    const url = (provider === 'coinsnap')
        ? `https://app.coinsnap.io/api/v1/stores/${Coinsnap_Bitcoin_Voting_sharedData?.coinsnapStoreId}/invoices`
        : `${Coinsnap_Bitcoin_Voting_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Voting_sharedData?.btcpayStoreId}/invoices`;

    const headers = (provider === 'coinsnap')
        ? {
            'x-api-key': Coinsnap_Bitcoin_Voting_sharedData?.coinsnapApiKey,
            'Content-Type': 'application/json'
        }
        : {
            'Authorization': 'token '+Coinsnap_Bitcoin_Voting_sharedData?.btcpayApiKey,
            'Content-Type': 'application/json'
        };

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(requestData)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        var responseData = await response.json();

        const invoiceCookieData = {
            id: responseData.id,
            amount: amount,
            currency: lastInputCurrency,
            checkoutLink: responseData.checkoutLink,
            message: message,
            name: name
        };

        setCookie('coinsnap_invoice_voting', JSON.stringify(invoiceCookieData), 15);
        if (provider === 'btcpay') {
            const url = `${Coinsnap_Bitcoin_Voting_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Voting_sharedData?.btcpayStoreId}/invoices/${responseData.id}/payment-methods`;
            const response2 = await fetch(url, {
                method: 'GET',
                headers: headers
            });
            const responseData2 = await response2.json();
            const paymentLink = responseData2[0].paymentLink;
            responseData.lightningInvoice = paymentLink?.replace('lightning:', '');
            responseData.onchainAddress = '';

            // Generate QR code image from lightning invoice
            const qrCodeImage = await generateQRCodeDataURL(paymentLink);
            responseData.qrCodes = {
                lightningQR: qrCodeImage || paymentLink
            };
        }
        if (redirect) {
            window.location.href = responseData.checkoutLink;
        }

        return responseData;
    } catch (error) {
        console.error('Error creating invoice:', error);
        return null;
    }
};

const checkVotingInvoiceStatus = async (invoiceId, amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata) => {

    var provider = (coinsnap === true)? 'coinsnap' : 'btcpay';
    
    const url = (provider === 'coinsnap')
        ? `https://app.coinsnap.io/api/v1/stores/${Coinsnap_Bitcoin_Voting_sharedData.coinsnapStoreId}/invoices/${invoiceId}`
        : `${Coinsnap_Bitcoin_Voting_sharedData.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Voting_sharedData.btcpayStoreId}/invoices/${invoiceId}`;

    const headers = (provider === 'coinsnap')
        ? {
            'x-api-key': Coinsnap_Bitcoin_Voting_sharedData.coinsnapApiKey,
            'Content-Type': 'application/json'

        }
        : {
            'Authorization': 'token '+Coinsnap_Bitcoin_Voting_sharedData.btcpayApiKey,
            'Content-Type': 'application/json'
        };

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: headers
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        var responseData = await response.json();

        if (provider === 'btcpay') {
            const url = `${Coinsnap_Bitcoin_Voting_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Voting_sharedData?.btcpayStoreId}/invoices/${responseData.id}/payment-methods`;
            const response2 = await fetch(url, {
                method: 'GET',
                headers: headers
            });
            const responseData2 = await response2.json();
            const paymentLink = responseData2[0].paymentLink;
            responseData.lightningInvoice = paymentLink?.replace('lightning:', '');
            responseData.onchainAddress = '';

            // Generate QR code image from lightning invoice
            const qrCodeImage = await generateQRCodeDataURL(paymentLink);
            responseData.qrCodes = {
                lightningQR: qrCodeImage || paymentLink
            }
        }

        if (responseData?.status === 'Settled') {
            return await createActualVotingInvoice(amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata);
        } else if (responseData?.status === 'New') {
            if (redirect) {
                window.location.href = responseData.checkoutLink;
            }
            return responseData;
        }

    } catch (error) {
        console.error('Error creating invoice:', error);
        return null;
    }
};

//  Invoice creation
const createVotingInvoice = async (amount, message, amountFiat, lastInputCurrency, name, type, redirect = true, metadata) => {
    existingInvoice = getCookie('coinsnap_invoice_voting');
    lastInputCurrency = 'SATS';
    if (existingInvoice) {
        invoiceJson = JSON.parse(existingInvoice);
        if (
            invoiceJson.id &&
            invoiceJson.checkoutLink &&
            invoiceJson.amount === amount &&
            invoiceJson.currency === lastInputCurrency &&
            invoiceJson.message === message &&
            invoiceJson.name === name
        ) {
            const cs = await checkVotingInvoiceStatus(
                invoiceJson.id,
                amount,
                message,
                lastInputCurrency,
                name,
                Coinsnap_Bitcoin_Voting_sharedData.provider === 'coinsnap',
                type,
                redirect,
                metadata
            );
            return cs;
        }
        else {
            return await createActualVotingInvoice(
                amount,
                message,
                lastInputCurrency,
                name,
                Coinsnap_Bitcoin_Voting_sharedData.provider === 'coinsnap',
                type,
                redirect,
                metadata
            );
        }
    }
    else {
        return await createActualVotingInvoice(
            amount,
            message,
            lastInputCurrency,
            name,
            Coinsnap_Bitcoin_Voting_sharedData.provider === 'coinsnap',
            type,
            redirect,
            metadata
        );
    }
};

const hideVotingElementById = (id, prefix = '', sufix = '') => {
    document.getElementById(`${prefix}${id}${sufix}`).style.display = 'none'
}
const hideVotingElementsById = (ids, prefix = '', sufix = '') => {
    ids.forEach(id => {
        hideVotingElementById(id, prefix, sufix)
    })
};
const showVotingElementById = (id, display, prefix = '', sufix = '') => {
    document.getElementById(`${prefix}${id}${sufix}`).style.display = display;
};
const showVotingElementsById = (ids, display, prefix = '', sufix = '') => {
    ids.forEach(id => {
        showVotingElementById(id, display, prefix, sufix);
    })
};