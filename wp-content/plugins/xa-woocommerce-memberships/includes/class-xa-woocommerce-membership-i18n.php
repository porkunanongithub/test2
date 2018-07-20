<?php

if (!defined('WPINC'))
    exit;

class Xa_Woocommerce_Membership_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain( 'xa-woocommerce-membership', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/' );
    }

}
// date functions - move to date util class
function hforce_memberships_parse_date($date, $format = 'mysql') {

    $parsed_date = false;
    $is_timestamp = 'timestamp' === $format;

    if ($is_timestamp && is_numeric($date)) {
        $parsed_date = (int) $date;
    } elseif (!$is_timestamp && is_string($date) && ( $time = strtotime($date) )) {
        $format = 'mysql' === $format ? 'Y-m-d H:i:s' : $format;
        $parsed_date = date($format, $time);
    }

    return $parsed_date;
}

function hforce_memberships_adjust_date_by_timezone($date, $format = 'mysql', $timezone = 'UTC') {

    if (is_numeric($date)) {
        $src_date = date('Y-m-d H:i:s', (int) $date);
    } else {
        $src_date = $date;
    }

    if ('mysql' === $format) {
        $format = 'Y-m-d H:i:s';
    }

    if ('UTC' === $timezone) {
        $from_timezone = 'UTC';
        $to_timezone = wc_timezone_string();
    } else {
        $from_timezone = $timezone;
        $to_timezone = 'UTC';
    }

    try {

        $from_date = new DateTime($src_date, new DateTimeZone($from_timezone));
        $to_date = new DateTimeZone($to_timezone);
        $offset = $to_date->getOffset($from_date);

        $timestamp = (int) $from_date->format('U');
    } catch (Exception $e) {

        trigger_error(sprintf('Failed to parse date "%1$s" to get timezone offset: %2$s.', $date, $e->getMessage()), E_USER_WARNING);

        $timestamp = is_numeric($date) ? (int) $date : strtotime($date);
        $offset = 0;
    }

    return 'timestamp' === $format ? $timestamp + $offset : date($format, $timestamp + $offset);
}

function hforce_memberships_get_order_access_granting_product_ids($plan, $order, $order_items = array()) {

    $access_granting_product_ids = array();

    if (empty($order_items)) {

        $order = is_numeric($order) ? wc_get_order((int) $order) : $order;

        if ($order instanceof WC_Order) {
            $order_items = $order->get_items();
        }
    }

    if (!empty($order_items)) {

        foreach ($order_items as $key => $item) {


            if ($plan->has_product($item['product_id'])) {
                $access_granting_product_ids[$item['product_id']] = max(1, (int) $item['qty']);
            }

            if (isset($item['variation_id']) && $item['variation_id'] && $plan->has_product($item['variation_id'])) {
                $access_granting_product_ids[$item['variation_id']] = max(1, (int) $item['qty']);
            }
        }

        if (!empty($access_granting_product_ids)) {

            reset($access_granting_product_ids);
            $product_ids = key($access_granting_product_ids);

            $access_granting_product_ids = (array) apply_filters('hf_memberships_access_granting_purchased_product_id', $product_ids, array_keys($access_granting_product_ids), $plan);
        }
    }

    return $access_granting_product_ids;
}

function hforce_memberships_set_order_access_granted_membership($order, $user_membership, $args = array()) {

    if (is_numeric($order)) {
        $order = wc_get_order((int) $order);
    }

    if ($order instanceof WC_Order) {

        if (is_numeric($user_membership)) {
            $usr = new XA_Woocommerce_User_Memberships();
            $user_membership = $usr->get_user_membership((int) $user_membership);
        }

        if ($user_membership instanceof XA_Woocommerce_User_Membership) {

            $user_membership_id = $user_membership->get_id();
            $meta = hf_get_order_access_granted_memberships($order);
            $details = wp_parse_args($args, array(
                'already_granted' => 'yes',
                'granting_order_status' => $order->get_status(),
            ));

            $meta[$user_membership_id] = $details;

            update_post_meta($order->get_id(), '_hf_memberships_access_granted', $meta);
        }
    }
}

