<?php
/*
 * Plugin Name:        Coinsnap Bitcoin Voting
 * Plugin URI:         https://coinsnap.io/coinsnap-bitcoin-voting-plugin/
 * Description:        Easy Bitcoin voting on a WordPress website
 * Version:            1.2.0
 * Author:             Coinsnap
 * Author URI:         https://coinsnap.io/
 * Text Domain:        coinsnap-bitcoin-voting
 * Domain Path:         /languages
 * Tested up to:        6.8
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:             true
 */

defined( 'ABSPATH' ) || exit;

if(!defined( 'COINSNAP_BITCOIN_VOTING_REFERRAL_CODE' ) ) { define( 'COINSNAP_BITCOIN_VOTING_REFERRAL_CODE', 'D46835' );}
if(!defined( 'COINSNAP_BITCOIN_VOTING_VERSION' ) ) { define( 'COINSNAP_BITCOIN_VOTING_VERSION', '1.2.0' );}
if(!defined( 'COINSNAP_BITCOIN_VOTING_PHP_VERSION' ) ) { define( 'COINSNAP_BITCOIN_VOTING_PHP_VERSION', '8.0' );}
if(!defined( 'COINSNAP_BITCOIN_VOTING_PLUGIN_DIR' ) ){ define('COINSNAP_BITCOIN_VOTING_PLUGIN_DIR',plugin_dir_url(__FILE__));}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}

// Plugin settings
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

function coinsnap_bitcoin_voting_create_voting_payments_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'voting_payments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_id VARCHAR(255) NOT NULL,
        poll_id VARCHAR(255) NOT NULL,
        option_id INT(4) NOT NULL,
        option_title VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

