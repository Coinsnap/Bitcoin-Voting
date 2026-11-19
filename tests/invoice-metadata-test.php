<?php
/**
 * Invoice orderId/metadata test, run inside a throwaway WordPress with the plugin active:
 *   wp eval-file wp-content/plugins/coinsnap-bitcoin-voting/tests/invoice-metadata-test.php
 *
 * For Coinsnap and BTCPay Server: starts a vote through the REST route, captures the
 * create-invoice request (orderId, metadata), then sends a signed Settled webhook and checks the
 * vote is counted once. The API is faked with pre_http_request; nothing leaves the site.
 * It overwrites this plugin's settings and webhook secrets: never run it on a real site.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['t_fail']     = 0;
$GLOBALS['t_sent']     = null;
$GLOBALS['t_invoices'] = array();
function t_ok( $name, $cond ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
	$GLOBALS['t_fail'] += $cond ? 0 : 1;
}
function t_resp( $code, $body ) {
	return array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array() ), 'cookies' => array() );
}

// Fake API: rates, BTCPay payment methods, create invoice, get invoice.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$method = strtoupper( $args['method'] ?? 'GET' );
	if ( false !== strpos( $url, 'api.coingecko.com' ) ) {
		return t_resp( 200, array( 'rates' => array( 'btc' => array( 'value' => 1 ), 'eur' => array( 'value' => 50000 ) ) ) );
	}
	if ( preg_match( '#/payment-methods$#', $url ) && false === strpos( $url, '/invoices/' ) ) {
		return t_resp( 200, array( array( 'enabled' => true, 'paymentMethodId' => 'BTC-LN' ) ) );
	}
	if ( 'POST' === $method && preg_match( '#/stores/[^/]+/invoices$#', $url ) ) {
		$body              = json_decode( $args['body'], true );
		$GLOBALS['t_sent'] = $body;
		$id                = 'inv' . wp_generate_password( 12, false, false );
		$meta              = $body['metadata'] ?? array();
		if ( 0 !== strpos( $url, 'https://btc.test' ) ) {
			$meta['orderId'] = $body['orderId'] ?? 'CS-20261119-0001'; // Coinsnap copies the top-level orderId into metadata.
		}
		$GLOBALS['t_invoices'][ $id ] = array( 'id' => $id, 'status' => 'New', 'additionalStatus' => 'None', 'amount' => (string) $body['amount'], 'currency' => $body['currency'], 'metadata' => $meta, 'createdAt' => time(), 'checkoutLink' => 'https://pay.test/i/' . $id );
		return t_resp( 200, $GLOBALS['t_invoices'][ $id ] );
	}
	if ( preg_match( '#/invoices/([A-Za-z0-9]+)/payment-methods$#', $url ) ) {
		return t_resp( 200, array() );
	}
	if ( 'GET' === $method && preg_match( '#/invoices/([A-Za-z0-9]+)$#', $url, $m ) && isset( $GLOBALS['t_invoices'][ $m[1] ] ) ) {
		return t_resp( 200, $GLOBALS['t_invoices'][ $m[1] ] );
	}
	return new WP_Error( 'http_request_failed', 'offline test' );
}, 10, 3 );

global $wpdb;
$table = $wpdb->prefix . 'voting_payments';

$poll_id = wp_insert_post( array( 'post_type' => 'coinsnap-polls', 'post_status' => 'publish', 'post_title' => 'Next meetup topic' ) );
foreach ( array(
	'active' => '1', 'amount' => '2', 'currency' => 'EUR', 'option_1' => 'Lightning', 'option_2' => 'Mining',
	'collect_donor_info' => '1', 'first_name_visibility' => 'optional', 'last_name_visibility' => 'optional',
	'email_visibility' => 'optional', 'address_visibility' => 'optional', 'custom_field_visibility' => 'optional',
	'custom_field_name' => 'Company',
) as $k => $v ) {
	update_post_meta( $poll_id, '_coinsnap_bitcoin_voting_polls_' . $k, $v );
}

foreach ( array( 'coinsnap', 'btcpay' ) as $provider ) {
	$store = 'btcpay' === $provider ? 'STORE2' : 'STORE1';
	update_option( 'coinsnap_bitcoin_voting_options', array(
		'provider'          => $provider,
		'coinsnap_api_key'  => 'test-key',
		'coinsnap_store_id' => 'STORE1',
		'btcpay_url'        => 'https://btc.test',
		'btcpay_api_key'    => 'test-key',
		'btcpay_store_id'   => 'STORE2',
	) );
	update_option( 'coinsnap_bitcoin_voting_webhook_secrets', array( "$provider:$store" => array( 'secret' => "test-secret-$provider", 'webhook_id' => 'wh' ) ) );
	$GLOBALS['t_sent'] = null;

	$req = new WP_REST_Request( 'POST', '/coinsnap-bitcoin-voting/v1/invoice' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( array(
		'poll_id' => $poll_id, 'option_id' => 2, 'page_url' => home_url( '/vote/' ),
		'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test',
		'street' => 'Main St', 'house_number' => '1', 'postal' => '1010', 'town' => 'Vienna', 'country' => 'Austria', 'custom' => 'ACME',
	) ) );
	$res = rest_do_request( $req );
	$inv = (string) ( $res->get_data()['id'] ?? '' );
	t_ok( "$provider: invoice created", 200 === $res->get_status() && '' !== $inv );

	$sent = is_array( $GLOBALS['t_sent'] ) ? $GLOBALS['t_sent'] : array();
	$m    = $sent['metadata'] ?? array();
	t_ok( "$provider: orderId top level and in metadata", 1 === preg_match( '/^VTNG_[0-9a-f]{16}$/', (string) ( $sent['orderId'] ?? '' ) ) && ( $m['orderId'] ?? '' ) === $sent['orderId'] );
	t_ok( "$provider: matching keys unchanged", (string) $poll_id === ( $m['pollId'] ?? null ) && '2' === ( $m['optionId'] ?? null )
		&& 'Coinsnap Bitcoin Voting' === ( $m['type'] ?? '' ) && 'D19833' === ( $m['referralCode'] ?? '' )
		&& 'Voted for Mining' === ( $m['orderNumber'] ?? '' ) && 'Mining' === ( $m['option'] ?? '' ) && '2 EUR' === ( $m['amountFiat'] ?? '' ) );
	t_ok( "$provider: item fields", 'Vote for Mining (Next meetup topic)' === ( $m['itemDesc'] ?? '' ) && "poll-$poll_id-option-2" === ( $m['itemCode'] ?? '' )
		&& 'Next meetup topic' === ( $m['pollTitle'] ?? '' ) && 'Mining' === ( $m['voteOption'] ?? '' ) && home_url( '/vote/' ) === ( $m['pageUrl'] ?? '' ) );
	$li = $m['lineItems'][0] ?? array();
	t_ok( "$provider: lineItems", 1 === count( $m['lineItems'] ?? array() ) && 4000 === ( $li['total'] ?? 0 ) && 'SATS' === ( $li['currency'] ?? '' ) && 1 === ( $li['quantity'] ?? 0 ) );
	t_ok( "$provider: voter fields", 'Jane Doe' === ( $m['customerName'] ?? '' ) && 'Jane Doe' === ( $m['buyerName'] ?? '' ) && 'jane@example.test' === ( $m['buyerEmail'] ?? '' )
		&& 'Main St 1' === ( $m['buyerAddress1'] ?? '' ) && '1010' === ( $m['buyerZip'] ?? '' ) && 'Vienna' === ( $m['buyerCity'] ?? '' )
		&& 'Austria' === ( $m['buyerCountry'] ?? '' ) && array( 'Company' => 'ACME' ) === ( $m['formFields'] ?? null ) );
	t_ok( "$provider: top-level buyerEmail", 'jane@example.test' === ( $sent['buyerEmail'] ?? '' ) );
	t_ok( "$provider: platform fields", 'wordpress' === ( $m['platform'] ?? '' ) && COINSNAP_BITCOIN_VOTING_VERSION === ( $m['pluginVersion'] ?? '' ) && '' !== ( $m['language'] ?? '' ) );
	t_ok( "$provider: no empty values, no secrets", array() === array_filter( $m, function ( $v ) { return '' === $v || null === $v; } ) && false === strpos( wp_json_encode( $sent ), 'test-key' ) );
	t_ok( "$provider: amount 4000 SATS", 4000 === ( $sent['amount'] ?? 0 ) && 'SATS' === ( $sent['currency'] ?? '' ) );

	// Settled webhook, signed with this plugin's secret: matched by invoice ID and metadata.pollId.
	$GLOBALS['t_invoices'][ $inv ]['status'] = 'Settled';
	$body = wp_json_encode( array( 'invoiceId' => $inv, 'type' => 'btcpay' === $provider ? 'InvoiceSettled' : 'Settled' ) );
	$wh   = new WP_REST_Request( 'POST', '/coinsnap-bitcoin-voting/v1/webhook' );
	$wh->set_body( $body );
	if ( 'btcpay' === $provider ) {
		$wh->set_header( 'BTCPay-Sig', 'sha256=' . hash_hmac( 'sha256', $body, 'test-secret-btcpay' ) );
	} else {
		$ts = time();
		$wh->set_header( 'X-Coinsnap-Signature', 't=' . $ts . ',v1=' . hash_hmac( 'sha256', $ts . '.' . $body, 'test-secret-coinsnap' ) );
	}
	$whr    = rest_do_request( $wh );
	$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE payment_id = %s", $inv ) );
	$posts  = get_posts( array( 'post_type' => 'coinsnap-voting-pds', 'post_status' => 'any', 'meta_key' => '_coinsnap_bitcoin_voting_payment_id', 'meta_value' => $inv, 'fields' => 'ids' ) );
	t_ok( "$provider: signed Settled webhook counts the vote", 200 === $whr->get_status() && 'completed' === $status && 1 === count( $posts ) );
	$again = rest_do_request( $wh );
	$posts = get_posts( array( 'post_type' => 'coinsnap-voting-pds', 'post_status' => 'any', 'meta_key' => '_coinsnap_bitcoin_voting_payment_id', 'meta_value' => $inv, 'fields' => 'ids' ) );
	t_ok( "$provider: repeated webhook counts nothing twice", 200 === $again->get_status() && 1 === count( $posts ) );
}

$results = rest_do_request( new WP_REST_Request( 'GET', '/voting/v1/voting_results/' . $poll_id ) )->get_data();
t_ok( 'two paid votes for option 2', 2 === ( $results['total'] ?? 0 ) );

echo $GLOBALS['t_fail'] ? $GLOBALS['t_fail'] . " failed\n" : "all passed\n";
if ( $GLOBALS['t_fail'] ) {
	exit( 1 );
}
