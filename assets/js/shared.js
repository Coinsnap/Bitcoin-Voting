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
        return null;
    }
}

/*
 * The server creates the invoice. The browser sends only the poll ID, the
 * option and the donor's form fields; it never sees an API key, and the price
 * comes from the poll's settings on the server.
 */
const createVotingInvoice = async (pollId, optionId, donor) => {
    try {
        const response = await fetch(Coinsnap_Bitcoin_Voting_sharedData.invoiceUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({}, donor || {}, {
                poll_id: pollId,
                option_id: optionId,
                page_url: window.location.href
            }))
        });
        const responseData = await response.json();
        if (!response.ok) {
            return { error: responseData?.message || 'Error' };
        }

        if (Coinsnap_Bitcoin_Voting_sharedData.provider === 'btcpay' && responseData.paymentLink) {
            const qrCodeImage = await generateQRCodeDataURL(responseData.paymentLink);
            responseData.qrCodes = { lightningQR: qrCodeImage || responseData.paymentLink };
        }
        return responseData;
    } catch (error) {
        return null;
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