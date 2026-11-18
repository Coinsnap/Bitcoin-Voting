<?php
if (!defined('ABSPATH')){ exit; }
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

// Remove only what this plugin created. Never touch a name another Coinsnap
// plugin may use, such as the shared coinsnap_webhook_secret (6.4).
global $wpdb;
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'voting_payments'));

foreach ([
    'coinsnap_bitcoin_voting_options',
    'coinsnap_bitcoin_voting_webhook_secrets',
    'coinsnap_bitcoin_voting_db_version',
    'coinsnap_bitcoin_voting_security_upgraded_at',
] as $coinsnap_bitcoin_voting_option) {
    delete_option($coinsnap_bitcoin_voting_option);
}
delete_transient('coinsnap_bitcoin_voting_btcpay_state');
