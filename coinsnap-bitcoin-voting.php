<?php
/*
 * Plugin Name:        Coinsnap Bitcoin Voting
 * Plugin URI:         https://coinsnap.io/coinsnap-bitcoin-voting-plugin/
 * Description:        Easy Bitcoin voting on a WordPress website
 * Version:            1.3.0
 * Author:             Coinsnap
 * Author URI:         https://coinsnap.io/
 * Text Domain:        bitcoin-voting
 * Domain Path:         /languages
 * Tested up to:        7.1
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:             true
 */

defined( 'ABSPATH' ) || exit;

if(!defined( 'COINSNAP_BITCOIN_VOTING_REFERRAL_CODE' ) ) { define( 'COINSNAP_BITCOIN_VOTING_REFERRAL_CODE', 'D19833' );}
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
            'text_domain'              => 'bitcoin-voting',
            'log_dir_name'             => 'cbv-logs',
            'log_file_name'            => 'cbv.log',
            'btcpay_callback_endpoint' => 'cbv-btcpay-callback',
            'btcpay_app_name'          => 'CoinsnapBitcoinVoting',
            'menu_slug'                => 'bitcoin-voting',
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
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-confirmation.php';

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
        payment_id VARCHAR(255) NOT NULL UNIQUE,
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

        // Intercept popup mode before vendor handler fires at priority 10.
        add_action(
            'template_redirect',
            function () {
                global $wp_query;

                $inst     = coinsnap_bitcoin_voting_plugin_instance();
                $endpoint = $inst->get( 'btcpay_callback_endpoint' );

                if ( ! isset( $wp_query->query_vars[ $endpoint ] ) ) {
                    return;
                }

                // SECURITY FIX: Require admin capability and valid nonce
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_safe_redirect( home_url() );
                    exit();
                }

                $nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                $user_id = get_current_user_id();
                $nonce_action = 'coinsnap-btcpay-auth-' . $user_id;
                
                if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
                    wp_safe_redirect( admin_url( '/admin.php?page=' . $inst->get( 'menu_slug' ) ) );
                    exit();
                }

                // Check auth session transient
                $auth_session = get_transient( 'coinsnap_btcpay_auth_' . $user_id );
                if ( empty( $auth_session ) ) {
                    wp_safe_redirect( admin_url( '/admin.php?page=' . $inst->get( 'menu_slug' ) ) );
                    exit();
                }

                $popup = filter_input( INPUT_GET, 'popup', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                if ( '1' !== $popup ) {
                    return;
                }

                $option_key   = $inst->option_key();
                $settings_url = admin_url( '/admin.php?page=' . $inst->get( 'menu_slug' ) );
                $form_data    = get_option( $option_key, array() );
                $btcpay_host  = is_array( $form_data ) ? ( $form_data['btcpay_host'] ?? '' ) : '';

                $btcpay_api_key = filter_input( INPUT_POST, 'apiKey', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                if ( empty( $btcpay_api_key ) ) {
                    wp_safe_redirect( $settings_url );
                    exit();
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- BTCPay posts back without a WP nonce.
                $btcpay_perms = isset( $_POST['permissions'] ) && is_array( $_POST['permissions'] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['permissions'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    : array();

                if ( empty( $btcpay_perms ) ) {
                    wp_safe_redirect( $settings_url );
                    exit();
                }

                $required = \CoinsnapCore\Auth\BTCPayAuthorizer::REQUIRED_PERMISSIONS;
                $optional = \CoinsnapCore\Auth\BTCPayAuthorizer::OPTIONAL_PERMISSIONS;

                $base_perms = array_reduce(
                    $btcpay_perms,
                    static function ( array $carry, string $p ) {
                        return array_merge( $carry, array( explode( ':', $p )[0] ) );
                    },
                    array()
                );
                $base_perms   = array_diff( $base_perms, $optional );
                $has_required = empty(
                    array_merge(
                        array_diff( $required, $base_perms ),
                        array_diff( $base_perms, $required )
                    )
                );

                $has_single_store = true;
                $store_id         = null;
                foreach ( $btcpay_perms as $perm ) {
                    $parts = explode( ':', $perm );
                    if ( 2 !== count( $parts ) ) {
                        wp_safe_redirect( $settings_url );
                        exit();
                    }
                    $received = $parts[1] ?? null;
                    if ( null === $received ) {
                        $has_single_store = false;
                    }
                    if ( $store_id === $received ) {
                        continue;
                    }
                    if ( null === $store_id ) {
                        $store_id = $received;
                        continue;
                    }
                    $has_single_store = false;
                }

                $clean_api_key      = sanitize_text_field( $btcpay_api_key );
                $store_id_from_perm = '';

                if ( $has_single_store && $has_required ) {
                    $store_id_from_perm = explode( ':', $btcpay_perms[0] )[1] ?? '';

                    // SECURITY FIX: Verify API key before saving (probe the store)
                    if ( empty( $store_id_from_perm ) && ! empty( $btcpay_host ) ) {
                        $response = wp_remote_get(
                            rtrim( $btcpay_host, '/' ) . '/api/v1/stores',
                            array(
                                'headers' => array(
                                    'Authorization' => 'token ' . $clean_api_key,
                                    'Content-Type'  => 'application/json',
                                ),
                                'timeout' => 20,
                            )
                        );
                        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 300 ) {
                            $stores = json_decode( wp_remote_retrieve_body( $response ), true );
                            if ( is_array( $stores ) && ! empty( $stores[0]['id'] ) ) {
                                $store_id_from_perm = $stores[0]['id'];
                            }
                        } else {
                            // API key failed verification - abort without saving
                            wp_safe_redirect( $settings_url );
                            exit();
                        }
                    }

                    \CoinsnapCore\Auth\BTCPayAuthorizer::update_settings(
                        $option_key,
                        array(
                            'btcpay_api_key'   => $clean_api_key,
                            'btcpay_store_id'  => $store_id_from_perm,
                            'payment_provider' => 'btcpay',
                        )
                    );
                }

                // Clean up auth session
                delete_transient( 'coinsnap_btcpay_auth_' . $user_id );

                $js_data     = wp_json_encode( array(
                    'type'    => 'coinsnap_voting_btcpay_auth',
                    'apiKey'  => $clean_api_key,
                    'storeId' => $store_id_from_perm,
                ) );
                $js_origin   = wp_json_encode( home_url() );
                $js_fallback = wp_json_encode( $settings_url );

                header( 'Content-Type: text/html; charset=utf-8' );
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Authorizing...</title>';
                echo '<script>(function(){';
                echo 'var d=' . $js_data . ';'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo 'if(window.opener&&!window.opener.closed){';
                echo 'window.opener.postMessage(d,' . $js_origin . ');'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo 'window.close();';
                echo '}else{window.location.href=' . $js_fallback . ';}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '})();</script></head><body></body></html>';
                exit();
            },
            5
        );

        // AJAX handlers – delegate to vendor AjaxHandlers.
        add_action('wp_ajax_cbv_connection_handler', [$this, 'handle_connection_check']);

        // Override vendor's btcpay URL handler at priority 5 to add popup support.
        add_action( 'wp_ajax_cbv_btcpay_apiurl_handler', [ $this, 'handle_btcpay_url' ], 5 );

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
        $nonce = filter_input( INPUT_POST, 'apiNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( ! wp_verify_nonce( $nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die( 'Unauthorized!', '', array( 'response' => 401 ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        // SECURITY FIX: Create auth session before redirecting to BTCPay
        $user_id = get_current_user_id();
        $auth_nonce = wp_create_nonce( 'coinsnap-btcpay-auth-' . $user_id );
        set_transient( 'coinsnap_btcpay_auth_' . $user_id, 1, 15 * MINUTE_IN_SECONDS );

        $host = filter_var(
            filter_input( INPUT_POST, 'host', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
            FILTER_VALIDATE_URL
        );
        if ( false === $host || ( substr( $host, 0, 7 ) !== 'http://' && substr( $host, 0, 8 ) !== 'https://' ) ) {
            wp_send_json_error( 'Error validating BTCPay Server URL.' );
        }

        $inst       = coinsnap_bitcoin_voting_plugin_instance();
        $endpoint   = $inst->get( 'btcpay_callback_endpoint' );
        $settings   = \CoinsnapCore\Admin\SettingsPage::get_settings_for( $inst );
        $popup_mode = false;

        if ( ! empty( $settings['ngrok_url'] ) ) {
            $redirect_url = rtrim( $settings['ngrok_url'], '/' ) . '/?' . $endpoint . '&_wpnonce=' . $auth_nonce;
        } elseif ( is_ssl() ) {
            $redirect_url = add_query_arg( [ $endpoint => '1', '_wpnonce' => $auth_nonce ], home_url( '/' ) );
        } else {
            $redirect_url = add_query_arg( [ $endpoint => '1', 'popup' => '1', '_wpnonce' => $auth_nonce ], home_url( '/' ) );
            $popup_mode   = true;
        }

        $permissions = array_merge(
            \CoinsnapCore\Auth\BTCPayAuthorizer::REQUIRED_PERMISSIONS,
            \CoinsnapCore\Auth\BTCPayAuthorizer::OPTIONAL_PERMISSIONS
        );

        try {
            $url = \CoinsnapCore\Auth\BTCPayAuthorizer::get_authorize_url(
                $host,
                $permissions,
                $inst->get( 'btcpay_app_name', 'CoinsnapBitcoinVoting' ),
                true,
                true,
                $redirect_url,
                null
            );

            \CoinsnapCore\Auth\BTCPayAuthorizer::update_settings(
                $inst->option_key(),
                array( 'btcpay_host' => $host )
            );

            wp_send_json_success( array( 'url' => $url, 'popup' => $popup_mode ) );
        } catch ( \Throwable $e ) {
            wp_send_json_error( 'Error processing request.' );
        }
        exit();
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
            wp_enqueue_style('coinsnap-bitcoin-voting-style', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/css/style.css', [], filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/style.css' ));
            wp_enqueue_script('coinsnap-bitcoin-voting-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/voting.js', ['jquery'], filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/voting.js' ), true);

            $provider_options = \CoinsnapCore\Admin\SettingsPage::get_settings_for(coinsnap_bitcoin_voting_plugin_instance());
            wp_enqueue_script('coinsnap-bitcoin-voting-popup-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/popup.js', ['jquery'], filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/popup.js' ), true);
            
            $sharedDataArray = [
                'provider'         => $provider_options['payment_provider'],
                'nonce'            => wp_create_nonce('wp_rest'),
                'rest_url'         => rest_url(),
                'confirmation_url' => Coinsnap_Bitcoin_Voting_Confirmation::get_url(),
                // SECURITY: API keys NEVER sent to frontend
                // Invoice creation happens server-side via REST endpoint
            ];

            wp_enqueue_script('coinsnap-bitcoin-voting-shared-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/shared.js', ['jquery'], filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/shared.js' ), true);
            wp_localize_script('coinsnap-bitcoin-voting-shared-script', 'Coinsnap_Bitcoin_Voting_sharedData', $sharedDataArray);
        }
    }

    function coinsnap_bitcoin_voting_enqueue_admin_styles($hook){
        $post_id   = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $post_type = filter_input(INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            ?? ((!empty($post_id)) ? get_post_type($post_id) : '');
        $page      = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

        // Vendor admin UI on settings pages.
        if (false !== strpos($page, 'bitcoin-voting')) {
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

            // Override vendor .csc-btn-generate to support popup mode on HTTP.
            wp_enqueue_script(
                'coinsnap-btcpay-popup',
                COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/btcpay-popup.js',
                [ 'coinsnap-core-admin' ],
                COINSNAP_BITCOIN_VOTING_VERSION,
                true
            );
        }

        // Old admin script for poll CPT pages.
        if ($page === 'bitcoin-voting' || $post_type === 'coinsnap-polls') {
            wp_enqueue_script('coinsnap-bitcoin-voting-admin-script', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/js/admin.js', ['jquery'], COINSNAP_BITCOIN_VOTING_VERSION, true);
            wp_localize_script('coinsnap-bitcoin-voting-admin-script', 'coinsnap_bitcoin_voting_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('coinsnap-ajax-nonce'),
                'post'     => $post_id,
            ]);
        }
        
        // Admin style only on plugin pages.
        if (false !== strpos($page, 'bitcoin-voting') || $post_type === 'coinsnap-polls') {
            wp_enqueue_style('coinsnap-bitcoin-voting-admin-style', COINSNAP_BITCOIN_VOTING_PLUGIN_DIR . 'assets/css/admin-style.css', [], COINSNAP_BITCOIN_VOTING_VERSION);
        }
    }
}
new Coinsnap_Bitcoin_Voting();
