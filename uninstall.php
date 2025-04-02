<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$tables = array(
    $wpdb->prefix . 'voting_payments',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

$options = array(
    'bitcoin_voting_options',
    'coinsnap_webhook_secret'
);

foreach ($options as $option) {
    delete_option($option);
}