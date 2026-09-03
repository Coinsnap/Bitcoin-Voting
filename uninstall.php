<?php
if (!defined('ABSPATH')){ exit; }
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

global $wpdb;
$coinsnap_bitcoin_voting_tables = array(
    $wpdb->prefix . 'voting_payments',
);

foreach ($coinsnap_bitcoin_voting_tables as $coinsnap_bitcoin_voting_table) {
    $wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $coinsnap_bitcoin_voting_table ) . "`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
}

$coinsnap_bitcoin_voting_options = array(
    'coinsnap_bitcoin_voting_options',
    'coinsnap_webhook_secret',
    'cbv_webhook',
);

foreach ($coinsnap_bitcoin_voting_options as $coinsnap_bitcoin_voting_option) {
    delete_option($coinsnap_bitcoin_voting_option);
}
