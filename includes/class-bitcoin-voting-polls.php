<?php
if (!defined('ABSPATH')) {
    exit;
}

class Bitcoin_Voting_Polls_Metabox
{
    public function __construct()
    {
        add_action('init', [$this, 'register_polls_post_type']);
        add_action('init', [$this, 'register_custom_meta_fields']);
        add_action('add_meta_boxes', [$this, 'add_polls_metaboxes']);
        add_action('save_post', [$this, 'save_polls_meta'], 10, 2);
        add_filter('manage_bitcoin-polls_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_bitcoin-polls_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
    }

    public function register_polls_post_type()
    {
        register_post_type('bitcoin-polls', [
            'labels' => [
                'name'               => 'Polls',
                'singular_name'      => 'Poll',
                'menu_name'          => 'Polls',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Poll',
                'edit_item'          => 'Edit Poll',
                'new_item'           => 'New Poll',
                'view_item'          => 'View Poll',
                'search_items'       => 'Search Polls',
                'not_found'          => 'No polls found',
                'not_found_in_trash' => 'No polls found in Trash',
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'bitcoin-polls'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
            'show_in_rest'       => true
        ]);
    }

    public function register_custom_meta_fields()
    {
        register_meta('post', '_bitcoin_voting_polls_description', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_option_1', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_bitcoin_voting_polls_option_2', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_bitcoin_voting_polls_option_3', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_bitcoin_voting_polls_option_4', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_amount', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
            'description' => 'Amount in satoshis',
        ]);

        register_meta('post', '_bitcoin_voting_polls_starting_date', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_ending_date', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_thank_you_message', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_active', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_one_vote', [
            'object_subtype' => 'bitcoin-polls',
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_polls_collect_donor_info', [
            'object_subtype' => 'bitcoin-polls',
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
            'custom_field'
        ];

        foreach ($donor_fields as $field) {
            register_meta('post', '_bitcoin_voting_polls_' . $field, [
                'object_subtype' => 'bitcoin-polls',
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ]);
        }
    }

    public function add_polls_metaboxes()
    {
        add_meta_box(
            'bitcoin_voting_polls_details',
            'Polls Details',
            [$this, 'render_polls_metabox'],
            'bitcoin-polls',
            'normal',
            'high'
        );
    }

    public function render_polls_metabox($post)
    {
        wp_nonce_field('bitcoin_voting_polls_nonce', 'bitcoin_voting_polls_nonce');

        $description = get_post_meta($post->ID, '_bitcoin_voting_polls_description', true);
        $option_1 = get_post_meta($post->ID, '_bitcoin_voting_polls_option_1', true);
        $option_2 = get_post_meta($post->ID, '_bitcoin_voting_polls_option_2', true);
        $option_3 = get_post_meta($post->ID, '_bitcoin_voting_polls_option_3', true);
        $option_4 = get_post_meta($post->ID, '_bitcoin_voting_polls_option_4', true);
        $amount = get_post_meta($post->ID, '_bitcoin_voting_polls_amount', true);
        $starting_date = get_post_meta($post->ID, '_bitcoin_voting_polls_starting_date', true);
        $ending_date = get_post_meta($post->ID, '_bitcoin_voting_polls_ending_date', true);
        $thank_you_message = get_post_meta($post->ID, '_bitcoin_voting_polls_thank_you_message', true);
        $active = get_post_meta($post->ID, '_bitcoin_voting_polls_active', true);
        if ($active === '') {
            $active = '1';
        }
        $one_vote = get_post_meta($post->ID, '_bitcoin_voting_polls_one_vote', true);
        $collect_donor_info = get_post_meta($post->ID, '_bitcoin_voting_polls_collect_donor_info', true);

        $donor_fields = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'address' => 'Address',
            'custom_field' => 'Custom Field Name',
        ];

        $field_values = [];
        foreach ($donor_fields as $field => $label) {
            $field_values[$field] = get_post_meta($post->ID, '_bitcoin_voting_polls_' . $field, true);
        }

        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}voting_payments WHERE status = 'completed' AND poll_id = %d",
            $post->ID
        );
        $results = $wpdb->get_results($query);
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

        wp_enqueue_script('jquery');
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function toggleDonorFields() {
                    if ($('input[name="bitcoin_voting_polls_collect_donor_info"]').is(':checked')) {
                        $('#donor-info-fields').show();
                    } else {
                        $('#donor-info-fields').hide();
                    }
                }

