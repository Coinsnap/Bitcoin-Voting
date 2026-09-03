<?php
if (!defined('ABSPATH')){ exit; }
class Coinsnap_Bitcoin_Voting_Webhooks {

    public function __construct(){
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_check_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_results_endpoint']);
        add_action('rest_api_init', [$this, 'register_check_payment_endpoint']);
        add_action('rest_api_init', [$this, 'register_create_invoice_endpoint']);
    }

    public function verify_rest_nonce(WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            return new WP_Error('rest_forbidden', __('A valid nonce is required.', 'Bitcoin-Voting'), ['status' => 403]);
        }
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', __('Invalid or expired nonce.', 'Bitcoin-Voting'), ['status' => 403]);
        }
        return true;
    }

    public function register_poll_results_endpoint(){
        register_rest_route('voting/v1', '/voting_results/(?P<poll_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_results'],
            'permission_callback' => [$this, 'verify_rest_nonce'],
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

    public function register_poll_check_endpoint()
    {
        register_rest_route('voting/v1', '/payment-status-long-poll/(?P<payment_id>[a-zA-Z0-9]+)/(?P<poll_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_payment_status_long_poll'],
            'permission_callback' => [$this, 'verify_rest_nonce'],

            'args' => [
                'payment_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return !empty($param);
                    }
                ],
                'poll_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
    }

    public function register_check_payment_endpoint()
    {
        register_rest_route('voting/v1', '/check-payment-status/(?P<payment_id>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_check_payment_status'],
            'permission_callback' => [$this, 'verify_rest_nonce'],

            'args' => [
                'payment_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
    }

    public function register_create_invoice_endpoint()
    {
        register_rest_route('voting/v1', '/create-invoice', [
            'methods'  => 'POST',
            'callback' => [$this, 'create_invoice'],
            'permission_callback' => [$this, 'verify_rest_nonce'],
            'args' => [
                'poll_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'option_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param >= 1 && $param <= 4;
                    }
                ]
            ]
        ]);
    }

    public function create_invoice(WP_REST_Request $request)
    {
        $poll_id = absint($request->get_param('poll_id'));
        $option_id = absint($request->get_param('option_id'));

        // Verify poll exists and is of correct type
        if ('coinsnap-polls' !== get_post_type($poll_id)) {
            return new WP_Error('invalid_poll', __('Invalid poll ID', 'Bitcoin-Voting'), ['status' => 400]);
        }

        // Verify option ID is valid (1-4)
        if ($option_id < 1 || $option_id > 4) {
            return new WP_Error('invalid_option', __('Invalid option ID', 'Bitcoin-Voting'), ['status' => 400]);
        }

        // Verify poll is active
        $poll_active = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_active', true);
        if (!$poll_active) {
            return new WP_Error('poll_inactive', __('Poll is not active', 'Bitcoin-Voting'), ['status' => 400]);
        }

        // Get amount from post meta (server-side, never from client)
        $amount = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true);
        $currency = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_currency', true);

        if (!$amount || !$currency) {
            return new WP_Error('missing_config', __('Poll configuration incomplete', 'Bitcoin-Voting'), ['status' => 400]);
        }

        // Get payment provider settings
        $provider_options = \CoinsnapCore\Admin\SettingsPage::get_settings_for(coinsnap_bitcoin_voting_plugin_instance());
        $provider = $provider_options['payment_provider'] ?? 'coinsnap';

        // Prepare invoice data
        $orderId = 'VTNG_' . dechex(time()) . dechex(rand());
        $option_title = get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$option_id}", true);

        $invoice_data = [
            'amount'      => $amount,
            'currency'    => $currency,
            'orderId'     => $orderId,
            'metadata'    => [
                'type'     => 'Coinsnap Bitcoin Voting',
                'pollId'   => $poll_id,
                'optionId' => $option_id,
                'option'   => $option_title,
            ]
        ];

        // Create invoice via provider (use existing Coinsnap Core client)
        $client = new Coinsnap_Bitcoin_Voting_Client();
        
        if ($provider === 'coinsnap') {
            $store_id = $provider_options['coinsnap_store_id'] ?? '';
            $api_key = $provider_options['coinsnap_api_key'] ?? '';

            if (!$store_id || !$api_key) {
                return new WP_Error('missing_credentials', __('Coinsnap credentials not configured', 'Bitcoin-Voting'), ['status' => 400]);
            }

            $url = "https://app.coinsnap.io/api/v1/stores/{$store_id}/invoices";
            $headers = [
                'x-api-key'     => $api_key,
                'Content-Type'  => 'application/json'
            ];
        } else {
            $host = $provider_options['btcpay_host'] ?? '';
            $store_id = $provider_options['btcpay_store_id'] ?? '';
            $api_key = $provider_options['btcpay_api_key'] ?? '';

            if (!$host || !$store_id || !$api_key) {
                return new WP_Error('missing_credentials', __('BTCPay credentials not configured', 'Bitcoin-Voting'), ['status' => 400]);
            }

            $url = "{$host}/api/v1/stores/{$store_id}/invoices";
            $headers = [
                'Authorization' => "token {$api_key}",
                'Content-Type'  => 'application/json'
            ];
        }

        $response = $client->remoteRequest('POST', $url, $headers, json_encode($invoice_data));

        if (isset($response['error'])) {
            return new WP_Error('invoice_creation_failed', __('Failed to create invoice', 'Bitcoin-Voting'), ['status' => 400]);
        }

        if (isset($response['status']) && $response['status'] === 200) {
            return new WP_REST_Response($response['body'], 200);
        }

        return new WP_Error('invoice_creation_failed', __('Failed to create invoice', 'Bitcoin-Voting'), ['status' => 400]);
    }

    function get_results($request){
        $poll_id = $request['poll_id'];
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}voting_payments WHERE status = 'completed' AND poll_id = %d",$poll_id));
        return ['results' => $results];
    }

    function get_payment_status_long_poll($request)
    {
        $payment_id = $request['payment_id'];
        $poll_id    = $request['poll_id'];
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
            $payment_id
        ));
        if ($status === 'completed') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}voting_payments WHERE status = 'completed' AND poll_id = %d", $poll_id));
            return ['status' => 'completed', 'results' => $results];
        }
        return ['status' => 'pending'];
    }

    function get_check_payment_status($request)
    {
        $payment_id = $request['payment_id'];
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
            $payment_id
        ));
        if ($status === 'completed') {
            return ['status' => 'completed'];
        }
        return ['status' => 'pending'];
    }


    private function get_webhook_secret()
    {
        $option_name = 'coinsnap_webhook_secret';
        $secret = get_option($option_name);

        if (!$secret) {
            $secret = bin2hex(random_bytes(16));
            add_option($option_name, $secret, '', false);
        }

        return $secret;
    }

    public function register_webhook_endpoint()
    {
        register_rest_route('coinsnap-bitcoin-voting/v1', 'webhook', [
            'methods'  => ['POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_webhook_request']
        ]);
    }

    function verify_webhook_request($request){
        
            $secret = $this->get_webhook_secret();
            $coinsnap_sig = $request->get_header('X-Coinsnap-Sig');
            $btcpay_sig = $request->get_header('btcpay_sig');
            $signature_header = !empty($coinsnap_sig) ? $coinsnap_sig : $btcpay_sig;
            if (empty($signature_header)) {
                return false;
            }

            $payload = $request->get_body();

            $computed_signature = hash_hmac('sha256', $payload, $secret);
            $computed_signature = 'sha256=' . $computed_signature; // Prefix the computed_signature with 'sha256='
            if (!hash_equals($computed_signature, $signature_header)) {
                return false;
            }
            return true;
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        $payload_data = $request->get_json_params();

        if (isset($payload_data['type']) && ($payload_data['type'] === 'Settled' || $payload_data['type'] === 'InvoiceSettled')) {
            //error_log('Webhook received: ' . json_encode($payload_data));
            // Voting
            if (isset($payload_data['metadata']['type']) && $payload_data['metadata']['type'] == "Coinsnap Bitcoin Voting") {
                // SECURITY FIX: Validate poll and amount on webhook
                $poll_id = absint($payload_data['metadata']['pollId'] ?? 0);
                $option_id = absint($payload_data['metadata']['optionId'] ?? 0);
                $invoice_id = sanitize_text_field($payload_data['invoiceId'] ?? '');
                $amount = floatval($payload_data['amount'] ?? 0);

                // Verify poll exists and is coinsnap-polls type
                if ('coinsnap-polls' !== get_post_type($poll_id)) {
                    error_log('Webhook rejected: Invalid poll_id ' . $poll_id);
                    return new WP_REST_Response('Invalid poll', 400);
                }

                // Verify option_id is valid (1-4)
                if ($option_id < 1 || $option_id > 4) {
                    error_log('Webhook rejected: Invalid option_id ' . $option_id);
                    return new WP_REST_Response('Invalid option', 400);
                }

                // Verify poll is active
                $poll_active = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_active', true);
                if (!$poll_active) {
                    error_log('Webhook rejected: Poll not active ' . $poll_id);
                    return new WP_REST_Response('Poll not active', 400);
                }

                // Verify amount meets minimum (with 0.0001 tolerance for rounding)
                $expected_amount = floatval(get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true));
                if ($amount + 0.0001 < $expected_amount) {
                    error_log('Webhook rejected: Underpaid. Expected ' . $expected_amount . ', got ' . $amount);
                    return new WP_REST_Response('Underpaid', 400);
                }

                global $wpdb;
                $invoiceId = $invoice_id;
                $optionTitle = get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$option_id}", true);

                // Check for duplicate webhook (idempotency)
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT payment_id FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
                    $invoiceId
                ));
                if (!empty($existing)) {
                    error_log('Webhook rejected: Duplicate payment_id ' . $invoiceId);
                    return new WP_REST_Response('Already processed', 409);
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->insert(
                    "{$wpdb->prefix}voting_payments",
                    [
                        'payment_id' => $invoiceId,
                        'option_id' => $option_id,
                        'option_title' => $optionTitle,
                        'poll_id' => $poll_id,
                        'status'     => 'completed'
                    ],
                    [
                        '%s',
                        '%d',
                        '%s',
                        '%d',
                        '%s'
                    ]
                );
                // In page QR
            }
            if (isset($payload_data['metadata']['modal'])) {
                global $wpdb;
                $invoiceId = sanitize_text_field($payload_data['invoiceId'] ?? '');

                // Check for duplicate webhook (idempotency)
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT payment_id FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
                    $invoiceId
                ));
                if (!empty($existing)) {
                    error_log('Webhook rejected: Duplicate payment_id ' . $invoiceId);
                    return new WP_REST_Response('Already processed', 409);
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->insert(
                    "{$wpdb->prefix}voting_payments",
                    [
                        'payment_id' => $invoiceId,
                        'status'     => 'completed'
                    ],
                    ['%s', '%s']
                );
                // Public donor
                if (isset($payload_data['metadata']['publicDonor']) && $payload_data['metadata']['publicDonor'] == '1') {

                    $name = sanitize_text_field($payload_data['metadata']['donorName'] ?? '');
                    $email = sanitize_email($payload_data['metadata']['donorEmail'] ?? '');
                    $address = sanitize_text_field($payload_data['metadata']['donorAddress'] ?? '');
                    $message = sanitize_textarea_field($payload_data['metadata']['donorMessage'] ?? '');
                    $opt_out = $payload_data['metadata']['donorOptOut'] ?? '0';
                    $custom = sanitize_text_field($payload_data['metadata']['donorCustom'] ?? '');
                    $type = sanitize_text_field($payload_data['metadata']['formType'] ?? '');
                    $amount = floatval($payload_data['metadata']['amount'] ?? 0);
                    $opt_out_value = filter_var($opt_out, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                    $post_data = array(
                        'post_title'    => $name,
                        'post_status'   => 'publish',
                        'post_type'     => 'coinsnap-pds',
                        'post_content'  => $message
                    );

                    $post_id = wp_insert_post($post_data);

                    if ($post_id) {
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_donor_name', $name);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_amount', $amount);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_message', $message);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_form_type', $type);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_dont_show', $opt_out_value);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_email', $email);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_address', $address);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_payment_id', $invoiceId);
                        update_post_meta($post_id, '_coinsnap_bitcoin_voting_custom_field', $custom);
                    }
                }
            }
        }

        return new WP_REST_Response('Webhook type not handled.', 200);
    }
}
new Coinsnap_Bitcoin_Voting_Webhooks();
