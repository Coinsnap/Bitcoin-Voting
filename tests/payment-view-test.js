// What the payment popup shows for an invoice. Run: node tests/payment-view-test.js
const assert = require('assert');
const { votingPaymentView } = require('../assets/js/shared.js');

const onchainOnly = {
    lightningInvoice: '',
    onchainAddress: 'bc1qgf6kc8n3kq4epjd7aytndrmu303v0pmcfpkun8',
    bip21: 'bitcoin:bc1qgf6kc8n3kq4epjd7aytndrmu303v0pmcfpkun8?amount=0.000006',
    checkoutLink: 'https://stores.coinsnap.io/i/abc',
    qrCodes: { lightningQR: '', bip21QR: 'data:image/png;base64,QklQMjE=' },
};
let v = votingPaymentView(onchainOnly);
assert.strictEqual(v.walletHref, onchainOnly.bip21, 'no Lightning: wallet link is the BIP21 link with the amount');
assert.strictEqual(v.qr, onchainOnly.qrCodes.bip21QR, 'no Lightning: BIP21 QR');
assert.strictEqual(v.lightning, '', 'no Lightning row');
assert.strictEqual(v.checkoutLink, onchainOnly.checkoutLink, 'checkout link offered');

v = votingPaymentView(Object.assign({}, onchainOnly, { lightningInvoice: 'lnbc6u1abc', qrCodes: { lightningQR: 'data:image/png;base64,TE4=', bip21QR: 'x' } }));
assert.strictEqual(v.walletHref, 'lightning:lnbc6u1abc', 'Lightning invoice: lightning link');
assert.strictEqual(v.qr, 'data:image/png;base64,TE4=', 'Lightning invoice: Lightning QR');

v = votingPaymentView({ lightningInvoice: '', bip21: '', onchainAddress: '', checkoutLink: 'https://stores.coinsnap.io/i/abc', qrCodes: {} });
assert.strictEqual(v.walletHref, 'https://stores.coinsnap.io/i/abc', 'nothing else: wallet button opens the checkout page');
assert.strictEqual(v.qr, '', 'no QR without a payment link');

v = votingPaymentView({ lightningInvoice: '', bip21: 'javascript:alert(1)', checkoutLink: 'javascript:alert(1)', qrCodes: {} });
assert.strictEqual(v.walletHref, '', 'only bitcoin: and http(s) links are used');

console.log('all passed');
