<?php
/*
 * Plugin Name:        Coinsnap Bitcoin Voting
 * Plugin URI:         https://coinsnap.io/coinsnap-bitcoin-voting-plugin/
 * Description:        Easy Bitcoin voting on a WordPress website
 * Version:            1.2.3
 * Author:             Coinsnap
 * Author URI:         https://coinsnap.io/
 * Text Domain:        coinsnap-bitcoin-voting
 * Domain Path:         /languages
 * Tested up to:        6.9
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:             true
 */

defined( 'ABSPATH' ) || exit;

if(!defined( 'COINSNAP_BITCOIN_VOTING_REFERRAL_CODE' ) ) { define( 'COINSNAP_BITCOIN_VOTING_REFERRAL_CODE', 'D46835' );}
if(!defined( 'COINSNAP_BITCOIN_VOTING_VERSION' ) ) { define( 'COINSNAP_BITCOIN_VOTING_VERSION', '1.2.3' );}
if(!defined( 'COINSNAP_BITCOIN_VOTING_PHP_VERSION' ) ) { define( 'COINSNAP_BITCOIN_VOTING_PHP_VERSION', '8.0' );}
if(!defined( 'COINSNAP_BITCOIN_VOTING_PLUGIN_DIR' ) ){ define('COINSNAP_BITCOIN_VOTING_PLUGIN_DIR',plugin_dir_url(__FILE__));}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}

// Plugin settings
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-security.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-polls.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-public-donors.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-shortcode-voting.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-webhooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-client.php';

register_activation_hook(__FILE__, 'coinsnap_bitcoin_voting_create_voting_payments_table');
register_deactivation_hook(__FILE__, 'coinsnap_bitcoin_voting_deactivate');

function coinsnap_bitcoin_voting_deactivate(){
    flush_rewrite_rules();
}

define('COINSNAP_BITCOIN_VOTING_DB_VERSION', '2');

function coinsnap_bitcoin_voting_create_voting_payments_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'voting_payments';
    $charset_collate = $wpdb->get_charset_collate();

    // Webhook retries used to insert the same invoice more than once, and the
    // old handler added a second, empty row for every vote. Keep the first row
    // per invoice so the unique key below can be added.
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name) {
        $wpdb->query($wpdb->prepare(
            'DELETE t1 FROM %i t1 INNER JOIN %i t2 ON t1.payment_id = t2.payment_id AND t1.id > t2.id',
            $table_name, $table_name
        ));
    }

    // One row per invoice, created by the server when it creates the invoice.
    $sql = "CREATE TABLE $table_name (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  payment_id varchar(191) NOT NULL,
  poll_id varchar(255) NOT NULL,
  option_id int(4) NOT NULL,
  option_title varchar(255) NOT NULL,
  amount bigint(20) NOT NULL DEFAULT 0,
  currency varchar(10) NOT NULL DEFAULT 'SATS',
  donor_data longtext,
  status varchar(50) NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY payment_id (payment_id)
) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (!get_option(Coinsnap_Bitcoin_Voting_Security::UPGRADED_AT)) {
        update_option(Coinsnap_Bitcoin_Voting_Security::UPGRADED_AT, time(), false);
    }
    update_option('coinsnap_bitcoin_voting_db_version', COINSNAP_BITCOIN_VOTING_DB_VERSION, false);
}

add_action('plugins_loaded', function () {
    if (get_option('coinsnap_bitcoin_voting_db_version') !== COINSNAP_BITCOIN_VOTING_DB_VERSION) {
        coinsnap_bitcoin_voting_create_voting_payments_table();
    }
});

