<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once plugin_dir_path( __FILE__ ) . 'class-coinsnap-bitcoin-voting-list.php';

/**
 * Admin menu registration and settings page rendering for Coinsnap Bitcoin Voting.
 * Delegates settings UI to CoinsnapCore\Admin\SettingsPage for a consistent card-based look.
 */
class Coinsnap_Bitcoin_Voting_Settings {

    /** @var Coinsnap_Bitcoin_Voting_Donation_List */
    private $donation_list;

    public function __construct() {
        $this->donation_list = new Coinsnap_Bitcoin_Voting_Donation_List();

        add_action( 'admin_menu', [ $this, 'coinsnap_bitcoin_voting_add_admin_menu' ] );
        add_action( 'admin_init', function () {
            \CoinsnapCore\Admin\SettingsPage::register_for( coinsnap_bitcoin_voting_plugin_instance() );
        } );
    }

    public function coinsnap_bitcoin_voting_add_admin_menu(): void {
        add_menu_page(
            'Coinsnap Bitcoin Voting',
            'Coinsnap Bitcoin Voting',
            'manage_options',
            'Bitcoin-Voting',
            [ $this, 'render_settings_page' ],
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/images/bitcoin.svg',
            100
        );

        add_submenu_page(
            'Bitcoin-Voting',
            'Settings',
            'Settings',
            'manage_options',
            'Bitcoin-Voting',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'Bitcoin-Voting',
            'Transactions',
            'Transactions',
            'manage_options',
            'coinsnap-bitcoin-voting-donation-list',
            [ $this->donation_list, 'render_donations_page' ]
        );

        add_submenu_page(
            'Bitcoin-Voting',
            'Polls',
            'Polls',
            'manage_options',
            'edit.php?post_type=coinsnap-polls'
        );

        add_submenu_page(
            'Bitcoin-Voting',
            'Donor Information',
            'Donor Information',
            'manage_options',
            'edit.php?post_type=coinsnap-pds'
        );

        // Alias slug used by vendor BTCPayAuthorizer callback redirect.
        add_submenu_page(
            'Bitcoin-Voting',
            'Settings',
            '',
            'manage_options',
            'coinsnap-bitcoin-voting-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'Bitcoin-Voting' ) );
        }
        \CoinsnapCore\Admin\SettingsPage::render_page_for( coinsnap_bitcoin_voting_plugin_instance() );
    }

    /**
     * Return plugin settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings(): array {
        return \CoinsnapCore\Admin\SettingsPage::get_settings_for( coinsnap_bitcoin_voting_plugin_instance() );
    }
}
new Coinsnap_Bitcoin_Voting_Settings();
