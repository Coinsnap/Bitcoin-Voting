<?php
if (!defined('ABSPATH')){ exit; }

class Coinsnap_Bitcoin_Voting_Polls_Metabox {
    public function __construct(){
        add_action('init', [$this, 'register_polls_post_type']);
        add_action('init', [$this, 'register_custom_meta_fields']);
        add_action('add_meta_boxes', [$this, 'add_polls_metaboxes']);
        add_action('save_post', [$this, 'save_polls_meta'], 10, 2);
        add_filter('manage_coinsnap-polls_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_coinsnap-polls_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
    }

    public function register_polls_post_type(){
        register_post_type('coinsnap-polls', [
            'labels' => [
                'name'               => __('Polls', 'coinsnap-bitcoin-voting'),
                'singular_name'      => __('Poll', 'coinsnap-bitcoin-voting'),
                'menu_name'          => __('Polls', 'coinsnap-bitcoin-voting'),
                'add_new'            => __('Add New', 'coinsnap-bitcoin-voting'),
                'add_new_item'       => __('Add New Poll', 'coinsnap-bitcoin-voting'),
                'edit_item'          => __('Edit Poll', 'coinsnap-bitcoin-voting'),
                'new_item'           => __('New Poll', 'coinsnap-bitcoin-voting'),
                'view_item'          => __('View Poll', 'coinsnap-bitcoin-voting'),
                'search_items'       => __('Search Polls', 'coinsnap-bitcoin-voting'),
                'not_found'          => __('No polls found', 'coinsnap-bitcoin-voting'),
                'not_found_in_trash' => __('No polls found in Trash', 'coinsnap-bitcoin-voting'),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'coinsnap-polls'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
            'show_in_rest'       => true
        ]);
    }

    public function register_custom_meta_fields()
    {
        register_meta('post', '_coinsnap_bitcoin_voting_polls_description', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_option_1', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_coinsnap_bitcoin_voting_polls_option_2', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_coinsnap_bitcoin_voting_polls_option_3', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_coinsnap_bitcoin_voting_polls_option_4', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_amount', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Payment amount',
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_currency', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'select',
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Currency',
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_starting_date', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_ending_date', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_thank_you_message', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_active', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_one_vote', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_coinsnap_bitcoin_voting_polls_collect_donor_info', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        $donor_fields = [
            'first_name',
            'last_name',
            'email',
            'address',
            'custom_field_visibility',
        ];

        register_meta('post', '_coinsnap_bitcoin_voting_polls_custom_field_name', [
            'object_subtype' => 'coinsnap-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        foreach ($donor_fields as $field) {
            register_meta('post', '_coinsnap_bitcoin_voting_polls_' . $field, [
                'object_subtype' => 'coinsnap-polls',
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ]);
        }
    }

    public function add_polls_metaboxes()
    {
        add_meta_box(
            'coinsnap_bitcoin_voting_polls_details',
            'Polls Details',
            [$this, 'render_polls_metabox'],
            'coinsnap-polls',
            'normal',
            'high'
        );
    }
    
    public function render_polls_metabox($post){
        wp_nonce_field('coinsnap_bitcoin_voting_polls_nonce', 'coinsnap_bitcoin_voting_polls_nonce');
        $client = new Coinsnap_Bitcoin_Voting_Client();

        $description = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_description', true);
        $option_1 = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_option_1', true);
        $option_2 = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_option_2', true);
        $option_3 = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_option_3', true);
        $option_4 = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_option_4', true);
        $amount = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_amount', true);
        $currency = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_currency', true);
        
        $coinsnapCurrencies = $client->getCurrencies();
        if ( empty( $currency ) ) { $currency = 'EUR'; }

        $starting_date = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_starting_date', true);
        if (empty($starting_date)) {
            $starting_date = gmdate('Y-m-d\TH:i');
        }

        $ending_date = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_ending_date', true);
        if (empty($ending_date)) {
            $ending_date = gmdate('Y-m-d\TH:i', strtotime('+1 month'));
        }

        $thank_you_message = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_thank_you_message', true);
        $active = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_active', true);
        if ($active === '') {
            $active = '1';
        }
        $one_vote = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_one_vote', true);
        $collect_donor_info = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_collect_donor_info', true);
        $custom_field_name = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_custom_field_name', true);
        $donor_fields = [
            'first_name' => __('First Name','coinsnap-bitcoin-voting'),
            'last_name' => __('Last Name','coinsnap-bitcoin-voting'),
            'email' => __('Email','coinsnap-bitcoin-voting'),
            'address' => __('Address','coinsnap-bitcoin-voting'),
            'custom_field' => __('Custom Field','coinsnap-bitcoin-voting'),
        ];

        $field_values = [];
        foreach ($donor_fields as $field => $label) {
            $field_values[$field] = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_' . $field, true);
        }

        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare( "SELECT * FROM {$wpdb->prefix}voting_payments WHERE status = 'completed' AND poll_id = %d", $post->ID));
        $votes = [
            'option_1' => 0,
            'option_2' => 0,
            'option_3' => 0,
            'option_4' => 0,
        ];
        if (count($results) > 0) {
            foreach ($results as $result) {
                switch ($result->option_id) {
                    case 1:
                        $votes['option_1']++;
                        break;
                    case 2:
                        $votes['option_2']++;
                        break;
                    case 3:
                        $votes['option_3']++;
                        break;
                    case 4:
                        $votes['option_4']++;
                        break;
                }
            }
        }
?>
        <div class="coinsnapConnectionStatus"></div>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('Active', 'coinsnap-bitcoin-voting');?></th>
                <td>
                    <label>
                        <input type="checkbox" name="coinsnap_bitcoin_voting_polls_active" value="1" <?php checked($active, '1'); ?>>
                         <?php echo esc_html__('Enable', 'coinsnap-bitcoin-voting');?>
                    </label>
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('One Vote Per User', 'coinsnap-bitcoin-voting');?></th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="coinsnap_bitcoin_voting_polls_one_vote"
                            value="1" <?php checked($one_vote, '1'); ?>>
                         <?php echo esc_html__('Enable', 'coinsnap-bitcoin-voting');?>
                    </label>
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_description"><?php echo esc_html__('Description', 'coinsnap-bitcoin-voting') ?></label>
                </th>
                <td>
                    <textarea
                        id="coinsnap_bitcoin_voting_polls_description"
                        name="coinsnap_bitcoin_voting_polls_description"
                        class="regular-text"
                        rows="2"
                        required
                        style="width: 350px"><?php echo esc_textarea($description); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_option_1"><?php echo esc_html__('Option 1', 'coinsnap-bitcoin-voting') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_1']); ?> <?php echo esc_html__('votes', 'coinsnap-bitcoin-voting');?>
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="coinsnap_bitcoin_voting_polls_option_1"
                        name="coinsnap_bitcoin_voting_polls_option_1"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($option_1); ?>">

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_option_2"><?php echo esc_html_e('Option 2', 'coinsnap-bitcoin-voting') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_2']); ?> <?php echo esc_html__('votes', 'coinsnap-bitcoin-voting');?>
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="coinsnap_bitcoin_voting_polls_option_2"
                        name="coinsnap_bitcoin_voting_polls_option_2"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($option_2); ?>">

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_option_3"><?php echo esc_html_e('Option 3', 'coinsnap-bitcoin-voting') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_3']); ?> <?php echo esc_html__('votes', 'coinsnap-bitcoin-voting');?>
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="coinsnap_bitcoin_voting_polls_option_3"
                        name="coinsnap_bitcoin_voting_polls_option_3"
                        class="regular-text"
                        value="<?php echo esc_attr($option_3); ?>">

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_option_4"><?php echo esc_html_e('Option 4', 'coinsnap-bitcoin-voting') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_4']); ?> <?php echo esc_html__('votes', 'coinsnap-bitcoin-voting');?>
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="coinsnap_bitcoin_voting_polls_option_4"
                        name="coinsnap_bitcoin_voting_polls_option_4"
                        class="regular-text"
                        value="<?php echo esc_attr($option_4); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_amount"><?php echo esc_html_e('Amount', 'coinsnap-bitcoin-voting') ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="coinsnap_bitcoin_voting_polls_amount"
                        name="coinsnap_bitcoin_voting_polls_amount"
                        class="regular-text"
                        required="required"
                        value="<?php echo esc_attr($amount); ?>"
                        min="0"
                        step="1"/>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_currency"><?php echo esc_html_e('Currency', 'coinsnap-bitcoin-voting') ?></label>
                </th>
                <td><select id="coinsnap_bitcoin_voting_polls_currency" name="coinsnap_bitcoin_voting_polls_currency" class="select">
                    <?php
                    for($j=0;$j<count($coinsnapCurrencies);$j++){
                        $selectedCurrencyAdd = ($coinsnapCurrencies[$j] === $currency)? ' selected' : '';
                        echo '<option value="'.esc_html($coinsnapCurrencies[$j]).'" '.esc_html($selectedCurrencyAdd).'>'.esc_html($coinsnapCurrencies[$j]).'</option>';
                    }
                    ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_starting_date"><?php echo esc_html_e('Starting Date', 'coinsnap-bitcoin-voting') ?></label>
                </th>
                <td><input type="datetime-local" id="coinsnap_bitcoin_voting_polls_starting_date" name="coinsnap_bitcoin_voting_polls_starting_date" class="regular-text" required value="<?php echo esc_attr($starting_date); ?>"/>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_ending_date"><?php echo esc_html_e('Ending Date', 'coinsnap-bitcoin-voting') ?></label>
                </th>
                <td>
                    <input
                        type="datetime-local"
                        id="coinsnap_bitcoin_voting_polls_ending_date"
                        name="coinsnap_bitcoin_voting_polls_ending_date"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($ending_date); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="coinsnap_bitcoin_voting_polls_thank_you_message"><?php echo esc_html_e('Thank You Message', 'coinsnap-bitcoin-voting') ?></label>
                </th>
                <td>
                    <textarea
                        id="coinsnap_bitcoin_voting_polls_thank_you_message"
                        name="coinsnap_bitcoin_voting_polls_thank_you_message"
                        class="regular-text"
                        rows="2"
                        required
                        style="width: 350px"><?php echo esc_textarea($thank_you_message); ?></textarea>

                </td>
            </tr>
            <th scope="row">
                <label for="shortcode"><?php echo esc_html_e('Shortcode', 'coinsnap-bitcoin-voting') ?></label>
            </th>
            <td>
                <input
                    type="text"
                    id="shortcode"
                    name="shortcode"
                    class="regular-text"
                    readonly
                    value='[coinsnap_bitcoin_voting id="<?php echo esc_html($post->ID); ?>"]'>
            </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Collect Donor Information', 'coinsnap-bitcoin-voting');?></th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="coinsnap_bitcoin_voting_polls_collect_donor_info"
                            value="1"
                            <?php checked($collect_donor_info, '1'); ?>>
                        <?php echo esc_html__('Enable', 'coinsnap-bitcoin-voting');?>
                    </label>
                    <br>
                </td>
            </tr>
        </table>

        <div id="donor-info-fields" style="margin-top: 20px;">
            <h3><?php echo esc_html__('Donor Information Fields', 'coinsnap-bitcoin-voting');?></h3>
            <table class="form-table">
                <?php
                foreach ($donor_fields as $field => $label) {
                    $visibility_value = get_post_meta($post->ID, '_coinsnap_bitcoin_voting_polls_' . $field . '_visibility', true) ?: 'optional';
                ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <select name="coinsnap_bitcoin_voting_polls_<?php echo esc_attr($field); ?>_visibility">
                                <option value="mandatory" <?php selected($visibility_value, 'mandatory'); ?>><?php echo esc_html__('Mandatory', 'coinsnap-bitcoin-voting');?></option>
                                <option value="optional" <?php selected($visibility_value, 'optional'); ?>><?php echo esc_html__('Optional', 'coinsnap-bitcoin-voting');?></option>
                                <option value="hidden" <?php selected($visibility_value, 'hidden'); ?>><?php echo esc_html__('Hidden', 'coinsnap-bitcoin-voting');?></option>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
                <tr>
                    <th scope="row">
                        <label for="coinsnap_bitcoin_voting_polls_custom_field_name"><?php echo esc_html__('Custom Field Name', 'coinsnap-bitcoin-voting');?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="coinsnap_bitcoin_voting_polls_custom_field_name"
                            name="coinsnap_bitcoin_voting_polls_custom_field_name"
                            class="regular-text"
                            value="<?php echo esc_attr($custom_field_name); ?>"
                            min="0"
                            step="1">
                    </td>
                </tr>

            </table>
        </div>
<?php
    }

    public function save_polls_meta($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $expected_nonce = 'wp_rest';
            $nonce = (null !== filter_input(INPUT_SERVER,'HTTP_X_WP_NONCE',FILTER_SANITIZE_FULL_SPECIAL_CHARS))? sanitize_text_field(filter_input(INPUT_SERVER,'HTTP_X_WP_NONCE',FILTER_SANITIZE_FULL_SPECIAL_CHARS)) : '';
        } else {
            $expected_nonce = 'coinsnap_bitcoin_voting_polls_nonce';
            $nonce = filter_input(INPUT_POST, 'coinsnap_bitcoin_voting_polls_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        
        if (empty($nonce) || !wp_verify_nonce($nonce, $expected_nonce)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_type !== 'coinsnap-polls') {
            return;
        }

        $fields = [
            'coinsnap_bitcoin_voting_polls_description' => 'text',
            'coinsnap_bitcoin_voting_polls_option_1'    => 'text',
            'coinsnap_bitcoin_voting_polls_option_2'    => 'text',
            'coinsnap_bitcoin_voting_polls_option_3'    => 'text',
            'coinsnap_bitcoin_voting_polls_option_4'    => 'text',
            'coinsnap_bitcoin_voting_polls_amount'      => 'number',
            'coinsnap_bitcoin_voting_polls_currency'      => 'select',
            'coinsnap_bitcoin_voting_polls_starting_date' => 'text',
            'coinsnap_bitcoin_voting_polls_ending_date'   => 'text',
            'coinsnap_bitcoin_voting_polls_thank_you_message' => 'text',
            'coinsnap_bitcoin_voting_polls_active'      => 'boolean',
            'coinsnap_bitcoin_voting_polls_one_vote'    => 'boolean',
            'coinsnap_bitcoin_voting_polls_collect_donor_info' => 'boolean',
            'coinsnap_bitcoin_voting_polls_custom_field_name' => 'text',
        ];

        $donor_fields = ['first_name', 'last_name', 'email', 'address', 'custom_field'];
        foreach ($donor_fields as $field) {
            $fields['coinsnap_bitcoin_voting_polls_' . $field . '_visibility'] = 'text';
        }

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            $required_fields = [
                'coinsnap_bitcoin_voting_polls_description',
                'coinsnap_bitcoin_voting_polls_option_1',
                'coinsnap_bitcoin_voting_polls_option_2',
                'coinsnap_bitcoin_voting_polls_amount',
                'coinsnap_bitcoin_voting_polls_starting_date',
                'coinsnap_bitcoin_voting_polls_ending_date'
            ];
            
            // Only require custom field name if donor info collection is enabled
            if ( null !== filter_input(INPUT_POST,'coinsnap_bitcoin_voting_polls_collect_donor_info',FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                $required_fields[] = 'coinsnap_bitcoin_voting_polls_custom_field_name';
            }

            foreach ($required_fields as $field) {
                
                if (empty(filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS))) {
                    wp_die(esc_html("Error: $field is required."));
                }
            }
        } 
        else {
            $json_body = file_get_contents('php://input');
            $data = json_decode($json_body, true);

            if (isset($data['meta']) && is_array($data['meta'])) {
                $required_meta_fields = [
                    '_coinsnap_bitcoin_voting_polls_description',
                    '_coinsnap_bitcoin_voting_polls_option_1',
                    '_coinsnap_bitcoin_voting_polls_option_2',
                    '_coinsnap_bitcoin_voting_polls_amount',
                    '_coinsnap_bitcoin_voting_polls_currency',
                    '_coinsnap_bitcoin_voting_polls_starting_date',
                    '_coinsnap_bitcoin_voting_polls_ending_date'
                ];

                // Only require custom field name if donor info collection is enabled
                if (
                    isset($data['meta']['_coinsnap_bitcoin_voting_polls_collect_donor_info']) &&
                    $data['meta']['_coinsnap_bitcoin_voting_polls_collect_donor_info']
                ) {
                    $required_meta_fields[] = '_coinsnap_bitcoin_voting_polls_custom_field_name';
                }

                foreach ($required_meta_fields as $field) {
                    if (empty($data['meta'][$field])) {
                        return new WP_Error('missing_required_field', "Error: $field is required.", ['status' => 400]);
                    }
                }
            }
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $json_body = file_get_contents('php://input');
            $data = json_decode($json_body, true);

            if (isset($data['meta']) && is_array($data['meta'])) {
                foreach ($fields as $field => $type) {
                    $json_key = '_' . $field;
                    if (isset($data['meta'][$json_key])) {
                        $value = $data['meta'][$json_key];
                        if ($type === 'boolean') {
                            $value = (bool)$value;
                        } elseif ($type === 'number') {
                            $value = floatval($value);
                        } else {
                            $value = sanitize_text_field($value);
                        }
                        update_post_meta($post_id, $json_key, $value);
                    }
                }
            }
            return;
        }

        foreach ($fields as $field => $type) {
            if ($type === 'boolean') {
                $value = (null !== filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS)) ? '1' : '';
                update_post_meta($post_id, '_' . $field, $value);
            }
            else {
                if (null !== filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
                    $value = filter_input(INPUT_POST,$field,FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    if ($type === 'number') {
                        $value = floatval($value);
                    }
                    else {
                        $value = sanitize_text_field($value);
                    }
                    update_post_meta($post_id, '_' . $field, $value);
                }
            }
        }
    }

    public function add_custom_columns($columns)
    {

        $new_columns = [
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'shortcode' => 'Shortcode',
            'amount' => 'Amount (satoshis)',
            'starting_date' => 'Starting Date',
            'ending_date' => 'Ending Date',
            'thank_you_message' => 'Thank You Message',
            'active' => 'Active',
            'one_vote' => 'One Vote',
        ];

        return $new_columns;
    }

    public function populate_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'description':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_description', true) ?: '');
                break;
            case 'option_1':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_option_1', true) ?: '');
                break;
            case 'option_2':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_option_2', true) ?: '');
                break;
            case 'option_3':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_option_3', true) ?: '');
                break;
            case 'option_4':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_option_4', true) ?: '');
                break;
            case 'amount':
                $amount = get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_amount', true);
                echo esc_html($amount ?: '0');
                break;
            case 'currency':
                $amount = esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_currency', true));
                break;
            case 'starting_date':
                $date = get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_starting_date', true);
                echo esc_html($date ?: '-');
                break;
            case 'ending_date':
                $date = get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_ending_date', true);
                echo esc_html($date ?: '-');
                break;
            case 'thank_you_message':
                echo esc_html(get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_thank_you_message', true) ?: '');
                break;
            case 'active':
                echo get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_active', true) ? '✓' : '✗';
                break;
            case 'one_vote':
                echo get_post_meta($post_id, '_coinsnap_bitcoin_voting_polls_one_vote', true) ? '✓' : '✗';
                break;
            case 'shortcode':
                echo '[coinsnap_bitcoin_voting id="' . esc_html($post_id) . '"]';
                break;
        }
    }
}

new Coinsnap_Bitcoin_Voting_Polls_Metabox();
