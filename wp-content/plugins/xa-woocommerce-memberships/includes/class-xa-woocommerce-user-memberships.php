<?php
if (!defined('WPINC'))
    exit;

class XA_Woocommerce_User_Memberships {

    private $is_user_member = array();

    public function __construct() {




        add_filter('wp_insert_post_data', array($this, 'adjust_user_membership_post_data'));
        add_action('save_post', array($this, 'save_user_membership'), 10, 3);
        add_action('delete_user', array($this, 'delete_user_memberships'));
        add_action('trashed_post', array($this, 'handle_order_trashed'));
        add_action('woocommerce_order_status_refunded', array($this, 'handle_order_refunded'));
        add_action('admin_menu', array($this, 'remove_my_post_metaboxes'));
    }

    public function remove_my_post_metaboxes() {

        remove_meta_box('commentsdiv', array('hf_user_membership',), 'normal'); // Comments Metabox
        remove_meta_box('commentstatusdiv', array('hf_user_membership'), 'normal');
        remove_meta_box('trackbacksdiv', array('hf_user_membership'), 'normal');
    }

    public function create_user_membership($args = array(), $action = 'create') {

        $args = wp_parse_args($args, array(
            'user_membership_id' => 0,
            'plan_id' => 0,
            'user_id' => 0,
            'product_id' => 0,
            'order_id' => 0,
                ));

        $new_membership_data = array(
            'post_parent' => (int) $args['plan_id'],
            'post_author' => (int) $args['user_id'],
            'post_type' => 'hf_user_membership',
            'post_status' => 'hf-active',
            'comment_status' => 'open',
        );

        $updating = false;

        if ((int) $args['user_membership_id'] > 0) {
            $updating = true;
            $new_membership_data['ID'] = (int) $args['user_membership_id'];
        }

        //error_log("args==".print_r($args,1));

        $new_post_data = apply_filters('hf_memberships_new_membership_data', $new_membership_data, array(
            'user_id' => (int) $args['user_id'],
            'product_id' => (int) $args['product_id'],
            'order_id' => (int) $args['order_id'],
                ));

        $membership_plans_obj = new Xa_Woocommerce_Membership_Plans();
        // bail out if a plan cannot be found before setting a new user membership
        if (!$membership_plans_obj->get_membership_plan($args['plan_id'])) {
            throw new Exception(sprintf(__('Cannot create User Membership: Membership Plan with ID %d does not exist', 'xa-woocommerce-membership'), (int) $args['plan_id']));
        }

        if ($updating) {

            // do not modify the post status yet on renewals
            unset($new_post_data['post_status']);

            $user_membership_id = wp_update_post($new_post_data, true);
        } else {

            $user_membership_id = wp_insert_post($new_post_data, true);
        }
        //error_log("memebr===" . var_dump($user_membership_id));
        // bail out on error
        if (0 === $user_membership_id || is_wp_error($user_membership_id)) {
            throw new Exception(sprintf(__('Cannot create User Membership: %s', 'xa-woocommerce-membership'), implode(', ', $user_membership_id->get_error_messages())));
        }

        $usr = new XA_Woocommerce_User_Memberships();
        $user_membership = $usr->get_user_membership($user_membership_id);

        if ((int) $args['product_id'] > 0) {
            $user_membership->set_product_id($args['product_id']);
        }

        if ((int) $args['order_id'] > 0) {
            $user_membership->set_order_id($args['order_id']);
        }

        $user_membership = $usr->get_user_membership($user_membership_id);

        $membership_plan = $membership_plans_obj->get_membership_plan((int) $args['plan_id'], $user_membership);

        if ('renew' !== $action) {

            $start_date = $membership_plan->is_access_length_type('fixed') ? $membership_plan->get_access_start_date() : current_time('mysql', true);

            $user_membership->set_start_date($start_date);
        } elseif ('delayed' !== $user_membership->get_status() && $user_membership->get_start_date('timestamp') > strtotime('tomorrow', current_time('timestamp', true))) {

            $user_membership->update_status('delayed');
        }

        $now = current_time('timestamp', true);
        $is_expired = $user_membership->is_expired();

        if ('renew' === $action && !$is_expired) {
            $end = $user_membership->get_end_date('timestamp');
            $now = !empty($end) ? $end : $now;
        }


        $end_date = $membership_plan->get_expiration_date($now, $args);


        $user_membership->set_end_date($end_date);


        if ('renew' === $action && $user_membership->is_in_active_period()) {

            if ($is_expired) {

                $user_membership->update_status('active');
            } elseif ($user_membership->has_status('cancelled')) {


                $renew_cancelled_membership = (bool) apply_filters('hf_memberships_renew_cancelled_membership', true, $user_membership, $args);

                if (true === $renew_cancelled_membership) {

                    $user_membership->update_status('active');
                }
            }
        }

        do_action('hf_memberships_user_membership_created', $membership_plan, array(
            'user_id' => $args['user_id'],
            'user_membership_id' => $user_membership->get_id(),
            'is_update' => $updating,
        ));

        return $user_membership;
    }

