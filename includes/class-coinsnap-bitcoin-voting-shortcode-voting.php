<?php
if (!defined('ABSPATH')){ exit; }

class Coinsnap_Bitcoin_Voting_Shortcode_Voting {
    
    public function __construct(){
        add_shortcode('coinsnap_bitcoin_voting', [$this, 'coinsnap_bitcoin_voting_render_shortcode_voting']);
    }

    private function get_template($template_name, $args = [])
    {
        if ($args && is_array($args)) {
            extract($args);
        }

        $template = plugin_dir_path(__FILE__) . '../templates/' . $template_name . '.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    function coinsnap_bitcoin_voting_render_shortcode_voting($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'coinsnap_bitcoin_voting');

        $poll_id = intval($atts['id']);
        // Check if poll_id is valid and post exists
        if (!$poll_id || get_post_type($poll_id) !== 'coinsnap-polls') {
            return '<p>Invalid or missing poll ID.</p>';
        }

        // Retrieve poll meta data
        $title = get_the_title($poll_id);
        $thank_you = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_thank_you_message', true);
        $description = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_description', true);
        $amount = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_amount', true);
        $currency = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_currency', true);
        $start_date = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_starting_date', true);
        $end_date = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_ending_date', true);
        $num_options = 0;
        $options = array();
        $options_general = get_option('coinsnap_bitcoin_voting_options');
        $theme_class = (($options_general['theme'] ?? '') === 'dark') ? 'dark-theme' : 'light-theme';
        $active = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_active', true);
        $one_vote = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_one_vote', true);
        $collect_donor_info = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_collect_donor_info', true);
        $first_name = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_first_name_visibility')[0];
        $last_name = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_last_name_visibility')[0];
        $email = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_email_visibility')[0];
        $address = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_address_visibility')[0];
        $custom = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_custom_field_visibility')[0];
        $custom_name = get_post_meta($poll_id, '_coinsnap_bitcoin_voting_polls_custom_field_name')[0];
        // Check if poll is active
        if (!$active) {
            ob_start();
?>
            <div id="coinsnap-bitcoin-voting-form" class="coinsnap-bitcoin-voting-form  <?php echo esc_attr($theme_class); ?>" data-one-vote="<?php echo esc_attr($one_vote) ?>" data-donor-info="<?php echo esc_attr($collect_donor_info) ?> ">
                <div class="coinsnap-bitcoin-voting-form-container">
                    <h3><?php echo esc_html($title ?:  'Coinsnap Bitcoin Voting'); ?></h3>
                    <p><?php echo esc_html($description ?: 'What would you like to see more of on our blog?'); ?></p>
                    <h4>This poll is not active</h4>
                </div>
            </div>
        <?php
            return ob_get_clean();
        }

        // Fetch options from meta data
        for ($i = 1; $i <= 4; $i++) {
            $option = get_post_meta($poll_id, "_coinsnap_bitcoin_voting_polls_option_{$i}", true);
            if (!empty($option)) {
                $options[$i] = $option;
                $num_options++;
            }
        }

        ob_start();

        $now = current_time('timestamp');
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        if ($now < $start_timestamp) {
            $time_until_start = human_time_diff($now, $start_timestamp);
        ?>
            <div id="coinsnap-bitcoin-voting-form" class="coinsnap-bitcoin-voting-form  <?php echo esc_attr($theme_class); ?>" data-one-vote="<?php echo esc_attr($one_vote) ?> " data-donor-info="<?php echo esc_attr($collect_donor_info) ?> ">
                <div class="coinsnap-bitcoin-voting-form-container">
                    <h3><?php echo esc_html($title ?:  'Coinsnap Bitcoin Voting'); ?></h3>
                    <p><?php echo esc_html($description ?: 'What would you like to see more of on our blog?'); ?></p>
                    <h4>Poll starting in: <?php echo esc_html($time_until_start); ?></h4>
                </div>
            </div>
        <?php
        } elseif ($now > $end_timestamp) {
        ?>
            <div id="coinsnap-bitcoin-voting-form" class="coinsnap-bitcoin-voting-form  <?php echo esc_attr($theme_class); ?>" data-one-vote="<?php echo esc_attr($one_vote) ?>" data-donor-info="<?php echo esc_attr($collect_donor_info) ?> ">
                <div class="coinsnap-bitcoin-voting-form-container">
                    <h3><?php echo esc_html($title ?:  'Voting Poll'); ?></h3>
                    <p><?php echo esc_html($description ?: ''); ?></p>
                    <div id="poll-results<?php echo esc_html($poll_id); ?>" class="poll-results" data-end-date="<?php echo esc_html($end_date); ?>" data-poll-id="<?php echo esc_html($poll_id); ?>">
                        <?php
                        for ($i = 1; $i <= min(4, $num_options ?: 4); $i++):
                            if (isset($options[$i])):
                        ?>
                                <div class="poll-result">
                                    <div class="poll-result-title">
                                        <span>
                                            <?php echo esc_html($options[$i]); ?> (<span class="vote-count" data-option="<?php echo esc_html($i); ?>">0</span> votes)
                                        </span>
                                        <span class="voting-progress-percentage" data-option="<?php echo esc_html($i); ?>"></span>
                                    </div>
                                    <div class="voting-progress">
                                        <div class="voting-progress-bar" data-option="<?php echo esc_html($i); ?>"></div>
                                    </div>

                                </div>
                        <?php endif;
                        endfor; ?>
                        <div class="poll-total-votes">
                            <div class="poll-total-wrapper">
                                Total votes:
                                <div id="total-votes<?php echo esc_html($poll_id); ?>">
                                </div>
                            </div>
                            <div class="end-text"><?php echo '<div>Poll closed</div>'; ?></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        } else {
            $time_until_end = human_time_diff($now, $end_timestamp);
            // The same price check the server uses when it creates the invoice.
            $checkInvoice = Coinsnap_Bitcoin_Voting_Security::poll_price($poll_id, Coinsnap_Bitcoin_Voting_Security::config());
        ?>
            <div id="coinsnap-bitcoin-voting-form" class="coinsnap-bitcoin-voting-form <?php echo esc_attr($theme_class);?>" data-poll-id="<?php echo esc_attr($poll_id);?>"
                data-poll-amountfiat="<?php echo esc_attr($amount ?: '0'); ?>" data-poll-amount="<?php if($checkInvoice['result']){ echo esc_attr($checkInvoice['sats']); } ?>" data-poll-currency="<?php echo esc_attr($currency); ?>"
                data-one-vote="<?php echo esc_attr($one_vote) ?>" data-donor-info="<?php echo esc_attr($collect_donor_info) ?> ">

                <div class="coinsnap-bitcoin-voting-form-container">
                    <div class="title-container">
                        <h3><?php echo esc_html($title ?:  'Coinsnap Bitcoin Voting'); ?></h3>
                        <button id="return-button<?php echo esc_html($poll_id); ?>" style="display: none;" class="return-button">&#8592;</button>
                    </div>
                    <p><?php echo esc_html($description ?: ''); ?></p>
                    <div class="poll-options">
                        <?php
                        for ($i = 1; $i <= min(4, $num_options ?: 4); $i++){
                            if (isset($options[$i])){?>
                                <button class="poll-option" data-option="<?php echo esc_html($i); ?>" <?php if(!$checkInvoice['result']){ echo ' disabled="disabled"'; }?>><?php echo esc_html($options[$i]); ?></button>
                            <?php
                            };
                        }?>
                        <div class="poll-total-votes">
                            <button id="check-results<?php echo esc_html($poll_id);?>" data-poll-id="<?php echo esc_html($poll_id);?>" class="check-results"><?php echo esc_html__('Check results','coinsnap-bitcoin-voting');?></button>
                            <div class="end-text"><?php echo esc_html__('Ends in:','coinsnap-bitcoin-voting');?> <?php echo esc_html($time_until_end); ?></div>
                        </div>

                    </div><?php
                    
                    //  Poll amount and currency error
                    if(!$checkInvoice['result']){
                        $errorMessage = Coinsnap_Bitcoin_Voting_Security::price_error_message($checkInvoice, (string) $currency);
                        echo '<div class="error-message">'.esc_html($errorMessage).'</div>';
                    }
                    
                    ?>
                    <div class="poll-results" style="display: none;">
                        <?php
                        for ($i = 1; $i <= min(4, $num_options ?: 4); $i++):
                            if (isset($options[$i])):
                        ?>
                                <div class="poll-result">
                                    <div class="poll-result-title">
                                        <span>
                                            <?php echo esc_html($options[$i]); ?> (<span class="vote-count" data-option="<?php echo esc_html($i); ?>">0</span> votes)
                                        </span>
                                        <span class="voting-progress-percentage" data-option="<?php echo esc_html($i); ?>"></span>
                                    </div>
                                    <div class="voting-progress">
                                        <div class="voting-progress-bar" data-option="<?php echo esc_html($i); ?>"></div>
                                    </div>
                                </div> <?php endif;
                                endfor; ?>
                        <div class="poll-total-votes">
                            <div class="poll-total-wrapper">
                                Total votes:
                                <div id="total-votes<?php echo esc_html($poll_id); ?>">
                                </div>
                            </div>
                            <div class="end-text">Ends in: <?php echo esc_html($time_until_end); ?></div>
                        </div>
                    </div>
                </div>
            </div>
<?php ?>
            <div id="coinsnap-bitcoin-voting-blur-overlay<?php echo esc_html($poll_id); ?>" class="blur-overlay coinsnap-bitcoin-voting"></div>
            <?php
            $this->get_template('coinsnap-bitcoin-voting-modal', [
                'prefix' => 'coinsnap-bitcoin-voting-',
                'sufix' => $poll_id,
                'first_name' => $collect_donor_info ? $first_name : 0,
                'last_name' => $collect_donor_info ? $last_name : 0,
                'email' => $collect_donor_info ? $email : 0,
                'address' => $collect_donor_info ? $address : 0,
                'custom' => $collect_donor_info ? $custom : 0,
                'custom_name' => $collect_donor_info ? $custom_name : 0,
                'public_donors' => $collect_donor_info,
                'thank_you' => $thank_you ?? "Your payment was successful",
            ]);
            ?>


<?php
        }

        return ob_get_clean();
    }
}

new Coinsnap_Bitcoin_Voting_Shortcode_Voting();
