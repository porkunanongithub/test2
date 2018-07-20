<?php
if (!defined('WPINC'))
    exit;

class Xa_Woocommerce_Membership_Plans {

    public function __construct() {
        //  memberships access upon products purchases
        //error_log("reached");
        add_action('woocommerce_order_status_completed', array($this, 'grant_access_to_membership_from_order'), 99);
        add_action('woocommerce_order_status_processing', array($this, 'grant_access_to_membership_from_order'), 99);
    }

    public function hforce_get_membership_plan($post = null, $user_membership = null) {

        if (empty($post) && isset($GLOBALS['post'])) {

            $post = $GLOBALS['post'];
        } elseif (is_numeric($post)) {

            $post = get_post($post);
        } elseif ($post instanceof XA_Woocommerce_Membership_Plan) {

            $post = get_post($post->get_id());
        } elseif (is_string($post)) {

            $posts = get_posts(array(
                'name' => $post,
                'post_type' => 'hf_membership_plan',
                'posts_per_page' => 1,
            ));

            if (!empty($posts)) {
                $post = $posts[0];
            }
        } elseif (!( $post instanceof WP_Post )) {

            $post = null;
        }

        // if no acceptable post is found, bail out
        if (!$post || 'hf_membership_plan' !== get_post_type($post)) {
            return false;
        }

        if (is_numeric($user_membership)) {

            $usr = new Xa_Woocommerce_User_Memberships();
            $user_membership = $usr->get_user_membership($user_membership);
        }

        $membership_plan = new XA_Woocommerce_Membership_Plan($post);

        return apply_filters('hf_memberships_membership_plan', $membership_plan, $post, $user_membership);
    }

    public function get_membership_plans_access_length_periods($with_labels = false) {

        $access_length_periods = array(
            'days' => __('Day(s)', 'xa-woocommerce-membership'),
            'weeks' => __('Week(s)', 'xa-woocommerce-membership'),
            'months' => __('Month(s)', 'xa-woocommerce-membership'),
            'years' => __('Year(s)', 'xa-woocommerce-membership'),
        );


        $access_length_periods = apply_filters('hf_memberships_plan_access_period_options', $access_length_periods);

        return true !== $with_labels ? array_keys($access_length_periods) : $access_length_periods;
    }

    public function render_general_tab_html($membership_plan, $post) {
        ?>
        <div id="hf-membership-plan-data-general" class="panel woocommerce_options_panel">

            <div class="options_group">
        <?php
        woocommerce_wp_text_input(array(
            'id' => 'post_name',
            'label' => __('Slug', 'xa-woocommerce-membership'),
            'value' => $post->post_name,
        ));
        ?>
            </div>

            <div class="options_group">

        <?php $current_access_type = 'purchase' ?>

                <p class="form-field plan-access-method-field">
                    <label for="_access_method"><?php esc_html_e('Grant access on', 'xa-woocommerce-membership'); ?></label>
                </p>

                <p class="form-field js-show-if-access-method-purchase <?php if ('purchase' !== $current_access_type) : ?>hide<?php endif; ?>">
                    <label for="_product_ids"><?php esc_html_e('product(s) purchase', 'xa-woocommerce-membership'); ?></label>

                    <select
                        name="_product_ids[]"
                        id="_product_ids"
                        class="wc-product-search"
                        style="width: 90%;"
                        multiple="multiple"
                        data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'xa-woocommerce-membership'); ?>">
        <?php $product_ids = $membership_plan->get_product_ids(); ?>
                            <?php foreach ($product_ids as $product_id) : ?>
                                <?php if ($product = wc_get_product($product_id)) : ?>
                                <option value="<?php echo $product_id; ?>" selected><?php echo esc_html($product->get_formatted_name()); ?></option>
                                <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

        <?php echo wc_help_tip(__('Leave empty to only allow members you manually assign.', 'xa-woocommerce-membership')); ?>
                </p>

            </div>

