<?php

if (!defined('WPINC'))
    exit;

class XA_Woocommerce_Membership_Plan {

    public $id;
    public $name;
    public $slug;
    public $post;
    protected $access_method_meta = '';
    protected $default_access_method = '';
    protected $access_length_meta = '';
    protected $access_start_date_meta = '';
    protected $access_end_date_meta = '';
    protected $product_ids_meta = '';

    public function __construct($id) {

        if (!$id) {
            return;
        }

        if (is_numeric($id)) {

            $post = get_post($id);

            if (!$post) {
                return;
            }

            $this->post = $post;
        } elseif (is_object($id)) {

            $this->post = $id;
        }

        if ($this->post) {

            $this->id = $this->post->ID;
            $this->name = $this->post->post_title;
            $this->slug = $this->post->post_name;
        }

        $this->access_method_meta = '_access_method';
        $this->access_length_meta = '_access_length';
        $this->access_start_date_meta = '_access_start_date';
        $this->access_end_date_meta = '_access_end_date';
        $this->product_ids_meta = '_product_ids';
        $this->default_access_method = 'unlimited';
    }

    public function get_id() {
        return $this->id;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_slug() {
        return $this->slug;
    }

    public function get_product_ids() {

        $product_ids = get_post_meta($this->id, $this->product_ids_meta, true);
        return !empty($product_ids) ? (array) $product_ids : array();
    }

    public function get_products($exclude_subscriptions = false) {

        $products = array();

        if ($this->has_products()) {

            foreach ($this->get_product_ids() as $product_id) {


                if ($product = wc_get_product($product_id)) {

                    if (true === $exclude_subscriptions) {


                        if (is_callable('WC_Subscriptions_Product::is_subscription')) {
                            $is_subscription = WC_Subscriptions_Product::is_subscription($product);
                        } else {
                            $is_subscription = $product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'));
                        }

                        if ($is_subscription) {
                            continue;
                        }
                    }

                    $products[$product_id] = $product;
                }
            }
        }

        return $products;
    }

    public function set_product_ids($product_ids, $merge = false) {

        if (is_string($product_ids)) {
            $product_ids = explode(',', $product_ids);
        }

        $product_ids = array_map('intval', (array) $product_ids);


        foreach ($product_ids as $index => $product_id) {

            if ($product_id <= 0 || !wc_get_product($product_id)) {

                unset($product_ids[$index]);
            }
        }

        if (true === $merge) {
            $product_ids = array_merge($this->get_product_ids(), $product_ids);
        }

        update_post_meta($this->id, $this->product_ids_meta, array_unique($product_ids));
    }

    public function delete_product_ids($product_ids = null) {

        if (empty($product_ids)) {

            delete_post_meta($this->id, $this->product_ids_meta);
        } else {

            if (is_numeric($product_ids)) {
                $product_ids = (array) $product_ids;
            }
            $remove_ids = array_map('intval', $product_ids);
            $existing_ids = $this->get_product_ids();

            update_post_meta($this->id, $this->product_ids_meta, array_diff($existing_ids, $remove_ids));
        }
    }

    public function has_products() {

        $product_ids = $this->get_product_ids();
        return !empty($product_ids);
    }

    public function has_product($product_id) {
        return is_numeric($product_id) ? in_array((int) $product_id, $this->get_product_ids(), true) : false;
    }

    private function validate_access_method($method) {

        $valid_access_methods = array('purchase');

        return in_array($method, $valid_access_methods, true) ? $method : 'manual-only';
    }

    public function set_access_method($method) {

        update_post_meta($this->id, $this->access_method_meta, $this->validate_access_method($method));
    }

    public function get_access_method() {

        $grant_access_type = get_post_meta($this->id, $this->access_method_meta, true);


        if (empty($grant_access_type)) {

            $product_ids = $this->get_product_ids();

            if (!empty($product_ids)) {
                $grant_access_type = 'purchase';
            }
        }

        return $this->validate_access_method($grant_access_type);
    }

    public function delete_access_method() {

        delete_post_meta($this->id, $this->access_method_meta);
    }

    public function is_access_method($method) {
        return is_array($method) ? in_array($this->get_access_method(), $method, true) : $method === $this->get_access_method();
    }

    public function set_access_length($access_length) {

        $access_length = (string) hforce_memberships_parse_period_length($access_length);

        if (!empty($access_length)) {

            update_post_meta($this->id, $this->access_length_meta, $access_length);
        }
    }

    public function get_access_length_amount() {
        return hforce_memberships_parse_period_length($this->get_access_length(), 'amount');
    }

    public function get_access_length_period() {
        return hforce_memberships_parse_period_length($this->get_access_length(), 'period');
    }

    public function has_access_length() {

        $period = $this->get_access_length_period();
        $amount = $this->get_access_length_amount();

        return is_int($amount) && !empty($period);
    }

    public function get_access_length() {


        $access_length = get_post_meta($this->id, $this->access_length_meta, true);

        if ($access_end = hforce_memberships_parse_date($this->get_access_end_date_meta(), 'mysql')) {

            $start_time = $this->get_access_start_date('timestamp');
            $end_time = strtotime($access_end);
            $access_days = ( $end_time - $start_time ) / DAY_IN_SECONDS;
            $access_length = sprintf('%d days', max(1, (int) $access_days));
        }

        return !empty($access_length) ? $access_length : '';
    }

    public function delete_access_length() {

        delete_post_meta($this->id, $this->access_length_meta);
    }

    public function get_access_length_type() {

        $access_length = $this->default_access_method;
        $access_end = $this->get_access_end_date_meta();

        if (!empty($access_end)) {
            $access_length = 'fixed';
        } elseif ($this->has_access_length()) {
            $access_length = 'specific';
        }

        return $access_length;
    }

    public function is_access_length_type($type) {
        return is_array($type) ? in_array($this->get_access_length_type(), $type, true) : $type === $this->get_access_length_type();
    }

    public function set_access_start_date($date = null) {

        if ($start_date = hforce_memberships_parse_date($date, 'mysql')) {

            update_post_meta($this->id, $this->access_start_date_meta, $start_date);
        }
    }

    public function get_access_start_date($format = 'mysql') {

        if ($this->is_access_length_type('fixed')) {
            $start_date = $this->validate_access_start_date(get_post_meta($this->id, $this->access_start_date_meta, true));
        }

        if (empty($start_date)) {
            $start_date = strtotime('today', current_time('timestamp', true));
        }

        return hforce_memberships_format_date($start_date, $format);
    }

    private function validate_access_start_date($access_start_date) {

        $start_date = hforce_memberships_parse_date($access_start_date, 'mysql');

        if ($start_date && ( $end_date = hforce_memberships_parse_date($this->get_access_end_date_meta(), 'mysql') )) {

            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date);

            if ($start_time >= $end_time) {

                $start_date = date('Y-m-d H:i:s', strtotime('yesterday', $end_time));
                $end_date = date('Y-m-d H:i:s', strtotime('tomorrow', $end_time));

                $this->set_access_start_date($start_date);
                $this->set_access_end_date($end_date);
            }
        }

        return $start_date;
    }