function hf_get_order_access_granted_memberships($order) {

    $access_granted = get_post_meta($order->get_id(), '_hf_memberships_access_granted', true);
    return !is_array($access_granted) ? array() : $access_granted;
}

function hforce_memberships_has_order_granted_access($order, $args) {

    $has_granted = false;
    $access_granted_memberships = hf_get_order_access_granted_memberships($order);

    if (!empty($access_granted_memberships)) {

        if (isset($args['user_membership'])) {

            $user_membership = $args['user_membership'];

            if (is_numeric($user_membership)) {
                $usr = new XA_Woocommerce_User_Memberships();
                $user_membership = $usr->get_user_membership((int) $user_membership);
            }

            if ($user_membership instanceof XA_Woocommerce_User_Membership) {
                $has_granted = array_key_exists($user_membership->get_id(), $access_granted_memberships);
            }
        } elseif (isset($args['membership_plan'])) {

            $membership_plan = $args['membership_plan'];
            $membership_plan_id = null;

            if (is_numeric($membership_plan)) {
                $membership_plan_id = (int) $membership_plan;
            } elseif ($membership_plan instanceof XA_Woocommerce_Membership_Plan) {
                $membership_plan_id = $membership_plan->get_id();
            }

            if ($membership_plan_id && ( $user_membership_ids = array_keys($access_granted_memberships) )) {

                foreach ($user_membership_ids as $user_membership_id) {
                    $usr = new XA_Woocommerce_User_Memberships();
                    $user_membership = $usr->get_user_membership($user_membership_id);

                    if ($user_membership && $membership_plan_id === $user_membership->get_plan_id()) {
                        $has_granted = true;
                        break;
                    }
                }
            }
        }
    }

    return $has_granted;
}

function hforce_memberships_format_date($date, $format = 'mysql') {

    switch ($format) {
        case 'mysql':
            return is_numeric($date) ? date('Y-m-d H:i:s', $date) : $date;
        case 'timestamp':
            return is_numeric($date) ? (int) $date : strtotime($date);
        default:
            return date($format, is_numeric($date) ? (int) $date : strtotime($date));
    }
}

function hforce_memberships_parse_period_length($length, $return = '') {

    if (!is_string($length)) {
        return '';
    }

    $pieces = explode(' ', trim($length));
    $amount = isset($pieces[0], $pieces[1]) && is_numeric($pieces[0]) ? (int) $pieces[0] : '';
    $period = isset($pieces[0], $pieces[1]) && is_numeric($pieces[0]) ? $pieces[1] : '';

    if (!empty($amount) && !empty($period)) {

        $plans_obj = new Xa_Woocommerce_Membership_Plans();
        $periods = $plans_obj->get_membership_plans_access_length_periods();

        if (in_array($period, $periods, true)) {

            switch ($return) {
                case 'amount' :
                    return $amount;
                case 'period' :
                    return $period;
                default :
                    return $amount . ' ' . $period;
            }
        }
    }

    return '';
}

function hforce_memberships_add_months_to_timestamp($from_timestamp, $months_to_add) {

    if (!is_numeric($months_to_add) || (int) $months_to_add <= 0) {
        return $from_timestamp;
    }

    $first_day_of_month = date('Y-m', $from_timestamp) . '-1';
    $days_in_next_month = date('t', strtotime("+ {$months_to_add} month", strtotime($first_day_of_month)));
    $next_timestamp = 0;

    if (date('d', $from_timestamp) > $days_in_next_month || date('d m Y', $from_timestamp) === date('t m Y', $from_timestamp)) {

        for ($i = 1; $i <= $months_to_add; $i++) {

            $next_month = strtotime('+ 3 days', $from_timestamp);
            $next_timestamp = $from_timestamp = strtotime(date('Y-m-t H:i:s', $next_month));
        }
    } else {

        $next_timestamp = strtotime("+ {$months_to_add} month", $from_timestamp);
    }

    return $next_timestamp;
}