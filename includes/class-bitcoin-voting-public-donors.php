<?php
if (!defined('ABSPATH')) {
    exit;
}

class Bitcoin_Voting_Public_Donors
{
    public function __construct()
    {
        add_action('init', [$this, 'register_public_donors_post_type']);
        add_action('init', [$this, 'register_custom_meta_fields']);
        add_action('add_meta_boxes', [$this, 'add_public_donors_metaboxes']);
        add_action('save_post', [$this, 'save_public_donors_meta'], 10, 2);
        add_filter('manage_bitcoin-pds_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_bitcoin-pds_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
    }

    public function register_public_donors_post_type()
    {
        register_post_type('bitcoin-pds', [
            'labels' => [
                'name'               => 'Donor Information',
                'singular_name'      => 'Donor Information',
                'menu_name'          => 'Donor Information',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Donor',
                'edit_item'          => 'Edit Donor',
                'new_item'           => 'New Donor',
                'view_item'          => 'View Donor',
                'search_items'       => 'Search Donors',
                'not_found'          => 'No donors found',
                'not_found_in_trash' => 'No donors found in Trash',
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'bitcoin-pds'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
            'show_in_rest'       => true
        ]);
    }

    public function register_custom_meta_fields()
    {
        register_meta('post', '_bitcoin_voting_donor_name', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_amount', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_message', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_form_type', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_email', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_address', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_payment_id', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', '_bitcoin_voting_custom_field', [
            'object_subtype' => 'bitcoin-pds',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }

    public function add_public_donors_metaboxes()
    {
        add_meta_box(
            'bitcoin_voting_public_donors_details',
            'Donor Details',
            [$this, 'render_public_donors_metabox'],
            'bitcoin-pds',
            'normal',
            'high'
        );
    }

    public function render_public_donors_metabox($post)
    {
        wp_nonce_field('bitcoin_voting_public_donors_nonce', 'bitcoin_voting_public_donors_nonce');

        $name = get_post_meta($post->ID, '_bitcoin_voting_donor_name', true);
        $amount = get_post_meta($post->ID, '_bitcoin_voting_amount', true);
        $message = get_post_meta($post->ID, '_bitcoin_voting_message', true);
        $form_type = get_post_meta($post->ID, '_bitcoin_voting_form_type', true);
        $email = get_post_meta($post->ID, '_bitcoin_voting_email', true);
        $address = get_post_meta($post->ID, '_bitcoin_voting_address', true);
        $payment_id = get_post_meta($post->ID, '_bitcoin_voting_payment_id', true);
        $custom_field = get_post_meta($post->ID, '_bitcoin_voting_custom_field', true);
?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_donor_name"><?php echo esc_html_e('Name', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="text" id="bitcoin_voting_donor_name" name="bitcoin_voting_donor_name" class="regular-text" value="<?php echo esc_attr($name); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_amount"><?php echo esc_html_e('Amount', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="text" id="bitcoin_voting_amount" name="bitcoin_voting_amount" class="regular-text" value="<?php echo esc_attr($amount); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_message"><?php echo esc_html_e('Message', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <textarea id="bitcoin_voting_message" name="bitcoin_voting_message" class="regular-text" rows="3" readonly><?php echo esc_textarea($message); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_form_type"><?php echo esc_html_e('Form Type', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="text" id="bitcoin_voting_form_type" name="bitcoin_voting_form_type" class="regular-text" value="<?php echo esc_attr($form_type); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_email"><?php echo esc_html_e('Email', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="email" id="bitcoin_voting_email" name="bitcoin_voting_email" class="regular-text" value="<?php echo esc_attr($email); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_address"><?php echo esc_html_e('Address', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="text" id="bitcoin_voting_address" name="bitcoin_voting_address" class="regular-text" value="<?php echo esc_attr($address); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_payment_id"><?php echo esc_html_e('Payment ID', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="text" id="bitcoin_voting_payment_id" name="bitcoin_voting_payment_id" class="regular-text" value="<?php echo esc_attr($payment_id); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="bitcoin_voting_custom_field"><?php echo esc_html_e('Custom Field', 'bitcoin-voting') ?></label>
                </th>
                <td>
                    <input type="text" id="bitcoin_voting_custom_field" name="bitcoin_voting_custom_field" class="regular-text" value="<?php echo esc_attr($custom_field); ?>" readonly>
                </td>
            </tr>
        </table>
<?php
    }

    public function save_public_donors_meta($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (!current_user_can('edit_post', $post_id)) return;

        if ($post->post_type !== 'bitcoin-pds') return;

        if (
            !isset($_POST['bitcoin_voting_public_donors_nonce']) ||
            !wp_verify_nonce($_POST['bitcoin_voting_public_donors_nonce'], 'bitcoin_voting_public_donors_nonce')
        ) {
            return;
        }

        $fields = [
            'bitcoin_voting_donor_name' => 'text',
            'bitcoin_voting_amount' => 'text',
            'bitcoin_voting_message' => 'text',
            'bitcoin_voting_form_type' => 'text',
            'bitcoin_voting_email' => 'text',
            'bitcoin_voting_address' => 'text',
            'bitcoin_voting_payment_id' => 'text',
            'bitcoin_voting_custom_field' => 'text',
        ];

        foreach ($fields as $field => $type) {
            if ($type === 'boolean') {
                $value = isset($_POST[$field]) ? '1' : '';
            } else {
                $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            }
            update_post_meta($post_id, '_' . $field, $value);
        }
    }

    public function add_custom_columns($columns)
    {
        return [
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'name' => 'Name',
            'email' => 'Email',
            'amount' => 'Amount',
            'message' => 'Message',
            'address' => 'Address',
            'payment_id' => 'Payment ID',
            'form_type' => 'Form Type',
            'custom_field' => 'Custom Field'
        ];
    }

    public function populate_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'name':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_donor_name', true));
                break;
            case 'amount':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_amount', true));
                break;
            case 'message':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_message', true));
                break;
            case 'form_type':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_form_type', true));
                break;
            case 'email':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_email', true));
                break;
            case 'address':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_address', true));
                break;
            case 'payment_id':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_payment_id', true));
                break;
            case 'custom_field':
                echo esc_html(get_post_meta($post_id, '_bitcoin_voting_custom_field', true));
                break;
        }
    }
}

new Bitcoin_Voting_Public_Donors();
