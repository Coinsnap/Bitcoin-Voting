<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Payment confirmation page shortcode and auto-page creation.
 * Shortcode: [coinsnap_bitcoin_voting_confirmation]
 */
class Coinsnap_Bitcoin_Voting_Confirmation {

    public function __construct() {
        add_shortcode( 'coinsnap_bitcoin_voting_confirmation', [ $this, 'render' ] );
        add_action( 'init', [ $this, 'maybe_create_confirmation_page' ] );
    }

    /**
     * Create the confirmation WP page once if it doesn't exist yet.
     */
    public function maybe_create_confirmation_page() {
        $page_id = (int) get_option( 'cbv_confirmation_page_id' );
        if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
            return;
        }

        $page_id = wp_insert_post( [
            'post_title'   => __( 'Payment Confirmation', 'bitcoin-voting' ),
            'post_content' => '[coinsnap_bitcoin_voting_confirmation]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'cbv_confirmation_page_id', $page_id );
        }
    }

    /**
     * Return the URL of the confirmation page (empty string if not found).
     */
    public static function get_url(): string {
        $page_id = (int) get_option( 'cbv_confirmation_page_id' );
        if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
            return (string) get_permalink( $page_id );
        }
        return '';
    }

    /**
     * Shortcode renderer — reads GET params and shows confirmation card.
     */
    public function render(): string {
        $invoice_id = sanitize_text_field( filter_input( INPUT_GET, 'invoice_id',  FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '' );
        // Coinsnap appends ?invoiceId=xxx automatically on redirect
        if ( ! $invoice_id ) {
            $invoice_id = sanitize_text_field( filter_input( INPUT_GET, 'invoiceId', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '' );
        }
        $amount     = sanitize_text_field( filter_input( INPUT_GET, 'amount',      FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '' );
        $currency   = sanitize_text_field( filter_input( INPUT_GET, 'currency',    FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '' );
        $option     = sanitize_text_field( filter_input( INPUT_GET, 'option',      FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '' );
        $poll_title = sanitize_text_field( filter_input( INPUT_GET, 'poll_title',  FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '' );
        $poll_id    = intval( filter_input( INPUT_GET, 'poll_id', FILTER_SANITIZE_NUMBER_INT ) ?? 0 );

        if ( ! $invoice_id && ! $poll_id && ! $option ) {
            return '<p>' . esc_html__( 'No payment information found.', 'bitcoin-voting' ) . '</p>';
        }

        // Build invoice URL from saved settings
        $options        = get_option( 'coinsnap_bitcoin_voting_options', [] );
        $saved_provider = $options['payment_provider'] ?? 'coinsnap';
        if ( $saved_provider === 'btcpay' ) {
            $btcpay_host = rtrim( $options['btcpay_host'] ?? '', '/' );
            $invoice_url = $btcpay_host . '/invoices/' . $invoice_id;
        } else {
            $invoice_url = 'https://app.coinsnap.io/transactions/' . $invoice_id;
        }

        // Find the page that embeds this poll for a "Back" link
        $back_url = '';
        if ( $poll_id ) {
            global $wpdb;
            $like   = '%[coinsnap_bitcoin_voting id="' . intval( $poll_id ) . '"%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $page_id_found = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT 1",
                $like
            ) );
            if ( $page_id_found ) {
                $back_url = get_permalink( $page_id_found );
            }
        }

        ob_start();
        ?>
        <div class="cbv-confirmation-wrap">
            <div class="cbv-confirmation-card">
                <div class="cbv-confirmation-icon">&#10004;</div>
                <h2><?php esc_html_e( 'Payment Successful!', 'bitcoin-voting' ); ?></h2>
                <p class="cbv-confirmation-sub"><?php esc_html_e( 'Your vote has been registered.', 'bitcoin-voting' ); ?></p>

                <table class="cbv-confirmation-table">
                    <?php if ( $poll_title ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Poll', 'bitcoin-voting' ); ?></th>
                        <td><?php echo esc_html( $poll_title ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $option ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Voted for', 'bitcoin-voting' ); ?></th>
                        <td><?php echo esc_html( $option ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $amount ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Amount', 'bitcoin-voting' ); ?></th>
                        <td><?php echo esc_html( $amount . ( $currency ? ' ' . strtoupper( $currency ) : '' ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php if ( $back_url ) : ?>
                <a href="<?php echo esc_url( $back_url ); ?>" class="cbv-confirmation-back">
                    &larr; <?php esc_html_e( 'Back to poll', 'bitcoin-voting' ); ?>
                </a>
                <?php else : ?>
                <a href="<?php echo esc_url( home_url() ); ?>" class="cbv-confirmation-back">
                    &larr; <?php esc_html_e( 'Back to home', 'bitcoin-voting' ); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <style>
        .cbv-confirmation-wrap {
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .cbv-confirmation-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .cbv-confirmation-icon {
            font-size: 60px;
            color: #22c55e;
            margin-bottom: 16px;
            line-height: 1;
        }
        .cbv-confirmation-card h2 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #111827;
        }
        .cbv-confirmation-sub {
            color: #6b7280;
            margin: 0 0 28px;
            font-size: 16px;
        }
        .cbv-confirmation-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-bottom: 28px;
        }
        .cbv-confirmation-table th,
        .cbv-confirmation-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 15px;
        }
        .cbv-confirmation-table th {
            color: #6b7280;
            font-weight: 500;
            width: 40%;
        }
        .cbv-confirmation-table td {
            color: #111827;
            font-weight: 600;
            word-break: break-all;
        }
        .cbv-confirmation-table a {
            color: #f59e0b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 400;
        }
        .cbv-confirmation-table a:hover { text-decoration: underline; }
        .cbv-confirmation-back {
            display: inline-block;
            padding: 11px 28px;
            background: #1e3a6e;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
        }
        .cbv-confirmation-back:hover {
            background: #162d57;
            color: #fff;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
new Coinsnap_Bitcoin_Voting_Confirmation();
