<?php
if (!defined('ABSPATH')){ exit; }
class Coinsnap_Bitcoin_Voting_Webhooks {

    public function __construct(){
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_check_endpoint']);
        add_action('rest_api_init', [$this, 'register_poll_results_endpoint']);
        add_action('rest_api_init', [$this, 'register_check_payment_endpoint']);
        add_action('rest_api_init', [$this, 'register_check_invoice_status_endpoint']);
        add_action('rest_api_init', [$this, 'register_create_invoice_endpoint']);
    }

    public function verify_rest_nonce(WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            return new WP_Error('rest_forbidden', __('A valid nonce is required.', 'coinsnap-bitcoin-voting'), ['status' => 403]);
        }
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', __('Invalid or expired nonce.', 'coinsnap-bitcoin-voting'), ['status' => 403]);
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

    public function register_check_invoice_status_endpoint()
    {
        register_rest_route('voting/v1', '/check-invoice-status', [
            'methods' => 'POST',
            'callback' => [$this, 'check_invoice_status'],
            'permission_callback' => [$this, 'verify_rest_nonce'],

            'args' => [
                'invoice_id' => [
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
            'permission_callback' => '__return_true'
        ]);
    }

    public function create_invoice(WP_REST_Request $request)
    {
        // Get JSON body parameters
        $params = $request->get_json_params();
        
        $poll_id = absint($params['poll_id'] ?? 0);
        $option_id = absint($params['option_id'] ?? 0);

        if (!$poll_id || !$option_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Missing poll_id or option_id', 'coinsnap-bitcoin-voting')
            ], 400);
        }

        // Verify poll exists and is of correct type
        $poll = get_post($poll_id);
        if (!$poll || 'coinsnap-polls' !== $poll->post_type) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid poll ID', 'coinsnap-bitcoin-voting')
            ], 400);
        }

        // Verify option ID is valid (1-4)
        if ($option_id < 1 || $option_id > 4) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid option ID', 'coinsnap-bitcoin-voting')
            ], 400);
        }

        // Verify poll is active
        $poll_active = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_active', true);
        if (!$poll_active) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Poll is not active', 'coinsnap-bitcoin-voting')
            ], 400);
        }

        // Get amount and currency from post meta (server-side, never from client)
        $amount = floatval(get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true));
        $currency = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_currency', true);

        if (!$amount || !$currency) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Poll configuration incomplete', 'coinsnap-bitcoin-voting')
            ], 400);
        }

        // Get payment provider settings
        $core = coinsnap_bitcoin_voting_plugin_instance();
        $settings = \CoinsnapCore\Admin\SettingsPage::get_settings_for($core);
        $provider = $settings['payment_provider'] ?? 'coinsnap';

        // Validate credentials
        if ('btcpay' === $provider) {
            $host = $settings['btcpay_host'] ?? '';
            $store_id = $settings['btcpay_store_id'] ?? '';
            $api_key = $settings['btcpay_api_key'] ?? '';
            
            if (empty($host) || empty($store_id) || empty($api_key)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('DEBUG CREATE_INVOICE: BTCPay credentials missing');
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Payment gateway is not configured', 'coinsnap-bitcoin-voting')
                ], 503);
            }
            $api_url = "{$host}/api/v1/stores/{$store_id}/invoices";
            $headers = [
                'Authorization' => "token {$api_key}",
                'Content-Type'  => 'application/json'
            ];
        } else {
            $store_id = $settings['coinsnap_store_id'] ?? '';
            $api_key = $settings['coinsnap_api_key'] ?? '';
            
            if (empty($store_id) || empty($api_key)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('DEBUG CREATE_INVOICE: Coinsnap credentials missing');
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Payment gateway is not configured', 'coinsnap-bitcoin-voting')
                ], 503);
            }
            $api_url = "https://app.coinsnap.io/api/v1/stores/{$store_id}/invoices";
            $headers = [
                'x-api-key'    => $api_key,
                'Content-Type' => 'application/json'
            ];
        }

        // Get option and poll details
        $option_title = get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$option_id}", true);
        $poll_title = $poll->post_title;

        // NOTE: Do NOT convert amount to minor units (cents).
        // Coinsnap API handles all conversions internally based on currency.
        // Send amount as stored in database (float value for fiat).
        // Same pattern as coinsnap-bitcoin-booking-form uses.
        $amount_to_send = $amount;

        // Generate order ID
        $order_id = 'voting_' . wp_rand(1000, 9999) . '_' . time();

        // Get the poll page URL for redirect
        // Instead of confirmation page, redirect to poll page so user can see results
        // Webhook will automatically record the vote when Coinsnap confirms payment
        global $wpdb;
        $like = '%[coinsnap_bitcoin_voting id="' . intval( $poll_id ) . '"%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $page_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT 1",
            $like
        ) );
        
        // Create a client-side reference ID for tracking (will be mapped to Coinsnap invoice_id later)
        $client_reference_id = bin2hex( random_bytes( 16 ) ); // Create random hex ID
        
        if ( $page_id ) {
            $redirect_url = get_permalink( $page_id );
            $redirect_url .= (strpos($redirect_url, '?') ? '&' : '?') . http_build_query([
                'payment_initiated' => '1',
                'poll_id' => $poll_id,
                'option_id' => $option_id,
                'ref_id' => $client_reference_id  // Local reference ID (will map to invoice_id)
            ]);
        } else {
            $redirect_url = home_url();
        }

        // Debug: Check if redirect_url is empty
        if (empty($redirect_url)) {
            return new WP_REST_Response([
                'success' => false,
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
                'message' => 'DEBUG: No redirect URL (confirmation_url=' . var_export($confirmation_url, true) . ', home_url=' . home_url() . ')'
            ], 400);
        }

        // Prepare invoice data for Coinsnap/BTCPay API
        $invoice_body = [
            'amount' => $amount_to_send,
            'currency' => $currency,
            'orderId' => $order_id,
            'redirectUrl' => $redirect_url,
            'redirectAutomatically' => true,
            'metadata' => [
                'type' => 'Coinsnap Bitcoin Voting',
                'pollId' => $poll_id,
                'optionId' => $option_id,
                'option' => $option_title,
            ]
        ];

        // For Coinsnap, add Lightning Network as default payment method
        if ('coinsnap' === $provider) {
            $invoice_body['checkout'] = [
                'defaultPaymentMethod' => 'LightningNetwork'
            ];
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('DEBUG CREATE_INVOICE: POST ' . $api_url);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('DEBUG CREATE_INVOICE: Headers = ' . json_encode($headers));
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('DEBUG CREATE_INVOICE: Body = ' . json_encode($invoice_body));

        // Make API call
        $response = wp_remote_post($api_url, [
            'headers' => $headers,
            'body' => json_encode($invoice_body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('DEBUG CREATE_INVOICE: WP Error - ' . $error_msg);
            return new WP_REST_Response([
                'success' => false,
                'message' => __('API request failed', 'coinsnap-bitcoin-voting'),
                'error' => $error_msg
            ], 500);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('DEBUG CREATE_INVOICE: Response Code = ' . $status_code);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('DEBUG CREATE_INVOICE: Response Body = ' . $response_body);

        if ($status_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_msg = $error_data['message'] ?? $error_data['error'] ?? __('Invoice creation failed', 'coinsnap-bitcoin-voting');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('DEBUG CREATE_INVOICE: API Error - ' . $error_msg);
            return new WP_REST_Response([
                'success' => false,
                'message' => $error_msg
            ], $status_code >= 400 && $status_code < 500 ? 400 : 500);
        }

        $invoice = json_decode($response_body, true);
        
        if (empty($invoice['id'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('DEBUG CREATE_INVOICE: No invoice ID in response');
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid API response', 'coinsnap-bitcoin-voting')
            ], 500);
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('DEBUG CREATE_INVOICE: Success - invoice_id=' . $invoice['id']);
        
        // Save mapping of ref_id to invoice_id so we can look it up later
        $invoice_id = $invoice['id'];
        
        // Store the reference ID mapping (optional cache for lookup)
        // In a real app, you'd store this in a transient or options
        // For now, we'll just send it back in the response
        $redirect_url_final = $redirect_url . '&invoice_id=' . urlencode($invoice_id);

        return new WP_REST_Response([
            'success' => true,
            'id' => $invoice['id'],
            'invoice_id' => $invoice['id'],
            'checkoutLink' => $invoice['checkoutLink'] ?? $invoice['checkout_link'] ?? '',
            'payment_url' => $invoice['checkoutLink'] ?? $invoice['checkout_link'] ?? '',
            'redirectUrl' => $redirect_url_final,
            'ref_id' => $client_reference_id,  // Send back ref_id for client tracking
        ], 200);
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

    /**
     * Check if invoice payment has been settled/paid
     * Used for polling payment status and auto-redirecting after payment
     */
    public function check_invoice_status(WP_REST_Request $request)
    {
        $invoice_id = sanitize_text_field($request->get_param('invoice_id'));

        if (empty($invoice_id)) {
            return new WP_Error('missing_invoice_id', __('Invoice ID is required', 'coinsnap-bitcoin-voting'), ['status' => 400]);
        }

        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
            $invoice_id
        ));

        if (!$payment) {
            return ['status' => 'pending', 'paid' => false];
        }

        // Check if payment is settled/completed
        $is_paid = ($payment->status === 'completed' || $payment->status === 'settled');
        
        return [
            'status' => $payment->status,
            'paid' => $is_paid
        ];
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
        // Endpoint for confirming payment after Coinsnap redirect
        register_rest_route('voting/v1', '/record-payment', [
            'methods'  => 'POST',
            'callback' => [$this, 'record_payment'],
            'permission_callback' => [$this, 'verify_rest_nonce']
        ]);

        register_rest_route('coinsnap-bitcoin-voting/v1', 'webhook', [
            'methods'  => ['POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_webhook_request']
        ]);
    }

    /**
     * REST endpoint: Record payment after user returns from Coinsnap
     * Called from confirmation page when Coinsnap redirects back
     */
    public function record_payment(WP_REST_Request $request)
    {
        $invoice_id = sanitize_text_field($request->get_param('invoice_id'));
        $poll_id = absint($request->get_param('poll_id'));
        $option_id = absint($request->get_param('option_id'));

        if (!$invoice_id || !$poll_id || !$option_id) {
            return new WP_Error('missing_params', __('Missing required parameters', 'coinsnap-bitcoin-voting'), ['status' => 400]);
        }

        // Verify poll exists
        if ('coinsnap-polls' !== get_post_type($poll_id)) {
            return new WP_Error('invalid_poll', __('Invalid poll ID', 'coinsnap-bitcoin-voting'), ['status' => 400]);
        }

        global $wpdb;

        // Check if payment is already recorded
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_id FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
            $invoice_id
        ));

        if ($existing) {
            return new WP_REST_Response(['status' => 'already_recorded', 'message' => 'Vote already recorded'], 200);
        }

        // Get option title
        $option_title = get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$option_id}", true);

        // Insert the payment record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $insert_result = $wpdb->insert(
            "{$wpdb->prefix}voting_payments",
            [
                'payment_id' => $invoice_id,
                'poll_id' => $poll_id,
                'option_id' => $option_id,
                'option_title' => $option_title,
                'status' => 'completed'
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );

        if ($insert_result === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("ERROR: Failed to insert payment: " . $wpdb->last_error);
            return new WP_Error('insert_failed', __('Failed to record payment', 'coinsnap-bitcoin-voting'), ['status' => 500]);
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log("SUCCESS: Payment recorded - invoiceId=$invoice_id, pollId=$poll_id, optionId=$option_id");
        
        // Also write to shared PaymentTable (used by admin Transactions page)
        // This mirrors what booking form does
        $shared_table = $wpdb->prefix . 'coinsnapbbf_payments';
        
        // Check if this table exists (SHOW TABLES returns table name without prefix)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $shared_table));
        if ($table_exists) {
            $poll_title = get_the_title($poll_id);
            $amount = floatval(get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true));
            $currency = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_currency', true);
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $shared_table,
                array(
                    'source_id'          => $poll_id,
                    'transaction_id'     => 'voting_' . time() . '_' . substr(md5($invoice_id), 0, 8),
                    'customer_name'      => 'Bitcoin Voter',
                    'customer_email'     => '',
                    'amount'             => $amount,
                    'currency'           => $currency,
                    'description'        => 'Vote: ' . $poll_title . ' - ' . $option_title,
                    'payment_provider'   => 'coinsnap',
                    'payment_invoice_id' => $invoice_id,
                    'payment_status'     => 'paid',
                    'payment_url'        => '',
                    'ip'                 => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
                    'created_at'         => current_time('mysql'),
                )
            );
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("Also inserted into shared PaymentTable for admin Transactions page");
        }

        // Clear cache after recording payment
        wp_cache_delete('coinsnap_voting_donations');

        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Payment and vote recorded',
            'poll_id' => $poll_id,
            'option_id' => $option_id
        ], 200);
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
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( "DEBUG WEBHOOK: Received webhook request" );
        
        // Get headers from request instead of getallheaders() which doesn't work in CLI
        $headers = $request->get_headers();
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( "DEBUG WEBHOOK: Headers: " . json_encode( $headers ) );
        
        $payload_data = $request->get_json_params();
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( "DEBUG WEBHOOK: Payload: " . json_encode( $payload_data ) );

        if (isset($payload_data['type']) && ($payload_data['type'] === 'Settled' || $payload_data['type'] === 'InvoiceSettled')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
                    return new WP_REST_Response('Invalid poll', 400);
                }

                // Verify option_id is valid (1-4)
                if ($option_id < 1 || $option_id > 4) {
                    return new WP_REST_Response('Invalid option', 400);
                }

                // Verify poll is active
                $poll_active = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_active', true);
                if (!$poll_active) {
                    return new WP_REST_Response('Poll not active', 400);
                }

                // Verify amount meets minimum (with 0.0001 tolerance for rounding)
                $expected_amount = floatval(get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true));
                if ($amount + 0.0001 < $expected_amount) {
                    return new WP_REST_Response('Underpaid', 400);
                }

                global $wpdb;
                $invoiceId = $invoice_id;
                $optionTitle = get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$option_id}", true);

                // Check for duplicate webhook (idempotency)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT payment_id FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
                    $invoiceId
                ));
                if (!empty($existing)) {
                    return new WP_REST_Response('Already processed', 409);
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $insert_result = $wpdb->insert(
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
                
                if ($insert_result === false) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log("ERROR: Failed to insert vote - " . $wpdb->last_error);
                    return new WP_REST_Response('Database error', 500);
                }
                
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log("SUCCESS: Vote recorded - invoiceId=$invoiceId, pollId=$poll_id, optionId=$option_id");
                
                // Also write to shared PaymentTable (used by admin Transactions page)
                // This mirrors what booking form does
                $shared_table = $wpdb->prefix . 'coinsnapbbf_payments';
                
                // Check if this table exists - use prepared statement
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $shared_table)) === $shared_table) {
                    $poll_title = get_the_title($poll_id);
                    $amount = floatval(get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true));
                    $currency = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_currency', true);
                    
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->insert(
                        $shared_table,
                        array(
                            'source_id'          => $poll_id,
                            'transaction_id'     => 'voting_' . time() . '_' . substr(md5($invoiceId), 0, 8),
                            'customer_name'      => 'Bitcoin Voter',
                            'customer_email'     => '',
                            'amount'             => $amount,
                            'currency'           => $currency,
                            'description'        => 'Vote: ' . $poll_title . ' - ' . $optionTitle,
                            'payment_provider'   => 'coinsnap',
                            'payment_invoice_id' => $invoiceId,
                            'payment_status'     => 'paid',
                            'payment_url'        => '',
                            'ip'                 => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
                            'created_at'         => current_time('mysql'),
                        )
                    );
                    
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log("Also inserted into shared PaymentTable for admin Transactions page");
                }
                
                return new WP_REST_Response('Vote recorded', 200);
                // In page QR
            }
            if (isset($payload_data['metadata']['modal'])) {
                global $wpdb;
                $invoiceId = sanitize_text_field($payload_data['invoiceId'] ?? '');

                // Check for duplicate webhook (idempotency)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT payment_id FROM {$wpdb->prefix}voting_payments WHERE payment_id = %s",
                    $invoiceId
                ));
                if (!empty($existing)) {
                    return new WP_REST_Response('Already processed', 409);
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $insert_result = $wpdb->insert(
                    "{$wpdb->prefix}voting_payments",
                    [
                        'payment_id' => $invoiceId,
                        'status'     => 'completed'
                    ],
                    ['%s', '%s']
                );
                
                if ($insert_result === false) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log("ERROR: Failed to insert donation - " . $wpdb->last_error);
                    return new WP_REST_Response('Database error', 500);
                }
                
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log("SUCCESS: Donation recorded - invoiceId=$invoiceId");
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
                return new WP_REST_Response('Donation recorded', 200);
            }
        }

        return new WP_REST_Response('Webhook type not handled.', 200);
    }
}
new Coinsnap_Bitcoin_Voting_Webhooks();
