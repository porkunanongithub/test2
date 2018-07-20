<?php

if (!defined('WPINC'))
    exit;

class Xa_Woocommerce_User_Membership {

    public $id;
    public $plan_id;
    public $plan;
    public $user_id;
    public $status;
    public $post;
    private $product;
    protected $type = '';

    public function __construct($id, $user_id = null) {

        if (!$id) {
            return;
        }

        if (is_numeric($id)) {
            $this->post = get_post($id);
        } elseif (is_object($id)) {
            $this->post = $id;
        }

        if ($this->post) {
            //var_dump($this->membership_user_id);

            $this->id = $this->post->ID;
            $this->user_id = ($this->get_member_user_id($this->id)) ? $this->get_member_user_id($this->id) : $this->post->post_author;
            $this->plan_id = $this->post->post_parent;
            if (!$this->post->post_status) {
                $this->status = 'hf-active';
            }
            $this->status = $this->post->post_status;
        } elseif ($user_id) {

            $this->user_id = $user_id;
        }
        if ($user_id) {


            $this->user_id = $user_id;
        }

        // set meta keys
        $this->membership_user_id = '_membership_user_id';
        $this->start_date_meta = '_start_date';
        $this->end_date_meta = '_end_date';
        $this->cancelled_date_meta = '_cancelled_date';
        $this->product_id_meta = '_product_id';
        $this->order_id_meta = '_order_id';

        $this->type = $this->get_type();
    }

    // All member meta data

    protected $membership_user_id = '_membership_user_id';
    protected $product_id_meta = '_product_id';
    protected $start_date_meta = '';
    protected $end_date_meta = '';
    protected $cancelled_date_meta = '';
    protected $order_id_meta = '';