    public function get_local_access_start_date($format = 'mysql') {

        $date = $this->get_access_start_date('timestamp');

        return hforce_memberships_adjust_date_by_timezone($date, $format);
    }

    public function delete_access_start_date() {

        delete_post_meta($this->id, $this->access_start_date_meta);
    }

    public function set_access_end_date($date) {

        if ($end_date = hforce_memberships_parse_date($date, 'mysql')) {

            update_post_meta($this->id, $this->access_end_date_meta, $end_date);
        }
    }

    public function get_access_end_date($format = 'mysql', $args = array()) {

        $end_date = get_post_meta($this->id, $this->access_end_date_meta, true);
        $end_date = empty($end_date) ? $this->get_expiration_date(current_time('timestamp', true), $args) : $end_date;

        return !empty($end_date) ? hforce_memberships_format_date($end_date, $format) : '';
    }

    public function get_local_access_end_date($format = 'mysql') {

        $access_end_date = $this->get_access_end_date($format);

        return !empty($access_end_date) ? hforce_memberships_adjust_date_by_timezone($access_end_date, $format) : '';
    }

    protected function get_access_end_date_meta() {

        $access_end_date = get_post_meta($this->id, $this->access_end_date_meta, true);
        return !empty($access_end_date) ? $access_end_date : null;
    }

