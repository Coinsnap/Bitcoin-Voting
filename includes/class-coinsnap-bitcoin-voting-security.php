<?php
if (!defined('ABSPATH')){ exit; }

/**
 * Server-side payment security for Coinsnap Bitcoin Voting.
 *
 * Follows the Coinsnap Plugin Security Guide: secrets stay on the server, a
 * payment counts only after the Coinsnap or BTCPay API confirms it, and each
 * invoice is acted on once.
 */
class Coinsnap_Bitcoin_Voting_Security {

    const OPTIONS        = 'coinsnap_bitcoin_voting_options';
    const SECRETS_OPTION = 'coinsnap_bitcoin_voting_webhook_secrets';
    const STATE_KEY      = 'coinsnap_bitcoin_voting_btcpay_state';
    const UPGRADED_AT    = 'coinsnap_bitcoin_voting_security_upgraded_at';
    const LEGACY_WINDOW  = DAY_IN_SECONDS;
    const INVOICE_TYPE   = 'Coinsnap Bitcoin Voting';

    /** The code the browser used to send; the Donations list filters invoices by it. */
    const REFERRAL_CODE  = 'D19833';

    const BTCPAY_PERMISSIONS = [
        'btcpay.store.cancreateinvoice',
        'btcpay.store.canviewinvoices',
        'btcpay.store.canviewstoresettings',
        'btcpay.store.webhooks.canmodifywebhooks',
    ];

    /* ---------------------------------------------------------------------
     * Configuration
     * ------------------------------------------------------------------- */

    public static function config(): array {
        $o = (array) get_option(self::OPTIONS, []);
        $provider = (($o['provider'] ?? '') === 'btcpay') ? 'btcpay' : 'coinsnap';
        if ($provider === 'btcpay') {
            return [
                'provider' => 'btcpay',
                'url'      => rtrim((string) ($o['btcpay_url'] ?? ''), '/'),
                'store_id' => (string) ($o['btcpay_store_id'] ?? ''),
                'api_key'  => (string) ($o['btcpay_api_key'] ?? ''),
            ];
        }
        return [
            'provider' => 'coinsnap',
            'url'      => rtrim(COINSNAP_SERVER_URL, '/'),
            'store_id' => (string) ($o['coinsnap_store_id'] ?? ''),
            'api_key'  => (string) ($o['coinsnap_api_key'] ?? ''),
        ];
    }

    public static function is_configured(array $cfg): bool {
        return $cfg['url'] !== '' && $cfg['store_id'] !== '' && $cfg['api_key'] !== '';
    }

    /** The webhook URL comes from the saved site address, never from the request (6.3). */
    public static function webhook_url(): string {
        $o = (array) get_option(self::OPTIONS, []);
        $ngrok = (string) ($o['ngrok_url'] ?? '');
        $path = 'wp-json/coinsnap-bitcoin-voting/v1/webhook';
        if ($ngrok !== '' && strpos(get_site_url(), 'localhost') !== false) {
            return rtrim($ngrok, '/') . '/' . $path;
        }
        return rtrim(home_url('/'), '/') . '/' . $path;
    }

    /* ---------------------------------------------------------------------
     * Webhook secrets: one per provider and store, under this plugin's own name (1, 6.4)
     * ------------------------------------------------------------------- */

    private static function secret_key(string $provider, string $storeId): string {
        return $provider . ':' . $storeId;
    }

    public static function get_secret(array $cfg): string {
        $all = get_option(self::SECRETS_OPTION, []);
        $key = self::secret_key($cfg['provider'], $cfg['store_id']);
        return (is_array($all) && isset($all[$key]['secret']) && is_string($all[$key]['secret'])) ? $all[$key]['secret'] : '';
    }

    private static function save_secret(array $cfg, string $secret, string $webhookId): void {
        $all = get_option(self::SECRETS_OPTION, []);
        if (!is_array($all)) {
            $all = [];
        }
        $all[self::secret_key($cfg['provider'], $cfg['store_id'])] = ['secret' => $secret, 'webhook_id' => $webhookId];
        update_option(self::SECRETS_OPTION, $all, false);
    }

    /* ---------------------------------------------------------------------
     * API calls
     * ------------------------------------------------------------------- */

    private static function headers(array $cfg): array {
        $h = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($cfg['provider'] === 'btcpay') {
            $h['Authorization'] = 'token ' . $cfg['api_key'];
        } else {
            $h['x-api-key'] = $cfg['api_key'];
        }
        return $h;
    }

