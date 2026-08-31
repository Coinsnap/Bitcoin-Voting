<?php
if (!defined('ABSPATH')){ exit; }
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

global $wpdb;
$tables = array(
    $wpdb->prefix . 'voting_payments',
);

foreach ($tables as $table) {
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s",$table));
}

$options = array(
    'coinsnap_bitcoin_voting_options',
    'coinsnap_webhook_secret'
);

foreach ($options as $option) {
    delete_option($option);
}