    public function get_user_memberships($user_id = null, $args = array()) {

        $args = wp_parse_args($args, array(
            'status' => 'any',
                ));


        foreach ((array) $args['status'] as $index => $status) {

            if ('any' !== $status) {
                $args['status'][$index] = 'hf-' . $status;
            }
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        $posts_args = array(
            'author' => $user_id,
            'post_type' => 'hf_user_membership',
            'post_status' => $args['status'],
            'nopaging' => true,
        );

        $posts = get_posts($posts_args);
        
        $user_memberships = array();

        $usr_membership_obj = new XA_Woocommerce_User_Memberships();
        foreach ($posts as $post) {
            if ($user_membership = $usr_membership_obj->get_user_membership($post)) {
                $user_memberships[] = $user_membership;
            }
        }

        return $user_memberships;
    }

    public function get_user_membership($id = null, $plan = null) {


        if ($plan) {

            $user_id = !empty($id) ? (int) $id : get_current_user_id();
            $plans_obj = new Xa_Woocommerce_Membership_Plans( );
            $membership_plan = $plans_obj->hforce_get_membership_plan($plan);


            if (!$membership_plan || !$user_id || 0 === $user_id) {
                return false;
            }

            $args = array(
                'author' => $user_id,
                'post_type' => 'hf_user_membership',
                'post_parent' => $membership_plan->get_id(),
                'post_status' => 'any',
            );

            $user_memberships = get_posts($args);
            $post = !empty($user_memberships) ? $user_memberships[0] : null;
        } else {

            $post = $id;

            if (false === $post) {

                $post = $GLOBALS['post'];
            } elseif (is_numeric($post)) {

                $post = get_post($post);
            } elseif ($post instanceof XA_Woocommerce_User_Membership) {

                $post = get_post($post->get_id());
            } elseif (!$post instanceof WP_Post) {
                $post = null;
            }
        }
        
        if (!$post || 'hf_user_membership' !== get_post_type($post)) {
            return false;
        }

        $user_membership = new Xa_Woocommerce_User_Membership($post);
        

        return apply_filters('hf_memberships_user_membership', $user_membership, $post, $id, $plan);
    }

    public function get_user_membership_by_order_id($order) {

        if (is_numeric($order)) {
            $order_id = (int) $order;
        } elseif ($order instanceof WC_Order || $order instanceof WC_Order_Refund) {
            $order_id = (int) $order->get_id();
        } else {
            return null;
        }

        $user_memberships_query = new WP_Query(array(
            'fields' => 'ids',
            'nopaging' => true,
            'post_type' => 'hf_user_membership',
            'post_status' => 'any',
            'meta_key' => '_order_id',
            'meta_value' => $order_id,
                ));

        if (empty($user_memberships_query)) {
            return null;
        }

        $user_memberships_posts = $user_memberships_query->get_posts();
        $user_memberships = array();

        foreach ($user_memberships_posts as $post_id) {

            if ($user_membership = $this->get_user_membership($post_id)) {

                $user_memberships[] = $user_membership;
            }
        }

        return $user_memberships;
    }

    public function is_user_member($user_id = null, $membership_plan = null, $check_if_active = false, $cache = true) {

        $is_member = false;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        } elseif (isset($user_id->ID)) {
            $user_id = $user_id->ID;
        }


        if (!is_numeric($user_id) || 0 === $user_id) {
            return $is_member;
        } else {
            $user_id = (int) $user_id;
        }

        $plan_id = null;

        if (is_numeric($membership_plan)) {
            $plan_id = $membership_plan;
        } elseif ($membership_plan instanceof XA_Woocommerce_Membership_Plan) {
            $plan_id = $membership_plan->get_id();
        }

        $member_status_cache_key = null;


        if (true === $check_if_active) {
            $member_status_cache_key = 'is_active';
        } elseif (!$check_if_active) {
            $member_status_cache_key = 'is_member';
        } elseif (is_string($check_if_active)) {
            $member_status_cache_key = "is_{$check_if_active}";
        }

        if (false !== $cache && $member_status_cache_key && is_numeric($plan_id) && isset($this->is_user_member[$user_id][$plan_id][$member_status_cache_key])) {

            $is_member = $this->is_user_member[$user_id][$plan_id][$member_status_cache_key];
        } else {


            $must_be_active_member = in_array($check_if_active, array('active', 'delayed', true), true);

            if (null === $membership_plan) {


                $plan_obj = new Xa_Woocommerce_Membership_Plans();
                $plans = $plan_obj->get_membership_plans();

                if (!empty($plans)) {

                    
                    foreach ($plans as $plan) {

                        
                        if ($user_membership = $this->get_user_membership($user_id, $plan)) {

                            
                            $is_member = !$must_be_active_member;

                            if (true === $must_be_active_member) {

                                if ($is_member = ( $user_membership->is_active() && $user_membership->is_in_active_period() )) {

                                    break;
                                } elseif ('delayed' === $check_if_active && ( $is_member = $user_membership->is_delayed() )) {

                                    break;
                                }
                            } else {

                                break;
                            }
                        }
                    }
                }
            } else {

                $user_membership = $this->get_user_membership($user_id, $membership_plan);
                $is_member = (bool) $user_membership;

                if ($is_member && $must_be_active_member) {

                    $is_member = $user_membership->is_active() && $user_membership->is_in_active_period();

                    if ('delayed' === $check_if_active) {

                        $is_member = $user_membership->is_delayed();
                    }
                }
            }

            $this->is_user_member[$user_id][$plan_id][$member_status_cache_key] = $is_member;
        }

        return $is_member;
    }

