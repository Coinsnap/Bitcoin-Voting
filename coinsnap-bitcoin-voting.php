<?php
/*
Plugin Name: Bitcoin Voting
Description: Easy Bitcoin voting on a WordPress website
Version: 0.1
Author: Coinsnap
*/

if (!defined('ABSPATH')) {
    exit;
}

// Plugin settings
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-polls.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-public-donors.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-shortcode-voting.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-coinsnap-bitcoin-voting-webhooks.php';

register_activation_hook(__FILE__, 'coinsnap_bitcoin_voting_create_voting_payments_table');
register_deactivation_hook(__FILE__, 'coinsnap_bitcoin_voting_deactivate');

function coinsnap_bitcoin_voting_deactivate()
{
    flush_rewrite_rules();
}

function coinsnap_bitcoin_voting_create_voting_payments_table()
{
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

class Bitcoin_Voting
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'coinsnap_bitcoin_voting_enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'coinsnap_bitcoin_voting_enqueue_admin_styles']);
    }

    function coinsnap_bitcoin_voting_enqueue_scripts()
    {
        wp_enqueue_style('coinsnap-bitcoin-voting-style', plugin_dir_url(__FILE__) . 'styles/style.css', [], '1.0.0');

        wp_enqueue_script('coinsnap-bitcoin-voting-script', plugin_dir_url(__FILE__) . 'js/voting.js', ['jquery'], '1.0.0', true);

        $provider_defaults = [
            'provider' => 'coinsnap',
            'coinsnap_store_id' => '',
            'coinsnap_api_key' => '',
            'btcpay_store_id' => '',
            'btcpay_api_key' => '',
            'btcpay_url' => ''
        ];
        $provider_options = array_merge($provider_defaults, (array) get_option('coinsnap_bitcoin_voting_options', []));
        wp_enqueue_script('coinsnap-bitcoin-voting-popup-script', plugin_dir_url(__FILE__) . 'js/popup.js', ['jquery'], '1.0.0', true);

        // Localize script for sharedData
        wp_enqueue_script('coinsnap-bitcoin-voting-shared-script', plugin_dir_url(__FILE__) . 'js/shared.js', ['jquery'], '1.0.0', true);
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

    function coinsnap_bitcoin_voting_enqueue_admin_styles($hook)
    {
        if ($hook === 'bitcoin-voting_page_bitcoin-donation-list') { // TODO verify that this ALL works
            wp_enqueue_style('coinsnap-bitcoin-voting-admin-style', plugin_dir_url(__FILE__) . 'styles/admin-style.css', [], '1.0.0');
        } else if ($hook === 'toplevel_page_coinsnap_bitcoin_voting') {
            wp_enqueue_style('coinsnap-bitcoin-voting-admin-style', plugin_dir_url(__FILE__) . 'styles/admin-style.css', [], '1.0.0');
            $secret = $this->get_webhook_secret();
            $options = get_option('coinsnap_bitcoin_voting_options', []);
            $ngrok_url = isset($options['ngrok_url']) ? $options['ngrok_url'] : '';
            wp_enqueue_script('coinsnap-bitcoin-voting-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery'], '1.0.0', true);
            wp_localize_script('coinsnap-bitcoin-voting-admin-script', 'adminData', ['webhookSecret' => $secret, 'ngrokUrl' => $ngrok_url]);
        }
    }

    function coinsnap_bitcoin_voting_verify_nonce($nonce, $action)
    {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(__('Security check failed', 'coinsnap_bitcoin_voting'));
        }
    }
}
new Bitcoin_Voting();
