<?php
/**
 * Payment fallback test, run inside a throwaway WordPress with the plugin active:
 *   wp eval-file wp-content/plugins/coinsnap-bitcoin-voting/tests/payment-fallback-test.php
 *
 * Covers a store without Lightning (the popup gets a BIP21 link and the checkout link),
 * exchange rates (cached, last good rates on a CoinGecko 429, no rates for SATS polls,
 * readable errors) and counting a vote from the status check when no webhook arrives.
 * The API is faked with pre_http_request; nothing leaves the site. The plugin's settings,
 * webhook secrets and cached rates are put back at the end, and the test's polls and rows
 * are removed. Still: don't run it on a real site.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table = $wpdb->prefix . 'voting_payments';

$GLOBALS['f_fail']     = 0;
$GLOBALS['f_invoices'] = array();
$GLOBALS['f_calls']    = array( 'rates' => 0, 'invoice_get' => 0 );
$GLOBALS['f_rates']    = 200;
$GLOBALS['f_posts']    = array();

function f_ok( $name, $cond ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
	$GLOBALS['f_fail'] += $cond ? 0 : 1;
}
function f_resp( $code, $body ) {
	return array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array() ), 'cookies' => array() );
}

// Keep the site's own values and put them back however the test ends.
$f_keep = array();
foreach ( array( 'coinsnap_bitcoin_voting_options', 'coinsnap_bitcoin_voting_webhook_secrets', 'coinsnap_bitcoin_voting_rates_last_good', '_transient_coinsnap_bitcoin_voting_rates', '_transient_timeout_coinsnap_bitcoin_voting_rates' ) as $name ) {
	$f_keep[ $name ] = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
}
register_shutdown_function( function () use ( $f_keep, $table ) {
	global $wpdb;
	foreach ( $f_keep as $name => $value ) {
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		if ( is_array( $value ) ) {
			$wpdb->insert( $wpdb->options, array( 'option_name' => $name, 'option_value' => $value['option_value'], 'autoload' => $value['autoload'] ) );
		}
		wp_cache_delete( $name, 'options' );
	}
	wp_cache_delete( 'alloptions', 'options' );
	foreach ( $GLOBALS['f_posts'] as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE poll_id = %s", (string) $id ) );
		wp_delete_post( $id, true );
	}
	foreach ( array_merge( array_keys( $GLOBALS['f_invoices'] ), array( 'notOurs123' ) ) as $inv ) {
		delete_transient( 'coinsnap_bitcoin_voting_chk_' . md5( $inv ) );
		foreach ( get_posts( array( 'post_type' => 'coinsnap-voting-pds', 'post_status' => 'any', 'meta_key' => '_coinsnap_bitcoin_voting_payment_id', 'meta_value' => $inv, 'fields' => 'ids' ) ) as $pid ) {
			wp_delete_post( $pid, true );
		}
	}
} );

// Fake API: a store with on-chain only, like the E2E store.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$method = strtoupper( $args['method'] ?? 'GET' );
	if ( false !== strpos( $url, 'api.coingecko.com' ) ) {
		$GLOBALS['f_calls']['rates']++;
		if ( 200 !== $GLOBALS['f_rates'] ) {
			return f_resp( $GLOBALS['f_rates'], array( 'status' => array( 'error_code' => 429 ) ) );
		}
		return f_resp( 200, array( 'rates' => array( 'btc' => array( 'value' => 1 ), 'eur' => array( 'value' => 50000 ) ) ) );
	}
	if ( 'POST' === $method && preg_match( '#/stores/[^/]+/invoices$#', $url ) ) {
		$body = json_decode( $args['body'], true );
		$id   = 'inv' . wp_generate_password( 12, false, false );
		$meta = $body['metadata'] ?? array();
		$GLOBALS['f_invoices'][ $id ] = array(
			'id' => $id, 'status' => 'New', 'additionalStatus' => null, 'amount' => (int) $body['amount'], 'currency' => $body['currency'],
			'metadata' => $meta, 'createdAt' => time(), 'checkoutLink' => 'https://stores.coinsnap.test/i/' . $id,
			'lightningInvoice' => '', 'onchainAddress' => 'bc1qgf6kc8n3kq4epjd7aytndrmu303v0pmcfpkun8',
			'bip21' => 'bitcoin:bc1qgf6kc8n3kq4epjd7aytndrmu303v0pmcfpkun8?amount=0.000006',
			'qrCodes' => array( 'lightningQR' => '', 'bitcoinQR' => 'data:image/png;base64,QUJD', 'bip21QR' => 'data:image/png;base64,QklQMjE=' ),
		);
		return f_resp( 200, $GLOBALS['f_invoices'][ $id ] );
	}
	if ( 'GET' === $method && preg_match( '#/invoices/([A-Za-z0-9]+)$#', $url, $m ) ) {
		$GLOBALS['f_calls']['invoice_get']++;
		return isset( $GLOBALS['f_invoices'][ $m[1] ] ) ? f_resp( 200, $GLOBALS['f_invoices'][ $m[1] ] ) : f_resp( 400, array( 'message' => 'not found' ) );
	}
	return new WP_Error( 'http_request_failed', 'offline test' );
}, 10, 3 );

function f_poll( $currency, $amount ) {
	$id = wp_insert_post( array( 'post_type' => 'coinsnap-polls', 'post_status' => 'publish', 'post_title' => "Test poll $currency" ) );
	foreach ( array( 'active' => '1', 'amount' => (string) $amount, 'currency' => $currency, 'option_1' => 'Red', 'option_2' => 'Blue', 'collect_donor_info' => '1', 'first_name_visibility' => 'optional' ) as $k => $v ) {
		update_post_meta( $id, '_coinsnap_bitcoin_voting_polls_' . $k, $v );
	}
	$GLOBALS['f_posts'][] = $id;
	return $id;
}
function f_vote( $poll_id ) {
	$req = new WP_REST_Request( 'POST', '/coinsnap-bitcoin-voting/v1/invoice' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( array( 'poll_id' => $poll_id, 'option_id' => 2, 'page_url' => home_url( '/vote/' ), 'first_name' => 'Jane' ) ) );
	return rest_do_request( $req );
}
function f_status( $inv ) {
	delete_transient( 'coinsnap_bitcoin_voting_chk_' . md5( $inv ) );
	return (string) ( rest_do_request( new WP_REST_Request( 'GET', '/voting/v1/check-payment-status/' . $inv ) )->get_data()['status'] ?? '' );
}
function f_reset_rates() {
	delete_transient( 'coinsnap_bitcoin_voting_rates' );
	delete_option( 'coinsnap_bitcoin_voting_rates_last_good' );
	$GLOBALS['f_calls']['rates'] = 0;
}

update_option( 'coinsnap_bitcoin_voting_options', array( 'provider' => 'coinsnap', 'coinsnap_api_key' => 'test-key', 'coinsnap_store_id' => 'STORE1' ) );
update_option( 'coinsnap_bitcoin_voting_webhook_secrets', array() ); // no webhook registered
$cfg  = Coinsnap_Bitcoin_Voting_Security::config();
$sats = f_poll( 'SATS', 600 );
$eur  = f_poll( 'EUR', 2 );

// 1. Exchange rates.
f_reset_rates();
$GLOBALS['f_rates'] = 429;
$p = Coinsnap_Bitcoin_Voting_Security::poll_price( $sats, $cfg );
f_ok( 'SATS poll priced while CoinGecko answers 429', ! empty( $p['result'] ) && 600 === $p['sats'] );
f_ok( 'SATS poll needs no exchange rate', 0 === $GLOBALS['f_calls']['rates'] );

$p   = Coinsnap_Bitcoin_Voting_Security::poll_price( $eur, $cfg );
$msg = method_exists( 'Coinsnap_Bitcoin_Voting_Security', 'price_error_message' ) ? Coinsnap_Bitcoin_Voting_Security::price_error_message( $p, 'EUR' ) : (string) ( $p['error'] ?? '' );
f_ok( 'EUR poll without any rates: readable error, not the raw code', empty( $p['result'] ) && false === strpos( $msg, 'Error' ) && false !== strpos( $msg, 'exchange rate' ) );
$res = f_vote( $eur );
f_ok( 'invoice route says why it is unavailable', 503 === $res->get_status() && false !== strpos( (string) ( $res->as_error()->get_error_message() ), 'exchange rate' ) );

f_reset_rates();
$GLOBALS['f_rates'] = 200;
Coinsnap_Bitcoin_Voting_Security::poll_price( $eur, $cfg );
Coinsnap_Bitcoin_Voting_Security::poll_price( $eur, $cfg );
f_ok( 'rates are cached: one CoinGecko call for two prices', 1 === $GLOBALS['f_calls']['rates'] );

delete_transient( 'coinsnap_bitcoin_voting_rates' );
$GLOBALS['f_rates'] = 429;
$p = Coinsnap_Bitcoin_Voting_Security::poll_price( $eur, $cfg );
f_ok( 'CoinGecko 429: last good rates are used', ! empty( $p['result'] ) && 4000 === $p['sats'] );
$before = $GLOBALS['f_calls']['rates'];
Coinsnap_Bitcoin_Voting_Security::poll_price( $eur, $cfg );
f_ok( 'after a 429 CoinGecko is not asked again on the next request', $before === $GLOBALS['f_calls']['rates'] );
$GLOBALS['f_rates'] = 200;

// 2. Store without Lightning: the browser gets a BIP21 link with the amount and the checkout link.
$res = f_vote( $sats );
$inv = (array) $res->get_data();
$id  = (string) ( $inv['id'] ?? '' );
f_ok( 'invoice created for the SATS poll', 200 === $res->get_status() && '' !== $id );
f_ok( 'response has the BIP21 link with the amount', 'bitcoin:bc1qgf6kc8n3kq4epjd7aytndrmu303v0pmcfpkun8?amount=0.000006' === ( $inv['bip21'] ?? '' ) );
f_ok( 'response has the BIP21 QR code', 'data:image/png;base64,QklQMjE=' === ( $inv['qrCodes']['bip21QR'] ?? '' ) );
f_ok( 'response has the checkout link', 'https://stores.coinsnap.test/i/' . $id === ( $inv['checkoutLink'] ?? '' ) );
f_ok( 'Lightning stays empty', '' === ( $inv['lightningInvoice'] ?? 'x' ) && '' === ( $inv['qrCodes']['lightningQR'] ?? 'x' ) );
if ( method_exists( 'Coinsnap_Bitcoin_Voting_Security', 'bip21' ) ) {
	f_ok( 'BIP21 built from address and sats when the API has none', 'bitcoin:bc1qtest0000000000000000000000?amount=0.00012345' === Coinsnap_Bitcoin_Voting_Security::bip21( '', 'bc1qtest0000000000000000000000', 12345 )
		&& 'bitcoin:bc1qtest0000000000000000000000?amount=1' === Coinsnap_Bitcoin_Voting_Security::bip21( '', 'bc1qtest0000000000000000000000', 100000000 ) );
} else {
	f_ok( 'BIP21 built from address and sats when the API has none', false );
}

// 3. No webhook: the status check asks the API, with the same checks as the webhook.
$count = function ( $inv ) use ( $wpdb, $table ) {
	return array(
		(string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE payment_id = %s", $inv ) ),
		count( get_posts( array( 'post_type' => 'coinsnap-voting-pds', 'post_status' => 'any', 'meta_key' => '_coinsnap_bitcoin_voting_payment_id', 'meta_value' => $inv, 'fields' => 'ids' ) ) ),
	);
};
f_ok( 'unpaid invoice stays pending', 'pending' === f_status( $id ) && array( 'pending', 0 ) === $count( $id ) );

$GLOBALS['f_invoices'][ $id ]['status'] = 'Settled';
$GLOBALS['f_invoices'][ $id ]['amount'] = 599;
f_ok( 'settled with the wrong amount: not counted', 'pending' === f_status( $id ) && array( 'pending', 0 ) === $count( $id ) );
$GLOBALS['f_invoices'][ $id ]['amount']             = 600;
$GLOBALS['f_invoices'][ $id ]['metadata']['pollId'] = (string) $eur;
f_ok( 'settled invoice of another poll: not counted', 'pending' === f_status( $id ) && array( 'pending', 0 ) === $count( $id ) );
$GLOBALS['f_invoices'][ $id ]['metadata']['pollId'] = (string) $sats;
$GLOBALS['f_invoices'][ $id ]['currency']           = 'EUR';
f_ok( 'settled in another currency: not counted', 'pending' === f_status( $id ) && array( 'pending', 0 ) === $count( $id ) );
$GLOBALS['f_invoices'][ $id ]['currency'] = 'SATS';

f_ok( 'settled invoice is counted by the status check', 'completed' === f_status( $id ) && array( 'completed', 1 ) === $count( $id ) );
f_ok( 'asking again counts nothing twice', 'completed' === f_status( $id ) && array( 'completed', 1 ) === $count( $id ) );

$calls = $GLOBALS['f_calls']['invoice_get'];
f_ok( 'unknown invoice ID: pending, the API is not asked', 'pending' === f_status( 'notOurs123' ) && $calls === $GLOBALS['f_calls']['invoice_get'] );

$res2 = f_vote( $sats );
$id2  = (string) ( $res2->get_data()['id'] ?? '' );
$GLOBALS['f_invoices'][ $id2 ]['status'] = 'Settled';
delete_transient( 'coinsnap_bitcoin_voting_chk_' . md5( $id2 ) );
set_transient( 'coinsnap_bitcoin_voting_chk_' . md5( $id2 ), 1, 10 );
$calls = $GLOBALS['f_calls']['invoice_get'];
rest_do_request( new WP_REST_Request( 'GET', '/voting/v1/check-payment-status/' . $id2 ) );
f_ok( 'status checks of one invoice are throttled', $calls === $GLOBALS['f_calls']['invoice_get'] );
f_ok( 'after the pause it is counted', 'completed' === f_status( $id2 ) );

$results = rest_do_request( new WP_REST_Request( 'GET', '/voting/v1/voting_results/' . $sats ) )->get_data();
f_ok( 'two paid votes in the results', 2 === ( $results['total'] ?? 0 ) );

echo $GLOBALS['f_fail'] ? $GLOBALS['f_fail'] . " failed\n" : "all passed\n";
if ( $GLOBALS['f_fail'] ) {
	exit( 1 );
}