class Coinsnap_Bitcoin_Voting
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'coinsnap_bitcoin_voting_enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'coinsnap_bitcoin_voting_enqueue_admin_styles']);
        add_action('wp_ajax_coinsnap_bitcoin_voting_btcpay_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
        add_action('wp_ajax_coinsnap_bitcoin_voting_connection_handler', [$this, 'coinsnapConnectionHandler']);
    }
    
    public function coinsnapConnectionHandler(){
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

                if ($storePaymentMethods['code'] === 200) {
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
                    $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];
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
        return ($coinsnap_bitcoin_voting_data['provider'] === 'btcpay')? 'btcpay' : 'coinsnap';
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
            $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
            if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
                wp_die('Unauthorized!', '', ['response' => 401]);
            }

            if ( current_user_can( 'manage_options' ) ) {
                $host = filter_var(filter_input(INPUT_POST,'host',FILTER_SANITIZE_STRING), FILTER_VALIDATE_URL);

                if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                    wp_send_json_error("Error validating BTCPayServer URL.");
                }

                $permissions = array_merge([
                    'btcpay.store.canviewinvoices',
                    'btcpay.store.cancreateinvoice',
                    'btcpay.store.canviewstoresettings',
                    'btcpay.store.canmodifyinvoices'
                ],
                [
                    'btcpay.store.cancreatenonapprovedpullpayments',
                    'btcpay.store.webhooks.canmodifywebhooks',
                ]);

                try {
                    // Create the redirect url to BTCPay instance.
                    $url = $this->getAuthorizeUrl(
                        $host,
                        $permissions,
                        'CoinsnapBitcoinVoting',
                        true,
                        true,
                        home_url('?voting-btcpay-settings-callback'),
                        null
                    );

                    // Store the host to options before we leave the site.
                    coinsnap_settings_update(['btcpay_url' => $host]);

                    // Return the redirect url.
                    wp_send_json_success(['url' => $url]);
                }

                catch (\Throwable $e) {

                }
            }
            wp_send_json_error("Error processing Ajax request.");
    }

    function coinsnap_bitcoin_voting_enqueue_scripts()
    {
        
        wp_enqueue_style('coinsnap-bitcoin-voting-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], COINSNAP_BITCOIN_VOTING_VERSION);
        wp_enqueue_script('coinsnap-bitcoin-voting-script', plugin_dir_url(__FILE__) . 'assets/js/voting.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);

        $provider_defaults = [
            'provider' => 'coinsnap',
            'coinsnap_store_id' => '',
            'coinsnap_api_key' => '',
            'btcpay_store_id' => '',
            'btcpay_api_key' => '',
            'btcpay_url' => ''
        ];
        $provider_options = array_merge($provider_defaults, (array) get_option('coinsnap_bitcoin_voting_options', []));
        wp_enqueue_script('coinsnap-bitcoin-voting-popup-script', plugin_dir_url(__FILE__) . 'assets/js/popup.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);

        // Localize script for sharedData
        wp_enqueue_script('coinsnap-bitcoin-voting-shared-script', plugin_dir_url(__FILE__) . 'assets/js/shared.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
        wp_localize_script('coinsnap-bitcoin-voting-shared-script', 'sharedData', [
            'provider' => $provider_options['provider'],
            'coinsnapStoreId' => $provider_options['coinsnap_store_id'],
            'coinsnapApiKey' => $provider_options['coinsnap_api_key'],
            'btcpayStoreId' => $provider_options['btcpay_store_id'],
            'btcpayApiKey' => $provider_options['btcpay_api_key'],
            'btcpayUrl' => $provider_options['btcpay_url'],
            'nonce' => wp_create_nonce('wp_rest')
        ]);
        
    }

    function coinsnap_bitcoin_voting_enqueue_admin_styles($hook){
        $post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
        wp_enqueue_script('coinsnap-bitcoin-voting-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
        wp_localize_script('coinsnap-bitcoin-voting-admin-script', 'coinsnap_bitcoin_voting_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce( 'coinsnap-ajax-nonce' ),
            'post' => $post_id
        ));
        
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
        $vars['voting-btcpay-nonce'] = wp_create_nonce('coinsnap-bitcoin-voting-btcpay-nonce');
    }
    return $vars;
});

function coinsnap_settings_update($data){
        
        $form_data = get_option('coinsnap_bitcoin_voting_options', []);
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option('coinsnap_bitcoin_voting_options',$form_data);
    } 

// Adding template redirect handling for voting-btcpay-settings-callback.
add_action( 'template_redirect', function(){
    
    global $wp_query;
            
    // Only continue on a voting-btcpay-settings-callback request.    
    if (!isset( $wp_query->query_vars['voting-btcpay-settings-callback'])) {
        return;
    }
    
    if(!isset($wp_query->query_vars['voting-btcpay-nonce']) || !wp_verify_nonce($wp_query->query_vars['voting-btcpay-nonce'],'coinsnap-bitcoin-voting-btcpay-nonce')){
        return;
    }

    $CoinsnapBTCPaySettingsUrl = admin_url('/admin.php?page=coinsnap-bitcoin-voting');
    
    $client = new Coinsnap_Bitcoin_Voting_Client();

    $rawData = file_get_contents('php://input');
    $form_data = get_option('coinsnap_bitcoin_voting_options', []);

    $btcpay_server_url = $form_data['btcpay_url'];
    $btcpay_api_key  = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $request_url = $btcpay_server_url.'/api/v1/stores';
    $request_headers = ['Accept' => 'application/json','Content-Type' => 'application/json','Authorization' => 'token '.$btcpay_api_key];
    $getstores = $client->remoteRequest('GET',$request_url,$request_headers);
            
    if(!isset($getstores['error'])){
        if (count($getstores['body']) < 1){
            $messageAbort = __('Error on verifiying redirected API Key with stored BTCPay Server url. Aborting API wizard. Please try again or continue with manual setup.', 'coinsnap-bitcoin-voting');
            wp_redirect($CoinsnapBTCPaySettingsUrl);
        }
    }
                        
    // Data does get submitted with url-encoded payload, so parse $_POST here.
    if (!empty($_POST)) {
        $data['apiKey'] = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
        if(isset($_POST['permissions'])){
            $permissions = array_map('sanitize_text_field', wp_unslash($_POST['permissions']));
            if(is_array($permissions)){
                foreach ($permissions as $key => $value) {
                    $data['permissions'][$key] = sanitize_text_field($permissions[$key] ?? null);
                }
            }
        }
    }
    
            if (isset($data['apiKey']) && isset($data['permissions'])) {

                $REQUIRED_PERMISSIONS = [
                    'btcpay.store.canviewinvoices',
                    'btcpay.store.cancreateinvoice',
                    'btcpay.store.canviewstoresettings',
                    'btcpay.store.canmodifyinvoices'
                ];
                $OPTIONAL_PERMISSIONS = [
                    'btcpay.store.cancreatenonapprovedpullpayments',
                    'btcpay.store.webhooks.canmodifywebhooks',
                ];
                
                $btcpay_server_permissions = $data['permissions'];
                
                $permissions = array_reduce($btcpay_server_permissions, static function (array $carry, string $permission) {
			return array_merge($carry, [explode(':', $permission)[0]]);
		}, []);

		// Remove optional permissions so that only required ones are left.
		$permissions = array_diff($permissions, $OPTIONAL_PERMISSIONS);

		$hasRequiredPermissions = (empty(array_merge(array_diff($REQUIRED_PERMISSIONS, $permissions), array_diff($permissions, $REQUIRED_PERMISSIONS))))? true : false;
                
                $hasSingleStore = true;
                $storeId = null;
		foreach ($btcpay_server_permissions as $perms) {
                    if (2 !== count($exploded = explode(':', $perms))) { return false; }
                    if (null === ($receivedStoreId = $exploded[1])) { $hasSingleStore = false; }
                    if ($storeId === $receivedStoreId) { continue; }
                    if (null === $storeId) { $storeId = $receivedStoreId; continue; }
                    $hasSingleStore = false;
		}
                
                if ($hasSingleStore && $hasRequiredPermissions) {

                    coinsnap_settings_update([
                        'btcpay_api_key' => $data['apiKey'],
                        'btcpay_store_id' => explode(':', $btcpay_server_permissions[0])[1],
                        'provider' => 'btcpay'
                        ]);
                    
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
                else {
                    //$notice->addNotice('error', __('Please make sure you only select one store on the BTCPay API authorization page.', 'coinsnap-bitcoin-voting'));
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
            }

    //$notice->addNotice('error', __('Error processing the data from Coinsnap. Please try again.', 'coinsnap-bitcoin-voting'));
    wp_redirect($CoinsnapBTCPaySettingsUrl);
    exit();
});
