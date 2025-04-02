<?php
if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'class-coinsnap-bitcoin-voting-list.php';

class Bitcoin_Voting_Settings
{
    private $donation_list;

    public function __construct()
    {
        $this->donation_list = new Bitcoin_Donation_List();

        // Register menus
        add_action('admin_menu', [$this, 'coinsnap_bitcoin_voting_add_admin_menu']);
        add_action('admin_init', [$this, 'coinsnap_bitcoin_voting_settings_init']);
    }

    function coinsnap_bitcoin_voting_add_admin_menu()
    {
        add_menu_page(
            'Bitcoin Voting',
            'Bitcoin Voting',
            'manage_options',
            'coinsnap_bitcoin_voting',
            [$this, 'coinsnap_bitcoin_voting_options_page'],
            plugin_dir_url(dirname(__FILE__)) . 'assets/bitcoin.svg',

            100
        );
        add_submenu_page(
            'coinsnap_bitcoin_voting',
            'Settings',
            'Settings',
            'manage_options',
            'coinsnap_bitcoin_voting',
            [$this, 'coinsnap_bitcoin_voting_options_page']
        );

        add_submenu_page(
            'coinsnap_bitcoin_voting',
            'Donations',
            'Donations',
            'manage_options',
            'coinsnap-bitcoin-donation-list',
            [$this->donation_list, 'render_donations_page']
        );

        add_submenu_page(
            'coinsnap_bitcoin_voting',
            'Polls',
            'Polls',
            'manage_options',
            'edit.php?post_type=bitcoin-polls'
        );

        add_submenu_page(
            'coinsnap_bitcoin_voting',
            'Donor Information',
            'Donor Information',
            'manage_options',
            'edit.php?post_type=bitcoin-pds'
        );
    }

    function coinsnap_bitcoin_voting_settings_init()
    {
        register_setting('coinsnap_bitcoin_voting_settings', 'coinsnap_bitcoin_voting_options', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);

        // Provider Section
        add_settings_section(
            'coinsnap_bitcoin_voting_provider_section',
            'General Settings',
            [$this, 'provider_section_callback'],
            'coinsnap_bitcoin_voting'
        );

        add_settings_field(
            'provider',
            'Payment Gateway',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_provider_section',
            [
                'label_for' => 'provider',
                'type'      => 'select',
                'options'   => [
                    'coinsnap' => 'Coinsnap',
                    'btcpay'   => 'BTCPay'
                ]
            ]
        );

        add_settings_field(
            'theme',
            'Theme',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_provider_section',
            [
                'label_for' => 'theme',
                'type'      => 'select',
                'options'   => [
                    'light' => 'Light',
                    'dark'   => 'Dark'
                ]
            ]
        );

        // Add ngrok field if site is running on localhost
        if (strpos(get_site_url(), 'localhost') !== false) {
            add_settings_field(
                'ngrok_url',
                'Ngrok URL',
                [$this, 'render_field'],
                'coinsnap_bitcoin_voting',
                'coinsnap_bitcoin_voting_provider_section',
                [
                    'label_for' => 'ngrok_url',
                    'type'      => 'text',
                    'description' => 'Enter your ngrok URL for webhook testing (e.g., https://your-tunnel.ngrok.io)'
                ]
            );
        }

        // Coinsnap Section
        add_settings_section(
            'coinsnap_bitcoin_voting_coinsnap_section',
            'Coinsnap Settings',
            [$this, 'coinsnap_section_callback'],
            'coinsnap_bitcoin_voting'
        );

        add_settings_field(
            'coinsnap_store_id',
            'Coinsnap Store ID',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_coinsnap_section',
            [
                'label_for' => 'coinsnap_store_id',
                'type'      => 'text'
            ]
        );

        add_settings_field(
            'coinsnap_api_key',
            'Coinsnap API Key',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_coinsnap_section',
            [
                'label_for' => 'coinsnap_api_key',
                'type'      => 'text'
            ]
        );

        add_settings_field(
            'check_connection_coinsnap',
            'Check Connection',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_coinsnap_section',
            [
                'label_for' => 'check_connection_coinsnap',
                'type'      => 'check_connection',
                'id'        => 'check_connection_coinsnap'

            ]
        );

        // BTCPay Section
        add_settings_section(
            'coinsnap_bitcoin_voting_btcpay_section',
            'BTCPay Settings',
            [$this, 'btcpay_section_callback'],
            'coinsnap_bitcoin_voting'
        );

        add_settings_field(
            'btcpay_store_id',
            'BTCPay Store ID',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_btcpay_section',
            [
                'label_for' => 'btcpay_store_id',
                'type'      => 'text'
            ]
        );

        add_settings_field(
            'btcpay_api_key',
            'BTCPay API Key',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_btcpay_section',
            [
                'label_for' => 'btcpay_api_key',
                'type'      => 'text'
            ]
        );

        add_settings_field(
            'btcpay_url',
            'BTCPay URL',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_btcpay_section',
            [
                'label_for' => 'btcpay_url',
                'type'      => 'text'
            ]
        );
        add_settings_field(
            'check_connection_btcpay',
            'Check Connection',
            [$this, 'render_field'],
            'coinsnap_bitcoin_voting',
            'coinsnap_bitcoin_voting_btcpay_section',
            [
                'label_for' => 'check_connection_btcpay',
                'type'      => 'check_connection',
                'id'        => 'check_connection_btcpay'
            ]
        );
    }