    public function delete_access_end_date() {

        delete_post_meta($this->id, $this->access_end_date_meta);
    }

    public function get_expiration_date($start = '', $args = array()) {

        $end = '';
        $end_date = '';

        $args = wp_parse_args($args, array(
            'plan_id' => $this->id,
            'start' => $start,
        ));
        if (!$this->is_access_length_type('unlimited')) {

            $access_length = $this->get_access_length();

            if ($this->is_access_length_type('fixed')) {
                $start = $this->get_access_start_date('timestamp');
            } elseif (empty($start)) {
                if (!empty($args['start'])) {
                    $start = is_numeric($args['start']) ? (int) $args['start'] : strtotime($args['start']);
                } else {
                    $start = current_time('timestamp', true);
                }
            } elseif (is_string($start) && !is_numeric($start)) {
                $start = strtotime($start);
            } else {
                $start = is_numeric($start) ? (int) $start : current_time('timestamp', true);
            }


            if (self::str_ends_with($access_length, 'months')) {
                $end = hforce_memberships_add_months_to_timestamp((int) $start, $this->get_access_length_amount());
            } else {
                $end = strtotime('+ ' . $access_length, (int) $start);
            }

            if (isset($args['format']) && 'timestamp' === $args['format']) {
                $end_date = $end;
            } else {
                $end_date = date('Y-m-d H:i:s', $end);
            }
        }


        return apply_filters('hf_memberships_plan_expiration_date', $end_date, $end, $args);
    }

    public static function str_ends_with($haystack, $needle) {

        if ('' === $needle) {
            return true;
        }

        if (extension_loaded('mbstring')) {

            return mb_substr($haystack, -mb_strlen($needle, 'UTF-8'), null, 'UTF-8') === $needle;
        } else {

            $haystack = self::str_to_ascii($haystack);
            $needle = self::str_to_ascii($needle);

            return substr($haystack, -strlen($needle)) === $needle;
        }
    }

    public static function str_to_ascii($string) {

        $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        return filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    }

    public function get_memberships($args = array()) {

        $args = wp_parse_args($args, array(
            'post_type' => 'hf_user_membership',
            'post_status' => 'any',
            'post_parent' => $this->id,
            'nopaging' => true,
        ));

        $posts = get_posts($args);

        $user_memberships = array();

        if (!empty($posts)) {

            $usr_mem_obj = new Xa_Woocommerce_User_Memberships;
            foreach ($posts as $post) {

                $user_memberships[] = $usr_mem_obj->get_user_membership($post);
            }
        }

        return $user_memberships;
    }

    public function get_memberships_count($status = 'any') {

        $membership_plans = new XA_Woocommerce_User_Memberships();
        $default_statuses = array_keys($membership_plans->wt_get_user_membership_statuses());

        if ('any' === $status) {
            $status = $default_statuses;
        }

        $statuses = (array) $status;
        $post_status = array();
        $members = array();

        if (!empty($statuses)) {

            foreach ($statuses as $status_key) {

                if (strpos($status_key, 'hf') !== FALSE) {
                    $status_key = $status_key;
                } else {
                    'hf-' . $status_key;
                }

                if (in_array($status_key, $default_statuses, true)) {
                    $post_status[] = $status_key;
                }
            }
        }

        if (!empty($post_status)) {

            $members = get_posts(array(
                'post_type' => 'hf_user_membership',
                'post_status' => $post_status,
                'post_parent' => $this->id,
                'fields' => 'ids',
                'nopaging' => true,
                    ))
            ;
        }

        return is_array($members) ? count($members) : 0;
    }