        </div>
        <?php
    }

    public function update_plan_data($post_id, WP_Post $post) {



        $membership_plan = new XA_Woocommerce_Membership_Plan($post);


        if ($membership_plan) {

            $membership_plan->set_access_method('purchase');
            $membership_plan->delete_access_length();
            $membership_plan->delete_access_start_date();
            $membership_plan->delete_access_end_date();


            if ($membership_plan->is_access_method('purchase')) {

                if (!empty($_POST['_product_ids'])) {

                    $membership_plan->set_product_ids($_POST['_product_ids']);
                } else {

                    $membership_plan->delete_product_ids();
                    $membership_plan->set_access_method('manual-only');
                }
            } else {
                $membership_plan->delete_product_ids();
            }
        }
    }

    public function get_available_membership_plans($values = 'objects') {

        $available_plans = array();
        $membership_plans = $this->get_membership_plans(array(
            'post_status' => array('publish', 'private', 'future', 'draft', 'pending')
                ));

        if (!empty($membership_plans)) {

            foreach ($membership_plans as $membership_plan) {

                if ('labels' === $values) {

                    $membership_plan_name = $membership_plan->get_name();

                    if ('publish' !== $membership_plan->post->post_status) {

                        $membership_plan_name = sprintf(__('%s (inactive)', 'xa-woocommerce-membership'), $membership_plan_name);
                    }

                    $available_plans[$membership_plan->get_id()] = $membership_plan_name;
                } elseif ('objects' === $values) {

                    $available_plans[$membership_plan->get_id()] = $membership_plan;
                } elseif ('ids' === $values) {

                    $available_plans[] = $membership_plan->get_id();
                }
            }
        }

        return $available_plans;
    }

    public function get_membership_plans($args = array()) {

        $args = wp_parse_args($args, array(
            'posts_per_page' => -1,
                ));

        $args['post_type'] = 'hf_membership_plan';

        // unique key for caching the applied rule results
        $cache_key = http_build_query($args);

        if (!isset($this->membership_plans[$cache_key])) {

            $membership_plan_posts = get_posts($args);

            $this->membership_plans[$cache_key] = array();

            if (!empty($membership_plan_posts)) {

                foreach ($membership_plan_posts as $post) {
                    $this->membership_plans[$cache_key][] = $this->get_membership_plan($post);
                }
            }
        }

        return $this->membership_plans[$cache_key];
    }

    public function get_membership_plan($post = null, $user_membership = null) {

        if (empty($post) && isset($GLOBALS['post'])) {

            $post = $GLOBALS['post'];
        } elseif (is_numeric($post)) {

            $post = get_post($post);
        } elseif ($post instanceof XA_Woocommerce_Membership_Plan) {

            $post = get_post($post->get_id());
        } elseif (is_string($post)) {

            $posts = get_posts(array(
                'name' => $post,
                'post_type' => 'hf_membership_plan',
                'posts_per_page' => 1,
                    ));

            if (!empty($posts)) {
                $post = $posts[0];
            }
        } elseif (!( $post instanceof WP_Post )) {

            $post = null;
        }

        // if no acceptable post is found, bail out
        if (!$post || 'hf_membership_plan' !== get_post_type($post)) {
            return false;
        }

        if (is_numeric($user_membership)) {
            $usr_membership_obj = new XA_Woocommerce_User_Memberships();
            $user_membership = $usr_membership_obj->get_user_membership($user_membership);
        }

        $membership_plan = new XA_Woocommerce_Membership_Plan($post);


        return apply_filters('hf_memberships_membership_plan', $membership_plan, $post, $user_membership);
    }

    public function grant_access_to_membership_from_order($order) {

        $order = is_numeric($order) ? wc_get_order((int) $order) : $order;

        if (!$order instanceof WC_Order) {
            return;
        }

        $order_items = $order->get_items();
        $user_id = $order->get_user_id();
        $membership_plans = $this->get_membership_plans();

        //error_log("user-id" . $user_id);


        if (!$user_id || empty($order_items) || empty($membership_plans)) {
            return;
        }


        foreach ($membership_plans as $plan) {
            //error_log("has products" . $plan->has_products());

            if (!$plan->has_products()) {
                continue;
            }

            $access_granting_product_ids = hforce_memberships_get_order_access_granting_product_ids($plan, $order, $order_items);

            if (!empty($access_granting_product_ids)) {

                $order_granted_access_already = hforce_memberships_has_order_granted_access($order, array('membership_plan' => $plan));

                foreach ($access_granting_product_ids as $product_id) {


                    if (!$plan->has_product($product_id)) {
                        continue;
                    }


                    $grant_access = (bool) apply_filters('hf_memberships_grant_access_from_new_purchase', !$order_granted_access_already, array(
                                'user_id' => (int) $user_id,
                                'product_id' => (int) $product_id,
                                'order_id' => (int) $order->get_id(),
                            ));

                    if ($grant_access) {

                        $plan->grant_access_from_purchase($user_id, $product_id, (int) $order->get_id());
                    }
                }
            }
        }
    }

}

new Xa_Woocommerce_Membership_Plans();