    public function sanitize_options($options)
    {
        $sanitized = [];

        if (isset($options['provider'])) {
            $sanitized['provider'] = sanitize_text_field($options['provider']);
        }

        if (isset($options['theme'])) {
            $sanitized['theme'] = sanitize_text_field($options['theme']);
        }

        if (isset($options['coinsnap_store_id'])) {
            $sanitized['coinsnap_store_id'] = sanitize_text_field($options['coinsnap_store_id']);
        }

        if (isset($options['coinsnap_api_key'])) {
            $sanitized['coinsnap_api_key'] = sanitize_text_field($options['coinsnap_api_key']);
        }

        if (isset($options['btcpay_store_id'])) {
            $sanitized['btcpay_store_id'] = sanitize_text_field($options['btcpay_store_id']);
        }

        if (isset($options['btcpay_api_key'])) {
            $sanitized['btcpay_api_key'] = sanitize_text_field($options['btcpay_api_key']);
        }

        if (isset($options['btcpay_url'])) {
            $sanitized['btcpay_url'] = esc_url_raw($options['btcpay_url']);
        }

        if (isset($options['ngrok_url'])) {
            $sanitized['ngrok_url'] = esc_url_raw($options['ngrok_url']);
        }

        // Check if provider is working
        if (isset($sanitized['provider']) && $sanitized['provider'] === 'coinsnap') {
            $this->check_coinsnap_connection($sanitized['coinsnap_store_id'], $sanitized['coinsnap_api_key']);
        } else if (isset($sanitized['provider']) && $sanitized['provider'] === 'btcpay') {
            $this->check_btcpay_connection($sanitized['btcpay_store_id'], $sanitized['btcpay_api_key'], $sanitized['btcpay_url']);
        }

        return $sanitized;
    }

