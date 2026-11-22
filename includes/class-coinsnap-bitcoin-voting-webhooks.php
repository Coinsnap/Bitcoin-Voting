<?php
if (!defined('ABSPATH')){ exit; }
class Coinsnap_Bitcoin_Voting_Webhooks {

    public function __construct(){
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        add_action('rest_api_init', [$this, 'register_invoice_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_results_endpoint']);
        add_action('rest_api_init', [$this, 'register_check_payment_endpoint']);
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'voting_payments';
    }

    /** The options of a poll, keyed 1..4, from the poll's own settings. */
    private function poll_options(int $poll_id): array
    {
        $options = [];
        for ($i = 1; $i <= 4; $i++) {
            $option = (string) get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$i}", true);
            if ($option !== '') {
                $options[$i] = $option;
            }
        }
        return $options;
    }

    /* ---------------------------------------------------------------------
     * Public: poll results. Open on purpose, returns vote counts only.
     * ------------------------------------------------------------------- */

    public function register_poll_results_endpoint(){
        register_rest_route('voting/v1', '/voting_results/(?P<poll_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_results'],
            'permission_callback' => '__return_true', // public: returns only the number of votes per option
            'args' => [
                'poll_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
    }

    function get_results($request){
        $poll_id = (int) $request['poll_id'];
        if (get_post_type($poll_id) !== 'coinsnap-polls') {
            return new WP_Error('not_found', 'Not found', ['status' => 404]);
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_id, COUNT(*) AS votes FROM %i WHERE status = 'completed' AND poll_id = %s GROUP BY option_id",
            $this->table(), (string) $poll_id
        ), ARRAY_A);

        $results = [];
        $total = 0;
        foreach ((array) $rows as $row) {
            $votes = (int) $row['votes'];
            $results[] = ['option_id' => (int) $row['option_id'], 'votes' => $votes];
            $total += $votes;
        }
        return ['results' => $results, 'total' => $total];
    }

    /* ---------------------------------------------------------------------
     * Public: payment status. Open on purpose, returns paid yes/no only.
     * ------------------------------------------------------------------- */

    public function register_check_payment_endpoint()
    {
        register_rest_route('voting/v1', '/check-payment-status/(?P<payment_id>[A-Za-z0-9_-]{1,64})', [
            'methods' => 'GET',
            'callback' => [$this, 'get_check_payment_status'],
            'permission_callback' => '__return_true', // public: returns only the status of one invoice
        ]);
    }

    function get_check_payment_status($request)
    {
        global $wpdb;
        $payment_id = (string) $request['payment_id'];
        if ($this->confirm_from_api($payment_id)) {
            return ['status' => 'completed'];
        }
        $start_time = time();
        $timeout = 5;

        while (time() - $start_time < $timeout) {
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM %i WHERE payment_id = %s",
                $this->table(), $payment_id
            ));
            if ($status === 'completed') {
                return ['status' => 'completed'];
            }
            sleep(1);
        }
        return ['status' => 'pending'];
    }

    /**
     * Fallback for when no webhook arrives (e.g. it could not be registered): ask the
     * API about an invoice this plugin created and count the vote with the same checks
     * as the webhook. Only for pending rows in our table, at most every few seconds per
     * invoice, so the public route can't be used to hammer the API.
     */
    private function confirm_from_api(string $invoiceId): bool
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE payment_id = %s", $this->table(), $invoiceId), ARRAY_A);
        if (!$row) {
            return false;
        }
        if ($row['status'] === 'completed') {
            return true;
        }
        if ($row['status'] !== 'pending') {
            return false;
        }
        $throttle = 'coinsnap_bitcoin_voting_chk_' . md5($invoiceId);
        if (get_transient($throttle)) {
            return false;
        }
        set_transient($throttle, 1, 10);

        $cfg = Coinsnap_Bitcoin_Voting_Security::config();
        if (!Coinsnap_Bitcoin_Voting_Security::is_configured($cfg)) {
            return false;
        }
        try {
            $invoice = Coinsnap_Bitcoin_Voting_Security::fetch_invoice($cfg, $invoiceId);
        } catch (RuntimeException $e) {
            return false;
        }
        if ($invoice === null || ($invoice['id'] ?? '') !== $invoiceId) {
            return false;
        }
        return $this->complete_vote($row, $invoice, $cfg, false, false) === 'completed';
    }

    /**
     * Count the vote of a stored row if the invoice fetched from the API pays it.
     * Returns 'completed', 'already', 'not_ours' or 'unpaid'. Same rules for the webhook and the status check.
     */
    private function complete_vote(array $row, array $invoice, array $cfg, bool $fromWebhook, bool $claimsPaid): string
    {
        global $wpdb;
        $invoiceId = (string) $invoice['id'];
        $metaPoll = (string) ($invoice['metadata']['pollId'] ?? '');
        if ($metaPoll !== (string) $row['poll_id']) {
            Coinsnap_Bitcoin_Voting_Security::log('info', 'not ours', $invoiceId);
            return 'not_ours';
        }

        if (!Coinsnap_Bitcoin_Voting_Security::invoice_pays($invoice, (int) $row['amount'], (string) $row['currency'], $cfg['provider'] === 'btcpay')) {
            $forged = $claimsPaid && ($invoice['additionalStatus'] ?? '') !== 'Marked';
            if ($fromWebhook) {
                Coinsnap_Bitcoin_Voting_Security::log($forged ? 'security' : 'info',
                    $forged ? 'forged status' : 'status ' . preg_replace('/[^A-Za-z]/', '', (string) ($invoice['status'] ?? '?')), $invoiceId);
            }
            return 'unpaid';
        }

        // Act once, and only move pending -> completed: the vote counts once.
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE %i SET status = 'completed' WHERE payment_id = %s AND status = 'pending'",
            $this->table(), $invoiceId
        ));
        if ($updated !== 1) {
            Coinsnap_Bitcoin_Voting_Security::log('info', 'already processed', $invoiceId);
            return 'already';
        }

        $this->create_donor_post($row, $invoiceId);
        return 'completed';
    }

    /* ---------------------------------------------------------------------
     * Public: start a vote. The browser sends the poll ID, the option and the
     * donor's form fields; the server sets the price and creates the invoice.
     * ------------------------------------------------------------------- */

    public function register_invoice_endpoint()
    {
        register_rest_route('coinsnap-bitcoin-voting/v1', '/invoice', [
            'methods' => 'POST',
            'callback' => [$this, 'create_invoice'],
            'permission_callback' => '__return_true', // public: visitors vote without an account
        ]);
    }

    private function field(array $data, string $key, int $max = 255): string
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }
        return mb_substr(sanitize_text_field((string) $value), 0, $max);
    }

    public function create_invoice(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return new WP_Error('bad_request', 'Bad request', ['status' => 400]);
        }

        $poll_id = (int) ($data['poll_id'] ?? 0);
        if ($poll_id <= 0 || get_post_type($poll_id) !== 'coinsnap-polls' || get_post_status($poll_id) !== 'publish'
            || !get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_active', true)) {
            return new WP_Error('not_found', 'Not found', ['status' => 404]);
        }

        // Same open/closed rule as the shortcode.
        $now = current_time('timestamp');
        $start = strtotime((string) get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_starting_date', true));
        $end = strtotime((string) get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_ending_date', true));
        if (($start !== false && $now < $start) || ($end !== false && $now > $end)) {
            return new WP_Error('poll_closed', __('This poll is not open for voting', 'coinsnap-bitcoin-voting'), ['status' => 400]);
        }

        $options = $this->poll_options($poll_id);
        $option_id = (int) ($data['option_id'] ?? 0);
        if (!isset($options[$option_id])) {
            return new WP_Error('invalid_option', __('Invalid option', 'coinsnap-bitcoin-voting'), ['status' => 400]);
        }

        $cfg = Coinsnap_Bitcoin_Voting_Security::config();
        if (!Coinsnap_Bitcoin_Voting_Security::is_configured($cfg)) {
            return new WP_Error('unavailable', __('Voting is temporarily unavailable. Please try again later.', 'coinsnap-bitcoin-voting'), ['status' => 503]);
        }

        $price = Coinsnap_Bitcoin_Voting_Security::poll_price($poll_id, $cfg);
        if (empty($price['result'])) {
            $currency = (string) get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_currency', true);
            return new WP_Error('unavailable', Coinsnap_Bitcoin_Voting_Security::price_error_message($price, $currency), ['status' => 503]);
        }
        $sats = (int) $price['sats'];

        // Donor fields are accepted only when this poll collects them.
        $collect = (bool) get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_collect_donor_info', true);
        $donor = [];
        if ($collect) {
            $visibility = [
                'first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email',
                'street' => 'address', 'house_number' => 'address', 'postal' => 'address', 'town' => 'address', 'country' => 'address',
                'custom' => 'custom_field',
            ];
            foreach ($visibility as $key => $metaKey) {
                $mode = (string) get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_' . $metaKey . '_visibility', true);
                if ($mode === '' || $mode === 'hidden') {
                    continue;
                }
                $value = $key === 'email' ? sanitize_email((string) ($data['email'] ?? '')) : $this->field($data, $key);
                if ($mode === 'mandatory' && $value === '') {
                    return new WP_Error('missing_field', __('Please fill in the required fields', 'coinsnap-bitcoin-voting'), ['status' => 400]);
                }
                $donor[$key] = $value;
            }
        }

        // The return address may only point back to this site.
        $pageUrl = is_string($data['page_url'] ?? null) ? esc_url_raw($data['page_url']) : '';
        $redirectUrl = $pageUrl !== '' ? wp_validate_redirect($pageUrl, home_url('/')) : home_url('/');

        $metadata = [
            'type'         => Coinsnap_Bitcoin_Voting_Security::INVOICE_TYPE,
            'referralCode' => Coinsnap_Bitcoin_Voting_Security::REFERRAL_CODE,
            'orderNumber'  => 'Voted for ' . $options[$option_id],
            'pollId'       => (string) $poll_id,
            'optionId'     => (string) $option_id,
            'option'       => $options[$option_id],
            'amountFiat'   => $price['amount'] . ' ' . $price['currency'],
        ];
        // Standard invoice fields (BTCPay/Coinsnap names) next to the keys above, which stay as they are.
        $metadata += $this->standard_metadata($poll_id, $option_id, $options[$option_id], $sats, $price, $donor, $pageUrl);

        $invoice = Coinsnap_Bitcoin_Voting_Security::create_invoice($cfg, $sats, $metadata, (string) ($donor['email'] ?? ''), (string) $redirectUrl);
        if ($invoice === null) {
            return new WP_Error('unavailable', __('The payment could not be created. Please try again later.', 'coinsnap-bitcoin-voting'), ['status' => 503]);
        }

        global $wpdb;
        $wpdb->insert($this->table(), [
            'payment_id'   => $invoice['id'],
            'poll_id'      => (string) $poll_id,
            'option_id'    => $option_id,
            'option_title' => $options[$option_id],
            'amount'       => $sats,
            'currency'     => 'SATS',
            'donor_data'   => wp_json_encode(['public_donor' => $collect ? 1 : 0, 'donor' => $donor]),
            'status'       => 'pending',
        ], ['%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']);

        return $invoice;
    }

    /**
     * What the vote is for and the voter's fields, under the BTCPay/Coinsnap names. Plain values;
     * empty ones are left out when the invoice is created.
     */
    private function standard_metadata(int $poll_id, int $option_id, string $option, int $sats, array $price, array $donor, string $pageUrl): array
    {
        $pollTitle = trim((string) get_post_field('post_title', $poll_id, 'raw'));
        $itemDesc = $pollTitle !== '' ? sprintf('Vote for %s (%s)', $option, $pollTitle) : sprintf('Vote for %s', $option);
        $itemCode = 'poll-' . $poll_id . '-option-' . $option_id;
        $name = trim(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? ''));
        $formFields = [];
        if (($donor['custom'] ?? '') !== '') {
            $label = (string) get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_custom_field_name', true);
            $formFields[$label !== '' ? $label : 'custom'] = $donor['custom'];
        }
        $page = $pageUrl !== '' ? wp_validate_redirect($pageUrl, '') : '';

        return [
            'itemDesc'      => $itemDesc,
            'itemCode'      => $itemCode,
            'lineItems'     => [[
                'name'     => $itemDesc,
                'sku'      => $itemCode,
                'quantity' => 1,
                'price'    => $sats,
                'total'    => $sats,
                'currency' => 'SATS',
            ]],
            'pollTitle'     => $pollTitle,
            'voteOption'    => $option,
            'pageUrl'       => is_string($page) ? $page : '',
            'customerName'  => $name,
            'buyerName'     => $name,
            'buyerEmail'    => (string) ($donor['email'] ?? ''),
            'buyerAddress1' => trim(($donor['street'] ?? '') . ' ' . ($donor['house_number'] ?? '')),
            'buyerZip'      => (string) ($donor['postal'] ?? ''),
            'buyerCity'     => (string) ($donor['town'] ?? ''),
            'buyerCountry'  => (string) ($donor['country'] ?? ''),
            'formFields'    => $formFields,
            'platform'      => 'wordpress',
            'plugin'        => 'coinsnap-bitcoin-voting',
            'pluginVersion' => COINSNAP_BITCOIN_VOTING_VERSION,
            'language'      => (string) get_locale(),
        ];
    }

    /* ---------------------------------------------------------------------
     * Webhook
     * ------------------------------------------------------------------- */

    public function register_webhook_endpoint()
    {
        register_rest_route('coinsnap-bitcoin-voting/v1', '/webhook', [
            'methods'  => ['POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => ['Coinsnap_Bitcoin_Voting_Security', 'check_webhook_signature'],
        ]);
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        $data = json_decode($request->get_body(), true);
        $invoiceId = (is_array($data) && is_string($data['invoiceId'] ?? null)) ? $data['invoiceId'] : '';
        if ($invoiceId === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $invoiceId)) {
            return new WP_REST_Response(null, 200);
        }
        $claimsPaid = in_array($data['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        $cfg = Coinsnap_Bitcoin_Voting_Security::config();
        try {
            $invoice = Coinsnap_Bitcoin_Voting_Security::fetch_invoice($cfg, $invoiceId);
        } catch (RuntimeException $e) {
            Coinsnap_Bitcoin_Voting_Security::log('info', 'retry: API unavailable', $invoiceId);
            return new WP_REST_Response(null, 503);
        }
        if ($invoice === null || ($invoice['id'] ?? '') !== $invoiceId) {
            Coinsnap_Bitcoin_Voting_Security::log('info', 'unknown invoice', $invoiceId);
            return new WP_REST_Response(null, 200);
        }

        // The poll and option come from the row the server stored when it
        // created the invoice, never from the webhook body.
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE payment_id = %s", $this->table(), $invoiceId), ARRAY_A);
        if (!$row) {
            $row = $this->legacy_row($invoice);
        }
        if (!$row) {
            Coinsnap_Bitcoin_Voting_Security::log('info', 'not ours', $invoiceId);
            return new WP_REST_Response(null, 200);
        }

        $this->complete_vote($row, $invoice, $cfg, true, $claimsPaid);
        return new WP_REST_Response(null, 200);
    }

    /**
     * Invoices created by the browser before this version have no row. They are
     * accepted only during a short window after the upgrade (6.7), with values
     * read from the API's answer and the poll's own options.
     */
    private function legacy_row(array $invoice): ?array
    {
        $upgradedAt = (int) get_option(Coinsnap_Bitcoin_Voting_Security::UPGRADED_AT, 0);
        if ($upgradedAt <= 0 || time() > $upgradedAt + Coinsnap_Bitcoin_Voting_Security::LEGACY_WINDOW) {
            return null;
        }
        $meta = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $created = (int) ($invoice['createdAt'] ?? $invoice['createdTime'] ?? 0);
        $pollId = (int) ($meta['pollId'] ?? 0);
        $optionId = (int) ($meta['optionId'] ?? 0);
        if (($meta['type'] ?? '') !== Coinsnap_Bitcoin_Voting_Security::INVOICE_TYPE || $pollId <= 0
            || get_post_type($pollId) !== 'coinsnap-polls' || $created <= 0 || $created > $upgradedAt) {
            return null;
        }
        $options = $this->poll_options($pollId);
        if (!isset($options[$optionId])) {
            return null;
        }
        $paid = (string) ($invoice['amount'] ?? '');
        if (strtoupper((string) ($invoice['currency'] ?? '')) !== 'SATS' || !preg_match('/^\d+(?:\.0+)?$/', $paid) || (int) $paid <= 0) {
            return null;
        }

        $donor = [];
        if (trim((string) ($meta['publicDonor'] ?? '')) === '1') {
            $donor = [
                'legacy_name'    => (string) ($meta['donorName'] ?? ''),
                'email'          => (string) ($meta['donorEmail'] ?? ''),
                'legacy_address' => (string) ($meta['donorAddress'] ?? ''),
                'legacy_custom'  => (string) ($meta['donorCustom'] ?? ''),
            ];
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO %i (payment_id, poll_id, option_id, option_title, amount, currency, donor_data, status) VALUES (%s, %s, %d, %s, %d, 'SATS', %s, 'pending')",
            $this->table(), (string) $invoice['id'], (string) $pollId, $optionId, $options[$optionId], (int) $paid,
            wp_json_encode(['public_donor' => $donor ? 1 : 0, 'donor' => $donor])
        ));
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE payment_id = %s", $this->table(), (string) $invoice['id']), ARRAY_A);
    }

    /** Donor details come from the row the server stored, never from the webhook body. */
    private function create_donor_post(array $row, string $invoiceId): void
    {
        $stored = json_decode((string) ($row['donor_data'] ?? ''), true);
        if (!is_array($stored) || empty($stored['public_donor'])) {
            return;
        }
        $d = is_array($stored['donor'] ?? null) ? $stored['donor'] : [];

        $name = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($d['legacy_name'] ?? ''));
        }

        $address = (string) ($d['legacy_address'] ?? '');
        if ($address === '' && (($d['street'] ?? '') . ($d['town'] ?? '') . ($d['country'] ?? '')) !== '') {
            $address = sprintf('%s %s, %s %s, %s', $d['street'] ?? '', $d['house_number'] ?? '', $d['postal'] ?? '', $d['town'] ?? '', $d['country'] ?? '');
        }
        $custom = (string) ($d['legacy_custom'] ?? '');
        if ($custom === '' && ($d['custom'] ?? '') !== '') {
            $customName = (string) get_post_meta((int) $row['poll_id'], '_coinsnap_bitcoin_voting_polls_custom_field_name', true);
            $custom = $customName !== '' ? $customName . ': ' . $d['custom'] : $d['custom'];
        }

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($name),
            'post_status'  => 'publish',
            'post_type'    => 'coinsnap-voting-pds',
            'post_content' => '',
        ]);
        if (!$post_id || is_wp_error($post_id)) {
            return;
        }
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_donor_name', sanitize_text_field($name));
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_amount', (int) $row['amount'] . ' SATS');
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_message', '');
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_form_type', Coinsnap_Bitcoin_Voting_Security::INVOICE_TYPE);
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_dont_show', '0');
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_email', sanitize_email((string) ($d['email'] ?? '')));
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_address', sanitize_text_field($address));
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_payment_id', sanitize_text_field($invoiceId));
        update_post_meta($post_id, '_coinsnap_bitcoin_voting_custom_field', sanitize_text_field($custom));
    }
}
new Coinsnap_Bitcoin_Voting_Webhooks();