    public function get_status() {
        //return 0 === strpos($this->status, 'hf-') ? substr($this->status, 3) : $this->status;
        return $this->status;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_user_id() {
        return $this->user_id;
    }

    public function get_user() {

        $user = $this->user_id > 0 ? get_user_by('id', $this->user_id) : null;

        return !empty($user) ? $user : null;
    }

    public function get_plan() {

        if (!$this->plan) {

            $plans_obj = new Xa_Woocommerce_Membership_Plans();
            $this->plan = $plan = $plans_obj->get_membership_plan($this->plan_id, $this);
        } else {

            $plan = $this->plan;
            $post = !empty($this->plan) ? $plan->post : null;

            $plan = apply_filters('hf_memberships_membership_plan', $plan, $post, $this);
        }

        return $plan;
    }

    public function get_plan_id() {
        return $this->plan_id;
    }

    public function get_type() {

        $type = 'manually-assigned';
        $plan = $this->get_plan();

        if ($plan) {

            $access_method = $plan->get_access_method();

            if ('signup' === $access_method) {
                $type = 'free';
            } elseif ('purchase' === $access_method) {

                $type = $this->get_order_id() && $this->get_product_id() ? 'purchased' : $type;
            }
        }

        $this->type = apply_filters('hf_memberships_user_membership_type', $type, $this);

        return $this->type;
    }

    public function is_type($type) {
        return is_array($type) ? in_array($this->get_type(), $type, true) : $type === $this->get_type();
    }

    public function set_start_date($date) {

        $start_date = hforce_memberships_parse_date($date, 'mysql');

        if (!$start_date) {
            $start_date = date('Y-m-d H:i:s', current_time('timestamp', true));
        }

        update_post_meta($this->id, $this->start_date_meta, $start_date);

        if ('delayed' !== $this->get_status() && strtotime('today', strtotime($start_date)) > current_time('timestamp', true)) {

            $this->update_status('delayed');
        }
    }

    public function set_member_user_id($id) {
        update_post_meta($this->id, $this->membership_user_id, $id);
    }

    public function get_member_user_id($id) {

        $membership_user_id = get_post_meta($this->id, $this->membership_user_id, true);
        return $membership_user_id;
    }

    public function get_start_date($format = 'mysql') {

        $date = get_post_meta($this->id, $this->start_date_meta, true);
        return !empty($date) ? hforce_memberships_format_date($date, $format) : null;
    }

    public function has_start_date() {
        return is_numeric($this->get_start_date('timestamp'));
    }

    public function get_local_start_date($format = 'mysql') {
        
        $date = $this->get_start_date('timestamp');
        if (!empty($date)) {
            return hforce_memberships_adjust_date_by_timezone($date, $format);
        }
        return null;
    }

    public function set_end_date($date = '') {

        $end_timestamp = '';
        $end_date = '';

        if (is_numeric($date)) {
            $end_timestamp = (int) $date;
        } elseif (is_string($date)) {
            $end_timestamp = strtotime($date);
        }

        if (!empty($end_timestamp)) {

            $end_timestamp = $this->get_plan() && $this->plan->is_access_length_type('fixed') ? hforce_memberships_adjust_date_by_timezone(strtotime('midnight', $end_timestamp), 'timestamp', wc_timezone_string()) : $end_timestamp;
            $end_date = date('Y-m-d H:i:s', (int) $end_timestamp);
        }

        update_post_meta($this->id, $this->end_date_meta, $end_date);
    }

    public function get_end_date($format = 'mysql', $include_paused = true) {

        $date = get_post_meta($this->id, $this->end_date_meta, true);
        return !empty($date) ? hforce_memberships_format_date($date, $format) : null;
    }

    public function get_local_end_date($format = 'mysql', $include_paused = true) {

        $date = $this->get_end_date('timestamp', $include_paused);
        if (!empty($date)) {
            return hforce_memberships_adjust_date_by_timezone($date, $format);
        }
        return null;
    }

    public function has_end_date() {
        return is_numeric($this->get_end_date('timestamp', false));
    }

    public function get_cancelled_date($format = 'mysql') {

        $date = get_post_meta($this->id, $this->cancelled_date_meta, true);
        if (!empty($date)) {
            return hforce_memberships_format_date($date, $format);
        } return null;
    }

    public function get_local_cancelled_date($format = 'mysql') {

        $date = $this->get_cancelled_date('timestamp');

        if (!empty($date)) {
            return hforce_memberships_adjust_date_by_timezone($date, $format);
        }
        return null;
    }

    public function set_cancelled_date($date) {

        if ($cancelled_date = hforce_memberships_parse_date($date, 'mysql')) {
            update_post_meta($this->id, $this->cancelled_date_meta, $cancelled_date);
        }
    }

    private function get_total_time($type, $format = 'timestamp') {

        $total = null;
        $time = 0;
        $start = $this->get_start_date('timestamp');

        if ('active' === $type) {

            if ($this->is_expired()) {
                $time = $this->get_end_date('timestamp');
            } elseif ($this->is_cancelled()) {
                $time = $this->get_cancelled_date('timestamp');
            }

            if (empty($total)) {
                $time = current_time('timestamp', true);
            }
        }

        if ('active' === $type) {
            $total = max(0, $time - $start);
        } elseif ('inactive' === $type) {
            $total = max(0, $time);
        }

        if ('human' === $format && is_int($total)) {

            $time_diff = max($start, $start + $total);
            $total = $time_diff !== $start && $time_diff > 0 ? human_time_diff($start, $time_diff) : 0;
        }

        return $total;
    }

    public function get_total_active_time($format = 'timestamp') {
        return $this->get_total_time('active', $format);
    }

    public function get_total_inactive_time($format = 'timestamp') {
        return $this->get_total_time('inactive', $format);
    }

    public function set_order_id($order_id) {

        $order_id = is_numeric($order_id) ? (int) $order_id : 0;

        if ($order = $order_id > 0 ? wc_get_order($order_id) : null) {

            update_post_meta($this->id, $this->order_id_meta, $order_id);


            if (!hforce_memberships_has_order_granted_access($order, array('user_membership' => $this))) {

                hforce_memberships_set_order_access_granted_membership($order, $this, array(
                    'already_granted' => 'yes',
                    'granting_order_status' => $order->get_status(),
                ));
            }
        }
    }

    public function get_order_id() {

        $order_id = get_post_meta($this->id, $this->order_id_meta, true);
        return $order_id ? (int) $order_id : null;
    }

    public function get_order() {

        $order_id = $this->get_order_id();
        return $order_id ? wc_get_order($order_id) : null;
    }

    public function delete_order_id() {
        delete_post_meta($this->id, $this->order_id_meta);
    }

    public function set_product_id($product_id) {

        if (is_array($product_id)) {
            update_post_meta($this->id, $this->product_id_meta, $product_id);
            return true;
        }
        $product_id = is_numeric($product_id) ? (int) $product_id : 0;

        if ($product_id > 0 && wc_get_product($product_id)) {
            update_post_meta($this->id, $this->product_id_meta, $product_id);
            unset($this->product);
        }
    }

    public function get_product_ids_for_restriction($id) {

        $product_id = get_post_meta($id, $this->product_id_meta, true);
        return $product_id;
    }

    public function get_product_id($get_variation_id = false) {


        $product_id = get_post_meta($this->plan->id, $this->product_id_meta, true);

        if ($get_variation_id && $product_id > 0) {

            $product = wc_get_product($product_id);
            $order = $this->get_order();

            if ($order && $product && $product->is_type('variable')) {

                foreach ($order->get_items() as $item) {

                    if (!empty($item['variation_id']) && $item['variation_id'] > 0) {

                        $variation_product = wc_get_product($item['variation_id']);

                        if ($variation_product && $variation_product->is_type('variation')) {

                            $parent = wc_get_product($variation_product->get_parent_id());
                            $parent_id = $parent ? $parent->get_id() : null;

                            if ($product_id && $parent_id === (int) $product_id) {

                                $product_id = $variation_product->get_id();
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $product_id ? (int) $product_id : null;
    }

    public function get_product($get_variation = false) {

        $product = null;
        $product_id = $this->get_product_id($get_variation);

        if ($get_variation) {
            $product = wc_get_product($product_id);
        } elseif (!isset($this->product)) {
            $this->product = $product_id ? wc_get_product($product_id) : null;
        }

        return $get_variation ? $product : $this->product;
    }

    public function delete_product_id() {

        delete_post_meta($this->id, $this->product_id_meta);
        unset($this->product);
    }

    public function has_status($status) {

        $has_status = ( ( is_array($status) && in_array($this->get_status(), $status, true) ) || $this->get_status() === $status );
        return (bool) apply_filters('woocommerce_memberships_membership_has_status', $has_status, $this, $status);
    }

    public function update_status($new_status, $note = '') {

        if (!$this->id) {
            return;
        }

        $new_status = 0 === strpos($new_status, 'hf-') ? substr($new_status, 4) : $new_status;
        $old_status = $this->get_status();

        $usr = new XA_Woocommerce_User_Memberships();
        $valid_statuses = $usr->wt_get_user_membership_statuses();


        if ($new_status !== $old_status && array_key_exists('hf-' . $new_status, $valid_statuses)) {

            remove_action('save_post', 'hf_user_membership_save_post');
            // update the order
            wp_update_post(array(
                'ID' => $this->id,
                'post_status' => 'hf-' . $new_status,
            ));
            add_action('save_post', 'hf_user_membership_save_post');
            $this->status = 'hf-' . $new_status;
        }
    }

    public function is_cancelled() {
        return 'cancelled' === $this->get_status();
    }

    public function is_expired() {
        return 'expired' === $this->get_status();
    }

    public function is_active() {

        $current_status = $this->get_status();
        $active_period = $this->is_in_active_period();
        $usr_memberships = new XA_Woocommerce_User_Memberships();
        $is_active = in_array($current_status, $usr_memberships->get_active_access_membership_statuses(), true);

        if ($is_active && !$active_period) {

            if ($this->get_start_date('timestamp') < current_time('timestamp', true)) {

                $this->expire_membership();
            }

            $is_active = false;
        } elseif ($active_period) {

            if ('expired' === $current_status) {

                $this->set_end_date(current_time('mysql', true));
                $is_active = false;
            }
        }

        return $is_active;
    }

    public function is_in_active_period() {

        $start = $this->get_start_date('timestamp');
        $now = current_time('timestamp', true);
        $end = $this->get_end_date('timestamp');
        return ( $start ? $start <= $now : true ) && ( $end ? $now <= $end : true );
    }

    public function cancel_membership($note = null) {

        if ($this->is_cancelled()) {
            return;
        }

        $this->update_status('cancelled', !empty($note) ? $note : __('Membership cancelled.', 'xa-woocommerce-membership') );
        $this->set_cancelled_date(current_time('mysql', true));
        do_action('hf_user_membership_cancelled', $this);
    }

    public function expire_membership() {


        if ($this->is_expired()) {
            return;
        }

        if (true === apply_filters('hf_memberships_expire_user_membership', true, $this)) {

            $current_time = current_time('timestamp', true);

            $this->update_status('expired', __('Membership expired.', 'xa-woocommerce-membership'));
            update_post_meta($this->id, $this->end_date_meta, date('Y-m-d H:i:s', $current_time));

            do_action('hf_memberships_user_membership_expired', $this->id);
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

    public function hf_update_user_membership_data($post_id, $posted_data, $user_membership_obj) {



        $timezone = wc_timezone_string();
        $date_format = 'Y-m-d H:i:s';
        $user_membership = $user_membership_obj;
        
        if (!empty($_POST['_start_date']) && ( $start_date_mysql = hforce_memberships_parse_date($_POST['_start_date'], 'mysql') )) {

            $start_date = date($date_format, hforce_memberships_adjust_date_by_timezone(strtotime($start_date_mysql), 'timestamp', $timezone));
            
            $user_membership->set_start_date($start_date);
        }

        // get the end date
        if (!empty($_POST['_end_date']) && ( $end_date_mysql = hforce_memberships_parse_date($_POST['_end_date'], 'mysql') )) {
            $end_date = date($date_format, hforce_memberships_adjust_date_by_timezone(strtotime($end_date_mysql), 'timestamp', $timezone));
        } else {
            $end_date = '';
        }

        $previous_end_date = $user_membership->get_end_date($date_format);

        if (!empty($end_date) && strtotime($end_date) <= current_time('timestamp', true)) {

            if ($previous_end_date != $end_date) {

                $user_membership->update_status('expired');
            } elseif (in_array($user_membership->get_status(), array('active',), true)) {

                $end_date = '';
            }
        } elseif ('expired' === $user_membership->get_status() && ( '' === $end_date || strtotime($end_date) > current_time('timestamp') )) {

            $user_membership->update_status('active');
        }

        $user_membership->set_end_date($end_date);
    }

}