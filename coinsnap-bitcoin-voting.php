<?php
/*
 * Plugin Name:        Coinsnap Bitcoin Voting
 * Plugin URI:         https://coinsnap.io/coinsnap-bitcoin-voting-plugin/
 * Description:        Easy Bitcoin voting on a WordPress website
 * Version:            1.2.3
 * Author:             Coinsnap
 * Author URI:         https://coinsnap.io/
 * Text Domain:        Bitcoin-Voting
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
if(!defined( 'COINSNAP_BITCOIN_VOTING_PLUGIN_DIR' ) ){ define('COINSNAP_BITCOIN_VOTING_PLUGIN_DIR', plugin_dir_url(__FILE__));}
if(!defined( 'COINSNAP_BITCOIN_VOTING_PLUGIN_PATH' ) ){ define('COINSNAP_BITCOIN_VOTING_PLUGIN_PATH', plugin_dir_path(__FILE__));}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}

// Load shared Coinsnap vendor library (CoinsnapCore namespace).
require_once COINSNAP_BITCOIN_VOTING_PLUGIN_PATH . 'vendor/coinsnap-core.php';

/**
 * Return a cached PluginInstance configured for Bitcoin Voting.
 *
 * @return \CoinsnapCore\PluginInstance
 */
function coinsnap_bitcoin_voting_plugin_instance(): \CoinsnapCore\PluginInstance {
    static $inst = null;
    if ( null === $inst ) {
        $inst = new \CoinsnapCore\PluginInstance( array(
            'plugin_name'              => 'Coinsnap Bitcoin Voting',
            'option_key'               => 'coinsnap_bitcoin_voting_options',
            'webhook_key'              => 'cbv_webhook',
            'rest_namespace'           => 'voting/v1',
            'table_suffix'             => 'voting_payments',
            'referral_code'            => COINSNAP_BITCOIN_VOTING_REFERRAL_CODE,
            'text_domain'              => 'coinsnap-bitcoin-voting',
            'log_dir_name'             => 'cbv-logs',
            'log_file_name'            => 'cbv.log',
            'btcpay_callback_endpoint' => 'cbv-btcpay-callback',
            'btcpay_app_name'          => 'CoinsnapBitcoinVoting',
            'menu_slug'                => 'coinsnap-bitcoin-voting',
            'plugin_url'               => COINSNAP_BITCOIN_VOTING_PLUGIN_DIR,
            'plugin_dir'               => COINSNAP_BITCOIN_VOTING_PLUGIN_PATH,
        ) );
    }
    return $inst;
}

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
        add_action('admin_notices', [$this, 'maybe_show_setup_notice']);

        // Register BTCPay OAuth callback (replaces old voting-btcpay-settings-callback).
        \CoinsnapCore\Auth\BTCPayAuthorizer::register_callback(coinsnap_bitcoin_voting_plugin_instance());

        // AJAX handlers â€” delegate to vendor AjaxHandlers.
        add_action('wp_ajax_cbv_connection_handler', [$this, 'handle_connection_check']);
        add_action('wp_ajax_cbv_btcpay_apiurl_handler', [$this, 'handle_btcpay_url']);
        add_action('wp_ajax_cbv_reregister_webhook', [$this, 'handle_reregister_webhook']);

        // Auto-register webhook when settings are saved.
        add_action('update_option_coinsnap_bitcoin_voting_options', [$this, 'maybe_register_webhook_on_save'], 10, 2);
    }

    public function maybe_show_setup_notice(): void {
        \CoinsnapCore\Admin\SettingsPage::maybe_show_setup_notice(coinsnap_bitcoin_voting_plugin_instance());
    }

    public function handle_connection_check(): void {
        \CoinsnapCore\Admin\AjaxHandlers::handle_connection_check(coinsnap_bitcoin_voting_plugin_instance());
    }

    public function handle_btcpay_url(): void {
        \CoinsnapCore\Admin\AjaxHandlers::handle_btcpay_url(coinsnap_bitcoin_voting_plugin_instance());
    }

    public function handle_reregister_webhook(): void {
        $nonce = filter_input(INPUT_POST, 'apiNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!wp_verify_nonce($nonce, 'coinsnap-ajax-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $inst          = coinsnap_bitcoin_voting_plugin_instance();
        $settings      = \CoinsnapCore\Admin\SettingsPage::get_settings_for($inst);
        $provider_name = $settings['payment_provider'] ?? 'coinsnap';

        try {
            $provider = \CoinsnapCore\Util\ProviderFactory::create($inst);
            $provider->check_webhook();
            delete_option($inst->webhook_key());

            $result = $provider->register_webhook();

            if (!isset($result['error']) && isset($result['result'])) {
                $stored                   = [];
                $stored[$provider_name]   = [
                    'id'     => $result['result']['id'],
                    'secret' => $result['result']['secret'],
                    'url'    => $result['result']['url'],
                ];
                update_option($inst->webhook_key(), $stored);
                wp_send_json_success([
                    'message' => 'Webhook registered successfully',
                    'url'     => $result['result']['url'],
                    'id'      => $result['result']['id'],
                ]);
            } else {
                wp_send_json_error($result['message'] ?? 'Registration failed');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function maybe_register_webhook_on_save($old_value, $new_value): void {
        if (!is_array($new_value)) {
            return;
        }

        $provider_name = $new_value['payment_provider'] ?? 'coinsnap';

        if ('btcpay' === $provider_name) {
            $has_creds = !empty($new_value['btcpay_api_key'])
                && !empty($new_value['btcpay_store_id'])
                && !empty($new_value['btcpay_host']);
        } else {
            $has_creds = !empty($new_value['coinsnap_api_key'])
                && !empty($new_value['coinsnap_store_id']);
        }

        if (!$has_creds) {
            return;
        }

        try {
            $inst     = coinsnap_bitcoin_voting_plugin_instance();
            $provider = \CoinsnapCore\Util\ProviderFactory::create($inst);

            if ($provider->check_webhook()) {
                return;
            }

            $result = $provider->register_webhook();

            if (!isset($result['error']) && isset($result['result'])) {
                $stored               = [];
                $stored[$provider_name] = [
                    'id'     => $result['result']['id'],
                    'secret' => $result['result']['secret'],
                    'url'    => $result['result']['url'],
                ];
                update_option($inst->webhook_key(), $stored);
            }
        } catch (\Exception $e) {
            // Silently fail â€” user can manually re-register via button.
        }
    }

    function coinsnap_bitcoin_voting_enqueue_scripts(){
        
        global $post;
    
        if ( is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'coinsnap_bitcoin_voting') ) {
            wp_enqueue_style('coinsnap-bitcoin-voting-style', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/css/style.css', [], COINSNAP_BITCOIN_VOTING_VERSION);
            wp_enqueue_script('coinsnap-bitcoin-voting-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/voting.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);

            $provider_options = \CoinsnapCore\Admin\SettingsPage::get_settings_for(coinsnap_bitcoin_voting_plugin_instance());
            wp_enqueue_script('coinsnap-bitcoin-voting-popup-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/popup.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
            
            $sharedDataArray = [
                'provider' => $provider_options['payment_provider'],
                'nonce'    => wp_create_nonce('wp_rest'),
            ];
            
            if ($provider_options['payment_provider'] === 'btcpay') {
                $sharedDataArray['btcpayStoreId'] = $provider_options['btcpay_store_id'];
                $sharedDataArray['btcpayApiKey']  = $provider_options['btcpay_api_key'];
                $sharedDataArray['btcpayUrl']     = $provider_options['btcpay_host'];
            } else {
                $sharedDataArray['coinsnapStoreId'] = $provider_options['coinsnap_store_id'];
                $sharedDataArray['coinsnapApiKey']  = $provider_options['coinsnap_api_key'];
            }

            wp_enqueue_script('coinsnap-bitcoin-voting-shared-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/shared.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
            wp_localize_script('coinsnap-bitcoin-voting-shared-script', 'Coinsnap_Bitcoin_Voting_sharedData', $sharedDataArray);
        }
    }

    function coinsnap_bitcoin_voting_enqueue_admin_styles($hook){
        $post_id   = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $post_type = filter_input(INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            ?? ((!empty($post_id)) ? get_post_type($post_id) : '');
        $page      = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

        // Vendor admin UI on settings pages.
        if (false !== strpos($page, 'coinsnap-bitcoin-voting')) {
            $inst = coinsnap_bitcoin_voting_plugin_instance();
            wp_register_style('coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/css/admin.css', [], COINSNAP_CORE_VERSION);
            wp_register_script('coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], COINSNAP_CORE_VERSION, true);
            wp_localize_script('coinsnap-core-admin', 'CoinsnapCoreAdmin', [
                'option_key'        => $inst->option_key(),
                'ajax_url'          => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce('coinsnap-ajax-nonce'),
                'connection_action' => 'cbv_connection_handler',
                'btcpay_action'     => 'cbv_btcpay_apiurl_handler',
                'webhook_action'    => 'cbv_reregister_webhook',
            ]);
            wp_enqueue_style('coinsnap-core-admin');
            wp_enqueue_script('coinsnap-core-admin');
        }

        // Old admin script for poll CPT pages.
        if ($page === 'coinsnap-bitcoin-voting' || $post_type === 'coinsnap-polls') {
            wp_enqueue_script('coinsnap-bitcoin-voting-admin-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/admin.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
            wp_localize_script('coinsnap-bitcoin-voting-admin-script', 'coinsnap_bitcoin_voting_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('coinsnap-ajax-nonce'),
                'post'     => $post_id,
            ]);
        }
        
        wp_enqueue_style('coinsnap-bitcoin-voting-admin-style', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/css/admin-style.css', [], COINSNAP_BITCOIN_VOTING_VERSION);
    }
}
new Coinsnap_Bitcoin_Voting();