    /**
     * @return array{code:int, body:mixed} code 0 = network error
     */
    private static function api(array $cfg, string $method, string $path, ?array $body = null): array {
        $args = ['method' => $method, 'timeout' => 15, 'headers' => self::headers($cfg)];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_request($cfg['url'] . '/api/v1/stores/' . rawurlencode($cfg['store_id']) . $path, $args);
        if (is_wp_error($response)) {
            return ['code' => 0, 'body' => null];
        }
        return [
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => json_decode((string) wp_remote_retrieve_body($response), true),
        ];
    }

    /**
     * Fetch an invoice. Returns the invoice, null if it doesn't exist, or throws
     * so the webhook replies 503 and the provider retries.
     */
    public static function fetch_invoice(array $cfg, string $invoiceId): ?array {
        $r = self::api($cfg, 'GET', '/invoices/' . rawurlencode($invoiceId));
        if ($r['code'] === 0 || $r['code'] >= 500) {
            throw new RuntimeException('API unavailable');
        }
        if ($r['code'] !== 200) {
            return null; // Coinsnap 400 / BTCPay 404: unknown invoice, e.g. a dashboard test webhook
        }
        return is_array($r['body']) ? $r['body'] : null;
    }

    /**
     * Create an invoice from server-side data only.
     * Returns the fields the browser may see, or null on failure.
     */
    public static function create_invoice(array $cfg, int $sats, array $metadata, string $buyerEmail, string $redirectUrl = ''): ?array {
        $orderId = 'VTNG_' . bin2hex(random_bytes(8));
        $fields = [
            'amount'                => $sats,
            'currency'              => 'SATS',
            'orderId'               => $orderId,
            'redirectAutomatically' => true,
            'metadata'              => array_merge($metadata, ['orderId' => $orderId]),
            'checkout'              => ['redirectAutomatically' => true],
        ];
        if ($redirectUrl !== '') {
            $fields['redirectUrl'] = $redirectUrl;
            $fields['checkout']['redirectUrl'] = $redirectUrl;
        }
        if ($buyerEmail !== '') {
            $fields['buyerEmail'] = $buyerEmail;
        }
        if ($cfg['provider'] === 'coinsnap') {
            $fields['referralCode'] = self::REFERRAL_CODE;
        }

        $r = self::api($cfg, 'POST', '/invoices', $fields);
        $inv = $r['body'];
        if ($r['code'] !== 200 || !is_array($inv) || !is_string($inv['id'] ?? null)) {
            self::log('info', 'invoice create failed');
            return null;
        }

        $out = [
            'id'               => $inv['id'],
            'amount'           => $sats,
            'checkoutLink'     => (string) ($inv['checkoutLink'] ?? ''),
            'lightningInvoice' => (string) ($inv['lightningInvoice'] ?? ''),
            'onchainAddress'   => (string) ($inv['onchainAddress'] ?? ''),
            'qrCodes'          => [
                'lightningQR' => (string) ($inv['qrCodes']['lightningQR'] ?? ''),
                'onchainQR'   => (string) ($inv['qrCodes']['onchainQR'] ?? ''),
            ],
            'paymentLink'      => '',
        ];

        if ($cfg['provider'] === 'btcpay') {
            $pm = self::api($cfg, 'GET', '/invoices/' . rawurlencode($inv['id']) . '/payment-methods');
            if ($pm['code'] === 200 && is_array($pm['body'])) {
                foreach ($pm['body'] as $method) {
                    $link = (string) ($method['paymentLink'] ?? '');
                    if ($link !== '' && stripos($link, 'lightning:') === 0) {
                        $out['paymentLink'] = $link;
                        $out['lightningInvoice'] = substr($link, strlen('lightning:'));
                        break;
                    }
                    if ($out['paymentLink'] === '' && $link !== '') {
                        $out['paymentLink'] = $link;
                    }
                }
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Poll price: always from the poll's own settings, never from the browser (3)
     * ------------------------------------------------------------------- */

    /**
     * The price of one vote in sats at the current rate.
     * Returns ['result' => true, 'sats' => int, 'amount' => float, 'currency' => string]
     * or ['result' => false, 'error' => string, 'min_value' => mixed].
     */
    public static function poll_price(int $pollId, array $cfg): array {
        $amount = (float) get_post_meta($pollId, '_coinsnap_bitcoin_voting_polls_amount', true);
        $currency = strtoupper((string) get_post_meta($pollId, '_coinsnap_bitcoin_voting_polls_currency', true));
        if ($currency === '') {
            $currency = 'EUR';
        }
        if ($amount <= 0) {
            return ['result' => false, 'error' => 'amountError', 'min_value' => ''];
        }

        $client = new Coinsnap_Bitcoin_Voting_Client();
        $kind = 'coinsnap';
        if ($cfg['provider'] === 'btcpay') {
            $methods = $client->getStorePaymentMethods($cfg['url'], $cfg['api_key'], $cfg['store_id']);
            $kind = '';
            if (($methods['code'] ?? 0) === 200) {
                if (!empty($methods['result']['lightning'])) {
                    $kind = 'lightning';
                } elseif (!empty($methods['result']['onchain'])) {
                    $kind = 'bitcoin';
                }
            }
            if ($kind === '') {
                return ['result' => false, 'error' => 'paymentMethodError', 'min_value' => ''];
            }
        }

        $check = $client->checkPaymentData($amount, $currency, $kind);
        if (empty($check['result']) || !isset($check['rate'])) {
            return ['result' => false, 'error' => (string) ($check['error'] ?? 'error'), 'min_value' => $check['min_value'] ?? ''];
        }
        $sats = (int) round($amount * (float) $check['rate'] * 100000000);
        if ($sats < 1) {
            return ['result' => false, 'error' => 'amountError', 'min_value' => ''];
        }
        return ['result' => true, 'sats' => $sats, 'amount' => $amount, 'currency' => $currency];
    }

    /* ---------------------------------------------------------------------
     * Webhook registration, on the server only (1, 6.5)
     * ------------------------------------------------------------------- */

    /**
     * Create or update this plugin's webhook with a new secret.
     * Callers check capability and nonce first.
     */
    public static function register_webhook(): bool {
        $cfg = self::config();
        if (!self::is_configured($cfg)) {
            return false;
        }
        $url = self::webhook_url();

        $list = self::api($cfg, 'GET', '/webhooks');
        if ($list['code'] !== 200 || !is_array($list['body'])) {
            // An API error is never "no webhook": don't create a second one.
            self::log('info', 'webhook list failed');
            return false;
        }
        $existingId = '';
        foreach ($list['body'] as $wh) {
            if (is_array($wh) && ($wh['url'] ?? '') === $url) {
                $existingId = (string) ($wh['id'] ?? '');
                break;
            }
        }

        $secret = bin2hex(random_bytes(32));
        $body = ($cfg['provider'] === 'btcpay')
            ? [
                'url' => $url,
                'enabled' => true,
                'automaticRedelivery' => true,
                'authorizedEvents' => ['everything' => false, 'specificEvents' => ['InvoiceSettled']],
                'secret' => $secret,
            ]
            : ['url' => $url, 'events' => ['Settled'], 'secret' => $secret];

        $r = ($existingId !== '')
            ? self::api($cfg, 'PUT', '/webhooks/' . rawurlencode($existingId), $body)
            : self::api($cfg, 'POST', '/webhooks', $body);

        if ($r['code'] !== 200) {
            self::log('info', 'webhook register failed');
            return false;
        }
        $id = $existingId !== '' ? $existingId : (string) ($r['body']['id'] ?? '');
        self::save_secret($cfg, $secret, $id);
        return true;
    }

    /* ---------------------------------------------------------------------
     * Webhook signature (handler step 1)
     * ------------------------------------------------------------------- */

    /**
     * Use as the webhook route's permission_callback. Logs why it failed.
     * WordPress calls it again for the Allow header, so the result is kept per request.
     */
    public static function check_webhook_signature(WP_REST_Request $request): bool {
        static $checked = null;
        $checked = $checked ?? new WeakMap();
        if (!isset($checked[$request])) {
            $checked[$request] = self::verify_signature($request);
        }
        return $checked[$request];
    }

    private static function verify_signature(WP_REST_Request $request): bool {
        $cfg = self::config();
        $secret = self::get_secret($cfg);
        $body = $request->get_body();

        if ($cfg['provider'] === 'btcpay') {
            $header = (string) $request->get_header('BTCPay-Sig');
            $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
            if ($secret === '' || $header === '' || !hash_equals($expected, $header)) {
                self::log('security', 'bad signature');
                return false;
            }
            return true;
        }

        $header = (string) $request->get_header('X-Coinsnap-Signature');
        parse_str(str_replace(',', '&', $header), $p);
        $t = (int) ($p['t'] ?? 0);
        $expected = hash_hmac('sha256', $t . '.' . $body, $secret);
        if ($secret === '' || $t <= 0 || !hash_equals($expected, (string) ($p['v1'] ?? ''))) {
            self::log('security', 'bad signature');
            return false;
        }
        if (abs(time() - $t) > 300) {
            self::log('security', 'stale timestamp');
            return false;
        }
        return true;
    }

    /* ---------------------------------------------------------------------
     * When is a vote paid (3)
     * ------------------------------------------------------------------- */

    /**
     * Paid only if Settled, not marked by hand, the same currency, and the amount
     * the plugin recorded when it created the invoice. Invoices are in SATS.
     */
    public static function invoice_pays(array $invoice, int $expectedSats, string $currency, bool $isBtcpay): bool {
        if ($expectedSats <= 0 || ($invoice['status'] ?? '') !== 'Settled') {
            return false;
        }
        if ($isBtcpay && ($invoice['additionalStatus'] ?? '') === 'Marked') {
            return false;
        }
        if (strtoupper((string) ($invoice['currency'] ?? '')) !== strtoupper($currency) || strtoupper($currency) !== 'SATS') {
            return false;
        }
        $paid = (string) ($invoice['amount'] ?? '');
        if (!preg_match('/^\d+(?:\.0+)?$/', $paid)) {
            return false;
        }
        return (int) $paid === $expectedSats;
    }

    /* ---------------------------------------------------------------------
     * BTCPay connect callback and key permissions
     * ------------------------------------------------------------------- */

    /** Called on the admin page (capability and nonce checked there). */
    public static function btcpay_start_state(): string {
        $state = bin2hex(random_bytes(32));
        set_transient(self::STATE_KEY, hash('sha256', $state), 10 * MINUTE_IN_SECONDS);
        return $state;
    }

    /** One use, even if the check fails. */
    public static function btcpay_consume_state(string $state): bool {
        $expected = get_transient(self::STATE_KEY);
        delete_transient(self::STATE_KEY);
        return is_string($expected) && $state !== '' && hash_equals($expected, hash('sha256', $state));
    }

    /** Ask BTCPay what the key can do; never trust permissions from the POST. */
    public static function btcpay_key_permissions(string $btcpayUrl, string $apiKey): ?array {
        if ($btcpayUrl === '' || $apiKey === '') {
            return null;
        }
        $response = wp_remote_get(rtrim($btcpayUrl, '/') . '/api/v1/api-keys/current', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'token ' . $apiKey, 'Accept' => 'application/json'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $key = json_decode((string) wp_remote_retrieve_body($response), true);
        return (is_array($key) && is_array($key['permissions'] ?? null)) ? array_values($key['permissions']) : null;
    }

    /** The single store this key is limited to, or '' if it isn't. */
    public static function btcpay_key_store(array $permissions): string {
        $first = explode(':', (string) ($permissions[0] ?? ''), 2);
        return $first[1] ?? '';
    }

    /** Every permission is a store permission for this store, and the needed ones are there. */
    public static function btcpay_key_is_acceptable(array $permissions, string $storeId): bool {
        if ($permissions === [] || $storeId === '') {
            return false;
        }
        foreach ($permissions as $permission) {
            [$policy, $scope] = array_pad(explode(':', (string) $permission, 2), 2, '');
            if (strpos($policy, 'btcpay.store.') !== 0 || $scope !== $storeId) {
                return false;
            }
        }
        foreach (['btcpay.store.cancreateinvoice', 'btcpay.store.canviewinvoices', 'btcpay.store.webhooks.canmodifywebhooks'] as $needed) {
            if (!in_array($needed . ':' . $storeId, $permissions, true)) {
                return false;
            }
        }
        return true;
    }

    /* ---------------------------------------------------------------------
     * Settings display
     * ------------------------------------------------------------------- */

    public static function secret_hint(string $value): string {
        return $value === '' ? '' : '••••••••' . substr($value, -4);
    }

    /* ---------------------------------------------------------------------
     * Logs: a fixed reason and the invoice ID, nothing else (5, 6.13)
     * ------------------------------------------------------------------- */

    public static function log(string $level, string $reason, string $invoiceId = ''): void {
        if ($level === 'security' && !self::security_log_allowed()) {
            return;
        }
        $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceId), 0, 64);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf('[coinsnap-bitcoin-voting] %s webhook reason=%s invoice=%s', strtoupper($level), $reason, $id !== '' ? $id : '-'));
    }

    private static function security_log_allowed(): bool {
        $key = 'coinsnap_bitcoin_voting_seclog_' . gmdate('YmdHi');
        $count = (int) get_transient($key) + 1;
        set_transient($key, $count, 2 * MINUTE_IN_SECONDS);
        if ($count === 11) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[coinsnap-bitcoin-voting] SECURITY webhook more rejections this minute, not logged');
        }
        return $count <= 10;
    }
}
