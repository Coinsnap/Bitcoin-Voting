<?php if (!defined('ABSPATH')){ exit;} ?>

<div id="<?php echo esc_html($prefix); ?>qr-container<?php echo esc_html($sufix); ?>" class="qr-container" data-public-donors="<?php echo esc_attr($public_donors); ?>">
    <div class="close-popup">×</div>
    <div id="<?php echo esc_html($prefix); ?>public-donor-popup<?php echo esc_html($sufix); ?>" class="public-donor-popup">
        <h3 style="margin-bottom: 24px; font-weight: bold">Donor Information</h3>
        <form class="public-donor-form">
            <?php if ($first_name !== 'hidden'): ?>
                <label for="<?php echo esc_html($prefix); ?>first-name<?php echo esc_html($sufix); ?>">First Name</label>
                <input <?php echo $first_name === 'mandatory' ? 'required' : ''; ?>
                    type="text"
                    id="<?php echo esc_html($prefix); ?>first-name<?php echo esc_html($sufix); ?>"
                    placeholder="<?php echo $first_name === 'mandatory' ? 'Required' : 'Optional'; ?>">
            <?php endif; ?>

            <?php if ($last_name !== 'hidden'): ?>
                <label for="<?php echo esc_html($prefix); ?>last-name<?php echo esc_html($sufix); ?>">Last Name</label>
                <input <?php echo $last_name === 'mandatory' ? 'required' : ''; ?>
                    type="text"
                    id="<?php echo esc_html($prefix); ?>last-name<?php echo esc_html($sufix); ?>"
                    placeholder="<?php echo $last_name === 'mandatory' ? 'Required' : 'Optional'; ?>">
            <?php endif; ?>

            <?php if ($email !== 'hidden'): ?>
                <label for="<?php echo esc_html($prefix); ?>donor-email<?php echo esc_html($sufix); ?>">Email</label>
                <input <?php echo $email === 'mandatory' ? 'required' : ''; ?>
                    type="email"
                    id="<?php echo esc_html($prefix); ?>donor-email<?php echo esc_html($sufix); ?>"
                    placeholder="<?php echo $email === 'mandatory' ? 'Required' : 'Optional'; ?>">
            <?php endif; ?>

            <?php if ($address !== 'hidden'): ?>
                <label>Address</label>
                <div class="address-grid">
                    <div class="address-row">
                        <input <?php echo $address === 'mandatory' ? 'required' : ''; ?>
                            type="text"
                            id="<?php echo esc_html($prefix); ?>street<?php echo esc_html($sufix); ?>"
                            placeholder="Street"
                            style="width: 50%;">
                        <input <?php echo $address === 'mandatory' ? 'required' : ''; ?>
                            type="text"
                            id="<?php echo esc_html($prefix); ?>house-number<?php echo esc_html($sufix); ?>"
                            placeholder="No."
                            style="width: 24%;">
                        <input <?php echo $address === 'mandatory' ? 'required' : ''; ?>
                            type="text"
                            id="<?php echo esc_html($prefix); ?>postal<?php echo esc_html($sufix); ?>"
                            placeholder="ZIP"
                            style="width: 26%;">

                    </div>
                    <div class="address-row">
                        <input <?php echo $address === 'mandatory' ? 'required' : ''; ?>
                            type="text"
                            id="<?php echo esc_html($prefix); ?>town<?php echo esc_html($sufix); ?>"
                            placeholder="Town"
                            style="width: 50%;">
                        <input <?php echo $address === 'mandatory' ? 'required' : ''; ?>
                            type="text"
                            id="<?php echo esc_html($prefix); ?>country<?php echo esc_html($sufix); ?>"
                            placeholder="Country"
                            style="width: 50%;">
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($custom !== 'hidden'): ?>
                <label id="<?php echo esc_html($prefix); ?>custom-name<?php echo esc_html($sufix); ?>" for="<?php echo esc_html($prefix); ?>custom<?php echo esc_html($sufix); ?>"><?php echo esc_html($custom_name); ?></label>
                <input <?php echo $custom === 'mandatory' ? 'required' : ''; ?>
                    type="text"
                    id="<?php echo esc_html($prefix); ?>custom<?php echo esc_html($sufix); ?>"
                    placeholder="<?php echo $custom === 'mandatory' ? 'Required' : 'Optional'; ?>">
            <?php endif; ?>
            <button type="submit" id="<?php echo esc_html($prefix); ?>public-donors-pay<?php echo esc_html($sufix); ?>">Pay</button>
        </form>
    </div>
    <div id="<?php echo esc_html($prefix); ?>payment-loading<?php echo esc_html($sufix); ?>" class="payment-loading">
        <div id="<?php echo esc_html($prefix); ?>qr-spinner<?php echo esc_html($sufix); ?>" class="loader qr-spinner"></div>
    </div>
    <div id="<?php echo esc_html($prefix); ?>payment-popup<?php echo esc_html($sufix); ?>" class="payment-popup">
        <h4 class="qr-amount" id="<?php echo esc_html($prefix); ?>qr-amount<?php echo esc_html($sufix); ?>"></h4>
        <p class="qr-fiat" id="<?php echo esc_html($prefix); ?>qr-fiat<?php echo esc_html($sufix); ?>"></p>
        <div style="position: relative;">
            <img class="qr-code" style="display: none;" id="<?php echo esc_html($prefix); ?>qrCode<?php echo esc_html($sufix); ?>" alt="QR Code">
            <img class="qr-code-btc" id="<?php echo esc_html($prefix); ?>qrCodeBtc<?php echo esc_html($sufix); ?>" alt="QR Code Btc" src="<?php echo esc_url(COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/images/bitcoinqr.svg'); ?>">
        </div>
        <details open class="qr-details">
            <summary id="<?php echo esc_html($prefix); ?>qr-summary<?php echo esc_html($sufix); ?>" class="qr-summary">Details <span class="qr-dropdown">&#9660;</span></summary>
            <div class="qr-address-wrapper" id="<?php echo esc_html($prefix); ?>lightning-wrapper<?php echo esc_html($sufix); ?>" style="display: none; margin-top:8px">
                <div class="qr-address-title">
                    Lightning:
                </div>
                <div id="<?php echo esc_html($prefix); ?>qr-lightning-container<?php echo esc_html($sufix); ?>" style="display: none;" class="qr-lightning-container">
                    <div class="qr-lightning" id="<?php echo esc_html($prefix); ?>qr-lightning<?php echo esc_html($sufix); ?>"></div>
                    <svg class="qr-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8f979e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                </div>
            </div>
            <div id="<?php echo esc_html($prefix); ?>btc-wrapper<?php echo esc_html($sufix); ?>" style="display: none; margin-top:12px" class="qr-address-wrapper">
                <div class="qr-address-title">
                    Address:
                </div>
                <div id="<?php echo esc_html($prefix); ?>qr-btc-container<?php echo esc_html($sufix); ?>" style="display: none;" class="qr-lightning-container">
                    <div class="qr-lightning" id="<?php echo esc_html($prefix); ?>qr-btc<?php echo esc_html($sufix); ?>"></div>
                    <svg class="qr-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8f979e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                </div>
            </div>
        </details>
        <a id="<?php echo esc_html($prefix);?>pay-in-wallet<?php echo esc_html($sufix);?>" class="qr-pay-in-wallet"><?php echo esc_html__('Pay in wallet','coinsnap-bitcoin-voting'); ?></a>
    </div>
    <div id="<?php echo esc_html($prefix); ?>thank-you-popup<?php echo esc_html($sufix); ?>" class="thank-you-popup">
        <img class="checkmark-img" id="checkmark" alt="Checkmark" src="<?php echo esc_url(COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/images/checkmark.svg'); ?>">
        <h3 style="margin: 10px 0 0 0;"><?php echo esc_html($thank_you); ?></h3>
    </div>
</div>