class Coinsnap_Bitcoin_Voting
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'coinsnap_bitcoin_voting_enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'coinsnap_bitcoin_voting_enqueue_admin_styles']);
        add_action('wp_ajax_coinsnap_bitcoin_voting_btcpay_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
        add_action('wp_ajax_coinsnap_bitcoin_voting_connection_handler', [$this, 'coinsnapConnectionHandler']);
        add_action('admin_post_coinsnap_bitcoin_voting_register_webhook', [$this, 'registerWebhookAction']);
        add_action('add_option_coinsnap_bitcoin_voting_options', [$this, 'credentialsAdded'], 10, 2);
        add_action('update_option_coinsnap_bitcoin_voting_options', [$this, 'credentialsChanged'], 10, 2);
        add_action('admin_notices', [$this, 'webhookNotice']);
    }

    /** Admin action: check capability and nonce before any API call (6.5). */
    public function registerWebhookAction(){
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'coinsnap-bitcoin-voting'), '', ['response' => 403]);
        }
        check_admin_referer('coinsnap_bitcoin_voting_register_webhook');
        $ok = Coinsnap_Bitcoin_Voting_Security::register_webhook();
        wp_safe_redirect(add_query_arg('voting_webhook', $ok ? 'ok' : 'failed', admin_url('admin.php?page=coinsnap-bitcoin-voting')));
        exit;
    }

    /** Settings were saved through options.php (capability and nonce checked by WordPress) or the connect callback. */
    public function credentialsAdded($option, $value){
        $this->credentialsChanged([], $value);
    }

    public function credentialsChanged($old, $new){
        if (!current_user_can('manage_options')) {
            return;
        }
        $keys = ['provider', 'coinsnap_store_id', 'coinsnap_api_key', 'btcpay_url', 'btcpay_store_id', 'btcpay_api_key', 'ngrok_url'];
        $old = (array) $old;
        $new = (array) $new;
        foreach ($keys as $k) {
            if (($old[$k] ?? '') !== ($new[$k] ?? '')) {
                Coinsnap_Bitcoin_Voting_Security::register_webhook();
                return;
            }
        }
    }

    public function webhookNotice(){
        if (!current_user_can('manage_options')) {
            return;
        }
        $cfg = Coinsnap_Bitcoin_Voting_Security::config();
        if (!Coinsnap_Bitcoin_Voting_Security::is_configured($cfg) || Coinsnap_Bitcoin_Voting_Security::get_secret($cfg) !== '') {
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=coinsnap_bitcoin_voting_register_webhook'), 'coinsnap_bitcoin_voting_register_webhook');
        printf('<div class="notice notice-warning"><p>%s <a class="button" href="%s">%s</a></p></div>',
            esc_html__('Bitcoin Voting: votes are not counted until the webhook is connected.', 'coinsnap-bitcoin-voting'),
            esc_url($url),
            esc_html__('Connect webhook', 'coinsnap-bitcoin-voting'));
    }

    public function coinsnapConnectionHandler(){
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die('Unauthorized!', '', ['response' => 403]);
        }
        $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
        if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }
        
        $response = [
            'result' => false,
            'message' => __('Empty gateway URL or API Key', 'coinsnap-bitcoin-voting')
        ];
        
        $coinsnap_bitcoin_voting_data = get_option('coinsnap_bitcoin_voting_options', []);
        
        $_provider = $this->getPaymentProvider();
        $currency = ('' !== filter_input(INPUT_POST,'apiPost',FILTER_SANITIZE_STRING))? get_post_meta(filter_input(INPUT_POST,'apiPost',FILTER_SANITIZE_STRING), '_coinsnap_bitcoin_voting_polls_currency', true) : 'EUR';
        $client = new Coinsnap_Bitcoin_Voting_Client();
        
        if($_provider === 'btcpay'){
            try {
                
                $storePaymentMethods = $client->getStorePaymentMethods($this->getApiUrl(), $this->getApiKey(), $this->getStoreId());

                if (($storePaymentMethods['code'] ?? 0) === 200) {
                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'bitcoin','calculation');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'lightning','calculation');
                    }
                }
            }
            catch (\Exception $e) {
                $response = [
                        'result' => false,
                        'message' => __('Coinsnap Bitcoin Voting: API connection is not established', 'coinsnap-bitcoin-voting')
                ];
                $this->sendJsonResponse($response);
            }
        }
        else {
            $checkInvoice = $client->checkPaymentData(0,$currency,'coinsnap','calculation');
        }
        
        if(isset($checkInvoice) && $checkInvoice['result']){
            $connectionData = __('Min order amount is', 'coinsnap-bitcoin-voting') .' '. $checkInvoice['min_value'].' '.$currency;
        }
        else {
            $connectionData = __('No payment method is configured', 'coinsnap-bitcoin-voting');
        }
        
        $_message_disconnected = ($_provider !== 'btcpay')? 
            __('Coinsnap Bitcoin Voting: Coinsnap server is disconnected', 'coinsnap-bitcoin-voting') :
            __('Coinsnap Bitcoin Voting: BTCPay server is disconnected', 'coinsnap-bitcoin-voting');
        $_message_connected = ($_provider !== 'btcpay')?
            __('Coinsnap Bitcoin Voting: Coinsnap server is connected', 'coinsnap-bitcoin-voting') : 
            __('Coinsnap Bitcoin Voting: BTCPay server is connected', 'coinsnap-bitcoin-voting');
        
        if( wp_verify_nonce($_nonce,'coinsnap-ajax-nonce') ){
            $response = ['result' => false,'message' => $_message_disconnected];

            try {
                $this_store = $client->getStore($this->getApiUrl(), $this->getApiKey(), $this->getStoreId());
                
                if ($this_store['code'] !== 200) {
                    $this->sendJsonResponse($response);
                }
                
                else {
                    $hasWebhook = Coinsnap_Bitcoin_Voting_Security::get_secret(Coinsnap_Bitcoin_Voting_Security::config()) !== '';
                    $webhookText = $hasWebhook
                        ? __('Webhook: connected', 'coinsnap-bitcoin-voting')
                        : __('Webhook: not connected', 'coinsnap-bitcoin-voting');
                    $response = ['result' => $hasWebhook,'message' => $_message_connected.' ('.$connectionData.'). '.$webhookText];
                    $this->sendJsonResponse($response);
                }
            }
            catch (\Exception $e) {
                $response['message'] =  __('Coinsnap Bitcoin Voting: API connection is not established', 'coinsnap-bitcoin-voting');
            }

            $this->sendJsonResponse($response);
        }            
    }
    
    public function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
    
    private function getPaymentProvider() {
        $coinsnap_bitcoin_voting_data = get_option('coinsnap_bitcoin_voting_options', []);
        return (($coinsnap_bitcoin_voting_data['provider'] ?? '') === 'btcpay')? 'btcpay' : 'coinsnap';
    }

    private function getApiKey() {
        $coinsnap_bitcoin_voting_data = get_option('coinsnap_bitcoin_voting_options', []);
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnap_bitcoin_voting_data['btcpay_api_key']  : $coinsnap_bitcoin_voting_data['coinsnap_api_key'];
    }
    
    private function getStoreId() {
	$coinsnap_bitcoin_voting_data = get_option('coinsnap_bitcoin_voting_options', []);
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnap_bitcoin_voting_data['btcpay_store_id'] : $coinsnap_bitcoin_voting_data['coinsnap_store_id'];
    }
    
    public function getApiUrl() {
        $coinsnap_bitcoin_voting_data = get_option('coinsnap_bitcoin_voting_options', []);
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnap_bitcoin_voting_data['btcpay_url'] : COINSNAP_SERVER_URL;
    }
    
    public function btcpayApiUrlHandler(){
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die('Unauthorized!', '', ['response' => 403]);
            }
            $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
            if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
                wp_die('Unauthorized!', '', ['response' => 401]);
            }

            $host = filter_var(filter_input(INPUT_POST,'host',FILTER_SANITIZE_STRING), FILTER_VALIDATE_URL);
            if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                wp_send_json_error("Error validating BTCPayServer URL.");
            }
            $host = rtrim($host, '/');

            try {
                $state = Coinsnap_Bitcoin_Voting_Security::btcpay_start_state();
                $url = $this->getAuthorizeUrl(
                    $host,
                    Coinsnap_Bitcoin_Voting_Security::BTCPAY_PERMISSIONS,
                    'CoinsnapBitcoinVoting',
                    true,
                    true,
                    add_query_arg(['voting-btcpay-settings-callback' => '1', 'state' => $state], home_url('/')),
                    'coinsnap-bitcoin-voting'
                );

                // A key belongs to one server: a new server URL drops the stored key (6.14).
                $stored = (array) get_option('coinsnap_bitcoin_voting_options', []);
                $update = ['btcpay_url' => $host];
                if (rtrim((string) ($stored['btcpay_url'] ?? ''), '/') !== $host) {
                    $update['btcpay_api_key'] = '';
                    $update['btcpay_store_id'] = '';
                }
                coinsnap_settings_update('coinsnap_bitcoin_voting_options', $update);

                wp_send_json_success(['url' => $url]);
            }
            catch (\Throwable $e) {
                wp_send_json_error("Error processing Ajax request.");
            }
    }

    function coinsnap_bitcoin_voting_enqueue_scripts(){
        
        global $post;
    
        if ( is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'coinsnap_bitcoin_voting') ) {
            wp_enqueue_style('coinsnap-bitcoin-voting-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], COINSNAP_BITCOIN_VOTING_VERSION);
            wp_enqueue_script('coinsnap-bitcoin-voting-script', plugin_dir_url(__FILE__) . 'assets/js/voting.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);

            $provider_options = (array) get_option('coinsnap_bitcoin_voting_options', []);
            wp_enqueue_script('coinsnap-bitcoin-voting-popup-script', plugin_dir_url(__FILE__) . 'assets/js/popup.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);

            // Only public data goes to the browser: no API key, store ID or server URL.
            $sharedDataArray = [
                'provider'   => (($provider_options['provider'] ?? '') === 'btcpay') ? 'btcpay' : 'coinsnap',
                'invoiceUrl' => rest_url('coinsnap-bitcoin-voting/v1/invoice'),
                'statusUrl'  => rest_url('voting/v1/check-payment-status/'),
                'resultsUrl' => rest_url('voting/v1/voting_results/'),
            ];

            wp_enqueue_script('coinsnap-bitcoin-voting-shared-script', plugin_dir_url(__FILE__) . 'assets/js/shared.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
            wp_localize_script('coinsnap-bitcoin-voting-shared-script', 'Coinsnap_Bitcoin_Voting_sharedData', $sharedDataArray);
        }
        
        
        
        
    }

    function coinsnap_bitcoin_voting_enqueue_admin_styles($hook){
        $post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
        $post_type = (filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 
            ((!empty($post_id))? get_post_type($post_id) : '');
        $page = (filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
        
        if($page === 'coinsnap-bitcoin-voting' || $post_type === 'coinsnap-polls'){
            wp_enqueue_script('coinsnap-bitcoin-voting-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
            wp_localize_script('coinsnap-bitcoin-voting-admin-script', 'coinsnap_bitcoin_voting_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                'post' => $post_id
            ));
        }
        
        wp_enqueue_style('coinsnap-bitcoin-voting-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], COINSNAP_BITCOIN_VOTING_VERSION);
    }

    function coinsnap_bitcoin_voting_verify_nonce($nonce, $action){
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Security check failed', 'coinsnap-bitcoin-voting'));
        }
    }
    
    public function getAuthorizeUrl(string $baseUrl, array $permissions, ?string $applicationName, ?bool $strict, ?bool $selectiveStores, ?string $redirectToUrlAfterCreation, ?string $applicationIdentifier): string
    {
        $url = rtrim($baseUrl, '/') . '/api-keys/authorize';

        $params = [];
        $params['permissions'] = $permissions;
        $params['applicationName'] = $applicationName;
        $params['strict'] = $strict;
        $params['selectiveStores'] = $selectiveStores;
        $params['redirect'] = $redirectToUrlAfterCreation;
        $params['applicationIdentifier'] = $applicationIdentifier;

        // Take out NULL values
        $params = array_filter($params, function ($value) {
            return $value !== null;
        });

        $queryParams = [];

        foreach ($params as $param => $value) {
            if ($value === true) {
                $value = 'true';
            }
            if ($value === false) {
                $value = 'false';
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === true) {
                        $item = 'true';
                    }
                    if ($item === false) {
                        $item = 'false';
                    }
                    $queryParams[] = $param . '=' . urlencode((string)$item);
                }
            } else {
                $queryParams[] = $param . '=' . urlencode((string)$value);
            }
        }

        $queryParams = implode("&", $queryParams);
        $url .= '?' . $queryParams;
        return $url;
    }
}
new Coinsnap_Bitcoin_Voting();

add_action('init', function() {
    // Setting up and handling custom endpoint for api key redirect from BTCPay Server.
    add_rewrite_endpoint('voting-btcpay-settings-callback', EP_ROOT);
});

// To be able to use the endpoint without appended url segments we need to do this.
add_filter('request', function($vars) {
    if (isset($vars['voting-btcpay-settings-callback'])) {
        $vars['voting-btcpay-settings-callback'] = true;
    }
    return $vars;
});

if(!function_exists('coinsnap_settings_update')){
    function coinsnap_settings_update($option,$data){
        
        $form_data = get_option($option, []);
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option($option,$form_data);
    }
}

// BTCPay posts the new API key here from its own site. The URL is public and the
// admin's login cookie is usually not sent, so the proof is the one-time state
// created when the admin clicked "Generate API key".
add_action( 'template_redirect', function(){

    global $wp_query;
    if (!isset($wp_query->query_vars['voting-btcpay-settings-callback'])
        && !isset($_GET['voting-btcpay-settings-callback'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $settingsUrl = admin_url('admin.php?page=coinsnap-bitcoin-voting');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the one-time state is the token
    $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    if (!Coinsnap_Bitcoin_Voting_Security::btcpay_consume_state($state)) {
        wp_safe_redirect($settingsUrl);
        exit;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the state above
    $apiKey = isset($_POST['apiKey']) ? sanitize_text_field(wp_unslash($_POST['apiKey'])) : '';
    $options = (array) get_option('coinsnap_bitcoin_voting_options', []);
    $btcpayUrl = (string) ($options['btcpay_url'] ?? ''); // from settings, never from the request

    // Ask BTCPay what the key can do; permissions and store ID in the POST are ignored.
    $permissions = Coinsnap_Bitcoin_Voting_Security::btcpay_key_permissions($btcpayUrl, $apiKey);
    if ($permissions === null) {
        wp_safe_redirect($settingsUrl);
        exit;
    }
    $storeId = Coinsnap_Bitcoin_Voting_Security::btcpay_key_store($permissions);
    if (!Coinsnap_Bitcoin_Voting_Security::btcpay_key_is_acceptable($permissions, $storeId)) {
        wp_safe_redirect($settingsUrl);
        exit;
    }

    coinsnap_settings_update('coinsnap_bitcoin_voting_options', [
        'btcpay_api_key'  => $apiKey,
        'btcpay_store_id' => $storeId,
        'provider'        => 'btcpay',
    ]);
    // The callback has no admin session, so register the webhook here.
    Coinsnap_Bitcoin_Voting_Security::register_webhook();

    wp_safe_redirect($settingsUrl);
    exit;
});
