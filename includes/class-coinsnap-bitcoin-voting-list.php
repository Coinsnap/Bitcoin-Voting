<?php
if (!defined('ABSPATH')){ exit; }

class Coinsnap_Bitcoin_Voting_Donation_List {

	public function __construct(){
		add_action('wp_ajax_refresh_donations', array($this, 'refresh_donations_ajax'));
	}
	
        private function fetch_donations(){
		global $wpdb;
		
		// Check cache first
		$cache_key = 'coinsnap_voting_donations';
		$voting_payments = wp_cache_get($cache_key);
		
		if (false === $voting_payments) {
			// Read voting transactions from shared PaymentTable
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$voting_payments = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}coinsnapbbf_payments 
				 WHERE description LIKE '%Vote:%' 
				 ORDER BY created_at DESC",
				ARRAY_A
			);
			
			// Cache for 5 minutes
			wp_cache_set($cache_key, $voting_payments, '', 300);
		}
		
		if ( empty( $voting_payments ) ) {
			return array();
		}
		
		// Transform voting payments to match expected format
		$donations = array();
		foreach ( $voting_payments as $payment ) {
			$donations[] = array(
				'id'       => $payment['payment_invoice_id'] ?: $payment['invoice_number'],
				'amount'   => (float) $payment['amount'],
				'currency' => $payment['currency'] ?: 'EUR',
				'status'   => 'Settled',
				'createdAt' => strtotime( $payment['created_at'] ),
				'createdTime' => strtotime( $payment['created_at'] ),
				'metadata' => array(
					'option'     => $payment['description'] ?: '',
					'donorName'  => $payment['customer_name'] ?: '',
					'name'       => $payment['customer_name'] ?: '',
					'referralCode' => COINSNAP_BITCOIN_VOTING_REFERRAL_CODE
				)
			);
		}
		
		return $donations;
	}

	public function render_donations_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$options          = get_option('coinsnap_bitcoin_voting_options', array());
		$provider         = isset( $options['payment_provider'] ) ? $options['payment_provider'] : '';
		$btcpay_store_id  = isset( $options['btcpay_store_id'] ) ? $options['btcpay_store_id'] : '';
		$btcpay_url       = isset( $options['btcpay_host'] ) ? $options['btcpay_host'] : '';
		$btcpay_href      = $btcpay_url . '/stores/' . $btcpay_store_id . '/invoices';
		try {
			$donations = $this->fetch_donations();
		} catch ( Exception $e ) {
			$donations = array();
			add_settings_error( 'coinsnap_bitcoin_voting', 'api_error', esc_html( $e->getMessage() ), 'error' );
		}

		$donations_per_page = 20;
		$paged = filter_input(INPUT_GET,'paged',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$current_page = ($paged !== null) ? max(1, intval($paged)) : 1;
                $total_donations = count($donations);
                
                
                
		$total_pages   = ceil($total_donations / $donations_per_page);
		$offset = ($current_page - 1) * $donations_per_page;
		$donations_page = array_slice($donations, $offset, $donations_per_page);

?>
		<div class="wrap">
			<h1>Transactions</h1>
			<?php if ($provider === 'coinsnap'): ?>
				<h4>Check <a href="https://app.coinsnap.io/transactions" target="_blank" rel="noopener noreferrer">Coinsnap app</a> for a detailed overview</h4>
			<?php elseif ($provider === 'btcpay'): ?>
				<h4>Check <a href="<?php echo esc_url($btcpay_href); ?>" target="_blank" rel="noopener noreferrer">BtcPay server</a> for a detailed overview</h4>
			<?php else: ?>
				<p>Provider not recognized.</p>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped voting-list-table">
				<thead>
					<tr>
						<th>Date</th>
						<th>Amount</th>
						<th>Poll Option</th>
						<th>Voter Name</th>
						<th>Invoice ID</th>
					</tr>
				</thead>
				<tbody id="voting-list-body">
					<?php
					if (empty($donations_page)) {
						echo '<tr><td colspan="5">No donations found.</td></tr>';
					} else {
						foreach ($donations_page as $donation) {
							$this->render_donation_row($donation);
						}
					}
					?>
				</tbody>
			</table>

			<?php
			if ($total_pages > 1) {
				$pagination_base = add_query_arg('paged', '%#%');
				$pagination_links = paginate_links([
					'base'      => $pagination_base,
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'prev_text' => esc_html('&laquo; ' . __('Previous','coinsnap-bitcoin-voting')),
					'next_text' => esc_html(__('Next','coinsnap-bitcoin-voting') . ' &raquo;'),
				]);

				if ($pagination_links) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses($pagination_links,['span'=>['aria-current'=>true,'class'=>true],'a'=>['href'  => true,'title' => true],'class'=>true]) . '</div></div>';
				}
			}
			?>
		</div>
	<?php
	}

	private function render_donation_row($donation)
	{
		$invoice_id = $donation['id'];
		$options = get_option('coinsnap_bitcoin_voting_options', array());
		$provider = isset( $options['payment_provider'] ) ? $options['payment_provider'] : '';
		$isBtcpay = $provider === 'btcpay';
		$btcpay_host = isset( $options['btcpay_host'] ) ? rtrim( $options['btcpay_host'], '/' ) : '';
		$href = ($isBtcpay)
			? $btcpay_host . '/invoices/' . esc_html($invoice_id)
			: 'https://app.coinsnap.io/transactions/' . esc_html($invoice_id);
		$poll_option = isset($donation['metadata']['option']) ? $donation['metadata']['option'] : ( isset($donation['metadata']['orderNumber']) ? $donation['metadata']['orderNumber'] : '' );
		$voter_name  = isset($donation['metadata']['donorName']) ? $donation['metadata']['donorName'] : ( isset($donation['metadata']['name']) ? $donation['metadata']['name'] : '' );
	?>
		<tr>
			<td>
				<?php echo esc_html(gmdate('Y-m-d H:i:s', (int)$donation[$isBtcpay ? 'createdTime' :  'createdAt'])); ?>
			</td>
			<td>
				<?php
				$amount =  $donation['amount'];
				$currency = $donation['currency'];
				echo esc_html(number_format($amount, $isBtcpay ? 2 : 0) . ' ' . ($isBtcpay ? $currency : 'sats'));
				?>
			</td>
			<td><?php echo esc_html($poll_option); ?></td>
			<td><?php echo esc_html($voter_name); ?></td>
			<td>
				<a href="<?php echo esc_url($href); ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html($invoice_id); ?>
				</a>
			</td>
		</tr>
<?php
	}
}