                $('input[name="bitcoin_voting_polls_collect_donor_info"]').change(toggleDonorFields);
                toggleDonorFields();
            });
        </script>

        <table class="form-table">
            <tr>
                <th scope="row">Active</th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="bitcoin_voting_polls_active"
                            value="1"
                            <?php checked($active, '1'); ?>>
                        Enable
                    </label>
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row">One Vote Per User</th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="bitcoin_voting_polls_one_vote"
                            value="1"
                            <?php checked($one_vote, '1'); ?>>
                        Enable
                    </label>
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_description"><?php echo esc_html_e('Description', 'bitcoin-voting-polls') ?></label>
                </th>
                <td>
                    <textarea
                        id="bitcoin_voting_polls_description"
                        name="bitcoin_voting_polls_description"
                        class="regular-text"
                        rows="2"
                        required
                        style="width: 350px"><?php echo esc_textarea($description); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_option_1"><?php echo esc_html_e('Option 1', 'bitcoin-voting-polls') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_1']); ?> votes
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="bitcoin_voting_polls_option_1"
                        name="bitcoin_voting_polls_option_1"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($option_1); ?>">

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_option_2"><?php echo esc_html_e('Option 2', 'bitcoin-voting-polls') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_2']); ?> votes
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="bitcoin_voting_polls_option_2"
                        name="bitcoin_voting_polls_option_2"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($option_2); ?>">

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_option_3"><?php echo esc_html_e('Option 3', 'bitcoin-voting-polls') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_3']); ?> votes
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="bitcoin_voting_polls_option_3"
                        name="bitcoin_voting_polls_option_3"
                        class="regular-text"
                        value="<?php echo esc_attr($option_3); ?>">

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_option_4"><?php echo esc_html_e('Option 4', 'bitcoin-voting-polls') ?></label>
                    <span style="font-weight: normal;">
                        (
                        <?php echo esc_attr($votes['option_4']); ?> votes
                        )
                    </span>
                </th>
                <td>
                    <input
                        type="text"
                        id="bitcoin_voting_polls_option_4"
                        name="bitcoin_voting_polls_option_4"
                        class="regular-text"
                        value="<?php echo esc_attr($option_4); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_amount"><?php echo esc_html_e('Amount (in satoshis)', 'bitcoin-voting-polls') ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="bitcoin_voting_polls_amount"
                        name="bitcoin_voting_polls_amount"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($amount); ?>"
                        min="0"
                        step="1">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_starting_date"><?php echo esc_html_e('Starting Date', 'bitcoin-voting-polls') ?></label>
                </th>
                <td>
                    <input
                        type="datetime-local"
                        id="bitcoin_voting_polls_starting_date"
                        name="bitcoin_voting_polls_starting_date"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($starting_date); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_ending_date"><?php echo esc_html_e('Ending Date', 'bitcoin-voting-polls') ?></label>
                </th>
                <td>
                    <input
                        type="datetime-local"
                        id="bitcoin_voting_polls_ending_date"
                        name="bitcoin_voting_polls_ending_date"
                        class="regular-text"
                        required
                        value="<?php echo esc_attr($ending_date); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_polls_thank_you_message"><?php echo esc_html_e('Thank You Message', 'bitcoin-voting-polls') ?></label>
                </th>
                <td>
                    <textarea
                        id="bitcoin_voting_polls_thank_you_message"
                        name="bitcoin_voting_polls_thank_you_message"
                        class="regular-text"
                        rows="2"
                        required
                        style="width: 350px"><?php echo esc_textarea($thank_you_message); ?></textarea>

                </td>
            </tr>
            <th scope="row">
                <label for="shortcode"><?php echo esc_html_e('Shortcode', 'bitcoin-voting-polls') ?></label>
            </th>
            <td>
                <input
                    type="text"
                    id="shortcode"
                    name="shortcode"
                    class="regular-text"
                    readonly
                    value='[bitcoin_voting id="<?php echo esc_html($post->ID); ?>"]'>
            </td>
            </tr>
            <tr>
                <th scope="row">Collect Donor Information</th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="bitcoin_voting_polls_collect_donor_info"
                            value="1"
                            <?php checked($collect_donor_info, '1'); ?>>
                        Enable
                    </label>
                    <br>
                </td>
            </tr>
        </table>

        <div id="donor-info-fields" style="margin-top: 20px;">
            <h3>Donor Information Fields</h3>
            <table class="form-table">
                <?php
                foreach ($donor_fields as $field => $label) {
                    $visibility_value = get_post_meta($post->ID, '_bitcoin_voting_polls_' . $field . '_visibility', true) ?: 'optional';
                ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <select name="bitcoin_voting_polls_<?php echo esc_attr($field); ?>_visibility">
                                <option value="mandatory" <?php selected($visibility_value, 'mandatory'); ?>>Mandatory</option>
                                <option value="optional" <?php selected($visibility_value, 'optional'); ?>>Optional</option>
                                <option value="hidden" <?php selected($visibility_value, 'hidden'); ?>>Hidden</option>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
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
            $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field($_SERVER['HTTP_X_WP_NONCE']) : '';
        } else {
            $expected_nonce = 'bitcoin_voting_polls_nonce';
            $nonce = filter_input(INPUT_POST, $expected_nonce, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        if (empty($nonce) || !wp_verify_nonce($nonce, $expected_nonce)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_type !== 'bitcoin-polls') {
            return;
        }

        $fields = [
            'bitcoin_voting_polls_description' => 'text',
            'bitcoin_voting_polls_option_1'    => 'text',
            'bitcoin_voting_polls_option_2'    => 'text',
            'bitcoin_voting_polls_option_3'    => 'text',
            'bitcoin_voting_polls_option_4'    => 'text',
            'bitcoin_voting_polls_amount'      => 'number',
            'bitcoin_voting_polls_starting_date' => 'text',
            'bitcoin_voting_polls_ending_date'   => 'text',
            'bitcoin_voting_polls_thank_you_message' => 'text',
            'bitcoin_voting_polls_active'      => 'boolean',
            'bitcoin_voting_polls_one_vote'    => 'boolean',
            'bitcoin_voting_polls_collect_donor_info' => 'boolean',
        ];

        $donor_fields = ['first_name', 'last_name', 'email', 'address', 'custom_field'];
        foreach ($donor_fields as $field) {
            $fields['bitcoin_voting_polls_' . $field . '_visibility'] = 'text';
        }

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            $required_fields = [
                'bitcoin_voting_polls_description',
                'bitcoin_voting_polls_option_1',
                'bitcoin_voting_polls_option_2',
                'bitcoin_voting_polls_amount',
                'bitcoin_voting_polls_starting_date',
                'bitcoin_voting_polls_ending_date'
            ];

            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    wp_die("Error: $field is required.");
                }
            }
        } else {
            $json_body = file_get_contents('php://input');
            $data = json_decode($json_body, true);

            if (isset($data['meta']) && is_array($data['meta'])) {
                $required_meta_fields = [
                    '_bitcoin_voting_polls_description',
                    '_bitcoin_voting_polls_option_1',
                    '_bitcoin_voting_polls_option_2',
                    '_bitcoin_voting_polls_amount',
                    '_bitcoin_voting_polls_starting_date',
                    '_bitcoin_voting_polls_ending_date'
                ];

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
                $value = isset($_POST[$field]) ? '1' : '';
                update_post_meta($post_id, '_' . $field, $value);
            } else {
                if (isset($_POST[$field])) {
                    $value = $_POST[$field];
                    if ($type === 'number') {
                        $value = floatval($value);
                    } else {
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
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_polls_description', true) ?: '');
                break;
            case 'option_1':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_polls_option_1', true) ?: '');
                break;
            case 'option_2':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_polls_option_2', true) ?: '');
                break;
            case 'option_3':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_polls_option_3', true) ?: '');
                break;
            case 'option_4':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_polls_option_4', true) ?: '');
                break;
            case 'amount':
                $amount = get_post_meta($post_id, '_bitcoin_voting_polls_amount', true);
                echo esc_html($amount ?: '0');
                break;
            case 'starting_date':
                $date = get_post_meta($post_id, '_bitcoin_voting_polls_starting_date', true);
                echo esc_html($date ?: '-');
                break;
            case 'ending_date':
                $date = get_post_meta($post_id, '_bitcoin_voting_polls_ending_date', true);
                echo esc_html($date ?: '-');
                break;
            case 'thank_you_message':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_polls_thank_you_message', true) ?: '');
                break;
            case 'active':
                echo get_post_meta($post_id, '_bitcoin_voting_polls_active', true) ? '✓' : '✗';
                break;
            case 'one_vote':
                echo get_post_meta($post_id, '_bitcoin_voting_polls_one_vote', true) ? '✓' : '✗';
                break;
            case 'shortcode':
                echo '[bitcoin_voting id="' . esc_html($post_id) . '"]';
                break;
        }
    }
}

new Bitcoin_Voting_Polls_Metabox();
