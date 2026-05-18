<?php
if (!defined('ABSPATH')){ exit; }
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

global $wpdb;
$cbv_tables = array(
    $wpdb->prefix . 'voting_payments',
);

foreach ($cbv_tables as $cbv_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $cbv_table ) . "`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

$cbv_options = array(
    'coinsnap_bitcoin_voting_options',
    'coinsnap_webhook_secret',
    'cbv_webhook',
);

foreach ($cbv_options as $cbv_option) {
    delete_option($cbv_option);
}