    public function is_user_active_member($user_id = null, $membership_plan = null, $cache = true) {
        return $this->is_user_member($user_id, $membership_plan, 'active', $cache);
    }

    public function get_user_member_since_date($user_id, $format = 'timestamp') {

        if ($user_id instanceof WP_User) {
            $user_id = $user_id->ID;
        }

        if (!is_numeric($user_id)) {
            return null;
        }

        $user_memberships = $this->get_user_memberships($user_id);
        $member_since = null;

        foreach ($user_memberships as $user_membership) {

            if (!$member_since || $member_since > $user_membership->get_start_date('timestamp')) {

                $member_since = $user_membership->get_start_date('timestamp');
            }
        }

        return $member_since ? hforce_memberships_format_date($member_since, $format) : null;
    }

    public function get_user_member_since_local_date($user_id, $format = 'timestamp') {

        $date = $this->get_user_member_since_date($user_id, $format);

        return !empty($date) ? hforce_memberships_adjust_date_by_timezone($date, $format) : null;
    }

    public function get_active_access_membership_statuses() {

        $active_statuses = array(
            'active',
            'pending',
        );
        return apply_filters('hf_active_access_membership_statuses', $active_statuses);
    }

    public function get_valid_user_membership_statuses_for_cancellation() {

        return apply_filters('hf_memberships_valid_membership_statuses_for_cancel', array('active',));
    }

    public function adjust_user_membership_post_data($data) {

        if ('hf_user_membership' === $data['post_type']) {


            if (!$data['post_password']) {
                $data['post_password'] = uniqid('um_', false);
            }


            if (isset($_GET['user']) && 'auto-draft' === $data['post_status']) {
                $data['post_author'] = absint($_GET['user']);
            }
        }

        return $data;
    }

    public function save_user_membership($post_id, $post, $update) {

        $usr = new XA_Woocommerce_User_Memberships();
        if ('hf_user_membership' === get_post_type($post) && ( $user_membership = $usr->get_user_membership($post_id) )) {


            do_action('hf_memberships_user_membership_saved', $user_membership->get_plan(), array(
                'user_id' => $user_membership->get_user_id(),
                'user_membership_id' => $user_membership->get_id(),
                'is_update' => $update,
            ));
        }
    }

    public function delete_user_memberships($user_id) {

        $user_memberships = $this->get_user_memberships($user_id);

        foreach ($user_memberships as $membership) {
            wp_delete_post($membership->get_id());
        }
    }

    public function handle_order_trashed($order_id) {

        $this->handle_order_cancellation($order_id, __('Membership cancelled because the associated order was trashed.', 'xa-woocommerce-membership'));
    }

    public function handle_order_refunded($order_id) {

        $this->handle_order_cancellation($order_id, __('Membership cancelled because the associated order was refunded.', 'xa-woocommerce-membership'));
    }

    private function handle_order_cancellation($order_id, $note) {

        if ('shop_order' !== get_post_type($order_id)) {
            return;
        }

        if ($user_memberships = $this->get_user_membership_by_order_id($order_id)) {

            foreach ($user_memberships as $user_membership) {
                $user_membership->cancel_membership($note);
            }
        }
    }

    public function wt_get_user_membership_statuses() {

        $user_membership_statuses = array(
            'hf-active' => array(
                'label' => __('Active', 'xa-woocommerce-membership'),
                'label_count' => _n_noop('Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'xa-woocommerce-membership'),
            ),
            'hf-pending' => array(
                'label' => __('Pending Cancellation', 'xa-woocommerce-membership'),
                'label_count' => _n_noop('Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'xa-woocommerce-membership'),
            ),
            'hf-expired' => array(
                'label' => __('Expired', 'xa-woocommerce-membership'),
                'label_count' => _n_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'xa-woocommerce-membership'),
            ),
            'hf-cancelled' => array(
                'label' => __('Cancelled', 'xa-woocommerce-membership'),
                'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'xa-woocommerce-membership'),
            ),
        );

        return apply_filters('hforce_user_membership_statuses', $user_membership_statuses);
    }

}