    public function check_coinsnap_connection($coinsnap_store_id, $coinsnap_api_key)
    {
        $response = wp_remote_get("https://app.coinsnap.io/api/v1/stores/{$coinsnap_store_id}", [
            'headers' => [
                'X-api-key' => $coinsnap_api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            add_settings_error(
                'coinsnap_bitcoin_voting_settings',
                'coinsnap_connection_error',
                'Error connecting to Coinsnap. Please check your API key and store ID.',
                'error'
            );
        } else {

            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code !== 200) {
                add_settings_error(
                    'coinsnap_bitcoin_voting_settings',
                    'coinsnap_response_error',
                    'Coinsnap responded with an error. Please verify your credentials.',
                    'error'
                );
            }
        }
    }

    public function check_btcpay_connection($btcpay_store_id, $btcpay_api_key, $btcpay_url)
    {

        $response = wp_remote_get("{$btcpay_url}/api/v1/stores/{$btcpay_store_id}/invoices", [
            'headers' => [
                'Authorization' => 'token ' . $btcpay_api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            add_settings_error(
                'coinsnap_bitcoin_voting_settings',
                'btcpay_connection_error',
                'Error connecting to Btcpay. Please check your API key and store ID.',
                'error'
            );
        } else {

            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code !== 200) {
                add_settings_error(
                    'coinsnap_bitcoin_voting_settings',
                    'btcpay_response_error',
                    'Btcpay responded with an error. Please verify your credentials.',
                    'error'
                );
            }
        }
    }

    // Optional section callbacks for additional descriptions
    public function provider_section_callback()
    {
        echo esc_html_e('Select your preferred payment provider and configure its settings below.', 'coinsnap_bitcoin_voting');
    }

    public function coinsnap_section_callback()
    {
        echo esc_html_e('Enter your Coinsnap credentials here if you selected Coinsnap as your payment provider.', 'coinsnap_bitcoin_voting');
    }

    public function btcpay_section_callback()
    {
        echo esc_html_e('Enter your BTCPay credentials here if you selected BTCPay as your payment provider.', 'coinsnap_bitcoin_voting');
    }

    function coinsnap_bitcoin_voting_section_general_callback()
    {
        echo __('Configure the plugin settings below.', 'sdb');
    }

    /**
     * Renders a specific settings section manually.
     *
     * @param string $section_id The ID of the section to render.
     */
    private function render_section($section_id)
    {
        global $wp_settings_sections, $wp_settings_fields;
        if (! isset($wp_settings_sections['coinsnap_bitcoin_voting'][$section_id])) {
            return;
        }

        $section = $wp_settings_sections['coinsnap_bitcoin_voting'][$section_id];

        if ($section['title']) {
            echo '<h3>' . esc_html($section['title']) . '</h3>';
        }
        if ($section['callback']) {
            call_user_func($section['callback'], $section);
        }

        if (! empty($wp_settings_fields['coinsnap_bitcoin_voting'][$section_id])) {
            echo '<table class="form-table">';
            do_settings_fields('coinsnap_bitcoin_voting', $section_id);
            echo '</table>';
        }
    }

    public function render_field($args)
    {
        $options     = get_option('coinsnap_bitcoin_voting_options', []);
        $field_id    = $args['label_for'];
        $field_type  = $args['type'];
        $field_value = isset($options[$field_id]) ? $options[$field_id] : '';
        $defaults = [
            'default_message' => 'Thank you for your work',
            'default_amount'  => '5',
            'button_text'     => 'Donate',
            'title_text'      => 'Donate with Bitcoin',
            'shoutout_default_message' => 'Thank you!',
            'shoutout_default_amount'  => '5',
            'shoutout_button_text'     => 'Shoutout',
            'shoutout_title_text'      => 'Bitcoin Shoutouts',
            'shoutout_minimum_amount'  => '21',
            'shoutout_premium_amount'  => '21000'
        ];
        if ($field_type == 'text') {
            $field_value = isset($options[$field_id]) ? $options[$field_id] : ($defaults[$field_id] ?? '');
        }
        switch ($field_type) {
            case 'select':
                echo '<select 
                id="' . esc_attr($field_id) . '" 
                name="coinsnap_bitcoin_voting_options[' . esc_attr($field_id) . ']"
                class="regular-text">';
                foreach ($args['options'] as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '"' .
                        selected($field_value, $value, false) . '>' .
                        esc_html($label) . '</option>';
                }
                echo '</select>';
                break;

            case 'check_connection':
                $id = isset($args['id']) ? $args['id'] : 'check_connection';

                echo '<div >' . '<button id="' . esc_attr($id) . '_button">Check</button>' . '<span style="" id="' . esc_attr($id) .  '">' . '</span>' . '</div>';
                break;

                break;

            case 'text':
                echo '<input type="text" 
                id="' . esc_attr($field_id) . '" 
                name="coinsnap_bitcoin_voting_options[' . esc_attr($field_id) . ']" 
                value="' . esc_attr($field_value) . '" 
                class="regular-text"' .
                    (isset($args['readonly']) && $args['readonly'] ? ' readonly' : '') .
                    (isset($args['value']) ? ' value="' . esc_attr($args['value']) . '"' : '') .
                    '>';
                break;
        }

        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function coinsnap_bitcoin_voting_options_page()
    {
?>
        <div class="wrap">
            <h1>Bitcoin Voting Settings</h1>
            <?php settings_errors('coinsnap_bitcoin_voting_settings'); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('coinsnap_bitcoin_voting_settings');
                // Render the General Settings Section
                echo '<div id="general" class="tab-content active">';
                $this->render_section('coinsnap_bitcoin_voting_provider_section');
                // Render Coinsnap Settings inside a wrapper
                echo '<div id="coinsnap-settings-wrapper" class="provider-settings tab-content">';
                $this->render_section('coinsnap_bitcoin_voting_coinsnap_section');
                echo '</div>';

                // Render BTCPay Settings inside a wrapper
                echo '<div id="btcpay-settings-wrapper" class="provider-settings tab-content">';
                $this->render_section('coinsnap_bitcoin_voting_btcpay_section');
                echo '</div>';
                echo '</div>';
                ?>
                <?php
                // Render submit button
                submit_button();
                ?>
            </form>
        </div>
<?php
    }
}
new Bitcoin_Voting_Settings();
