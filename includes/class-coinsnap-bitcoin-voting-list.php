<?php

class Bitcoin_Donation_List
{

	public function __construct()
	{
		add_action('wp_ajax_refresh_donations', array($this, 'refresh_donations_ajax'));
	}
	private function fetch_donations()
	{
		$options = get_option('coinsnap_bitcoin_voting_options');
		$provider = $options['provider'];

		if ($provider == 'coinsnap') {
			$api_key = $options['coinsnap_api_key'];
			$store_id = $options['coinsnap_store_id'];
			$url = 'https://app.coinsnap.io/api/v1/stores/' . $store_id . '/invoices';
			$headers = array(
				'headers' => array('x-api-key' => $api_key, 'Content-Type' => 'application/json')
			);
		} else {
			$api_key = $options['btcpay_api_key'];
			$store_id = $options['btcpay_store_id'];
			$base_url = $options['btcpay_url'];
			$url = $base_url . '/api/v1/stores/' . $store_id . '/invoices';
			$headers = array(
				'headers' => array('Authorization' => 'token ' . $api_key, 'Content-Type' => 'application/json')
			);
		}

		$response = wp_remote_get($url, $headers);
		$body = wp_remote_retrieve_body($response);
		$invoices = json_decode($body, true);
		if (!is_array($invoices)) {
			throw new Exception('Invalid API response');
		}
		$filtered_invoices = array_filter($invoices, function ($invoice) {
			return isset($invoice['metadata']['referralCode'])
				&& $invoice['metadata']['referralCode'] === "D19833"
				&& $invoice['status'] === 'Settled'
				&& $invoice['metadata']['type'] == 'Bitcoin Voting';
		});
		if ($provider == 'coinsnap') {
			usort($filtered_invoices, function ($a, $b) {
				return $b['createdAt'] <=> $a['createdAt'];
			});
		} else {
			usort($filtered_invoices, function ($a, $b) {
				return $b['createdTime'] <=> $a['createdTime'];
			});
		}
		return array_values($filtered_invoices);
	}

	public function render_donations_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$options          = get_option('coinsnap_bitcoin_voting_options');
		$provider         = $options['provider'];
		$btcpay_store_id  = $options['btcpay_store_id'];
		$btcpay_url       = $options['btcpay_url'];
		$btcpay_href      = $btcpay_url . '/stores/' . $btcpay_store_id . '/invoices';
		$donations        = $this->fetch_donations();

		$donations_per_page = 20;
		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$total_donations = count($donations);
		$total_pages   = ceil($total_donations / $donations_per_page);
		$offset = ($current_page - 1) * $donations_per_page;
		$donations_page = array_slice($donations, $offset, $donations_per_page);

?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<?php if ($provider === 'coinsnap'): ?>
				<h4>Check <a href="https://app.coinsnap.io/transactions" target="_blank" rel="noopener noreferrer">Coinsnap app</a> for a detailed overview</h4>
			<?php elseif ($provider === 'btcpay'): ?>
				<h4>Check <a href="<?php echo esc_html($btcpay_href); ?>" target="_blank" rel="noopener noreferrer">BtcPay server</a> for a detailed overview</h4>
			<?php else: ?>
				<p>Provider not recognized.</p>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped voting-list-table">
				<thead>
					<tr>
						<th>Date</th>
						<th>Amount</th>
						<th>Name</th>
						<th>Message</th>
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
					'prev_text' => __('&laquo; Previous'),
					'next_text' => __('Next &raquo;'),
				]);

				if ($pagination_links) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . $pagination_links . '</div></div>';
				}
			}
			?>
		</div>
	<?php
	}

	private function render_donation_row($donation)
	{
		$invoice_id = $donation['id'];
		$options = get_option('coinsnap_bitcoin_voting_options');
		$provider = $options['provider'];
		$isBtcpay = $provider === 'btcpay';
		$href = ($isBtcpay)
			? "https://btcpay.coincharge.io/invoices/" . esc_html($invoice_id)
			: "https://app.coinsnap.io/td/" . esc_html($invoice_id);
		$message = isset($donation['metadata']['orderNumber']) ? $donation['metadata']['orderNumber'] : '';
		$message = strlen($message) > 150 ? substr($message, 0, 150) . ' ...' : $message;
		$name = isset($donation['metadata']['voteTitle']) ? $donation['metadata']['voteTitle'] : '';
	?>
		<tr>
			<td>
				<?php echo esc_html(date('Y-m-d H:i:s', (int)$donation[$isBtcpay ? 'createdTime' :  'createdAt'])); ?>
			</td>

			<td>
				<?php
				$amount =  $donation['amount'];
				$currency = $donation['currency'];
				echo esc_html(number_format($amount, $isBtcpay ? 2 : 0) . ' ' . ($isBtcpay ? $currency : 'sats'));
				?>
			</td>
			<td><?php echo esc_html($name); ?></td>
			<td><?php echo esc_html($message); ?></td>
			<td>
				<a href="<?php echo $href; ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html($invoice_id); ?>
				</a>

			</td>
		</tr>
<?php
	}
}