    public function has_active_memberships() {
        return $this->get_memberships_count('active') > 0;
    }

    public function grant_access_from_purchase($user_id, $product_id, $order_id) {

        $user_membership_id = null;
        $action = 'create';
        $product = is_numeric($product_id) ? wc_get_product($product_id) : $product_id;
        $order = is_numeric($order_id) ? wc_get_order($order_id) : $order_id;

        // sanity check
        if (!$product instanceof WC_Product || !$order instanceof WC_Order || !get_user_by('id', $user_id)) {
            return null;
        }

        $product_id = $product->get_id();
        $order_status = $order->get_status();

        $access_granted = hf_get_order_access_granted_memberships($order);

        $usr = new XA_Woocommerce_User_Memberships();

        if ($usr->is_user_member($user_id, $this->id, false)) {

            $existing_membership = $usr->get_user_membership($user_id, $this->id);
            $user_membership_id = $existing_membership->get_id();
            $past_order_id = $existing_membership->get_order_id();

            // Do not allow the same order to renew or reactivate the membership:
            // this prevents admins changing order statuses from extending/reactivating the membership.
            if (!empty($past_order_id) && (int) $order_id === $past_order_id) {
                $cumulative_access = get_option('hf_memberships_allow_cumulative_access_granting_orders');

                if ('yes' === $cumulative_access) {

                    if (isset($access_granted[$user_membership_id]) && $access_granted[$user_membership_id]['granting_order_status'] !== $order_status) {

                        // bail if this is an order status change and not a cumulative purchase
                        if ('yes' === $access_granted[$user_membership_id]['already_granted']) {

                            return null;
                        }
                    }
                } else {

                    return null;
                }
            }

            // otherwise... continue as usual
            $action = 'renew';

            if ($existing_membership->is_active() || $existing_membership->is_delayed()) {

                $renew_membership = apply_filters('hf_memberships_renew_membership', (bool) $this->get_access_length_amount(), $this, array(
                    'user_id' => $user_id,
                    'product_id' => $product_id,
                    'order_id' => $order_id,
                ));

                if (!$renew_membership) {
                    return null;
                }
            }
        }

        // create/update the user membership
        try {
            $user_membership = $usr->create_user_membership(array(
                'user_membership_id' => $user_membership_id,
                'user_id' => $user_id,
                'product_id' => $product_id,
                'order_id' => $order_id,
                'plan_id' => $this->id,
                    ), $action);
        } catch (Exception $e) {
            return null;
        }

        if (!isset($access_granted[$user_membership->get_id()])) {

            hforce_memberships_set_order_access_granted_membership($order, $user_membership, array(
                'already_granted' => 'yes',
                'granting_order_status' => $order_status,
            ));
        }


        do_action('hf_memberships_grant_membership_access_from_purchase', $this, array(
            'user_id' => $user_id,
            'product_id' => $product_id,
            'order_id' => $order_id,
            'user_membership_id' => $user_membership->get_id(),
        ));

        return $user_membership->get_id();
    }

    public function list_products_granting_access($membership_plan) {

        $product_ids = $membership_plan->get_product_ids();

        if (!empty($product_ids)) {

            echo '<ul class="access-from-list">';

            foreach ($product_ids as $product_id) {

                if ($product = wc_get_product($product_id)) {

                    printf('<li>%1$s</li>', $this->get_product_edit_link($product));
                }
            }

            echo '</ul>';
        }
    }

    public function get_product_edit_link($product) {

        $product_link = '';

        if ($product instanceof WC_Product) {

            $product_name = sprintf('%1$s (#%2$s)', $product->get_name(), $product->get_id());

            if ($product->is_type('variation')) {
                $product_link = get_edit_post_link(wc_get_product($product->get_parent_id()));
            } else {
                $product_link = get_edit_post_link($product->get_id());
            }

            $product_link = sprintf('<a href="%1$s">%2$s</a>', $product_link, $product_name);
        }

        return $product_link;
    }

}