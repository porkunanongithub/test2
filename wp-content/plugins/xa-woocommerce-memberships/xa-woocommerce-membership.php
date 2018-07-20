<?php
/*
 * Plugin Name:       Memberships for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/xa-woocommerce-memberships/
 * Description:       This allows to restrict content according to the mebmership plan user belongs..
 * Author:            WebToffee
 * Author URI:        https://www.webtoffee.com/
 * Text Domain:       xa-woocommerce-membership
 * Version:           1.0.3
 * WC tested up to:   3.4.3
 * Domain Path:       /languages
 * License:           GPLv3
 * 
 */

/*
 Memberships for WooCommerce is inspired from WooCommerce Memberships by Skyverge
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('XA_WOO_MEMBERSHIP_VERSION', '1.0.3');

if (!defined('XA_MEMBERSHIP_BASE_NAME')) {
    define('XA_MEMBERSHIP_BASE_NAME', plugin_basename(__FILE__));
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-xa-woocommerce-membership-activator.php
 */
function activate_xa_woocommerce_membership() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-xa-woocommerce-membership-activator.php';
    Xa_Woocommerce_Membership_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-xa-woocommerce-membership-deactivator.php
 */
function deactivate_xa_woocommerce_membership() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-xa-woocommerce-membership-deactivator.php';
    Xa_Woocommerce_Membership_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_xa_woocommerce_membership');
register_deactivation_hook(__FILE__, 'deactivate_xa_woocommerce_membership');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-xa-woocommerce-membership.php';
require plugin_dir_path(__FILE__) . 'includes/class-hf-membership-uninstall-feedback.php';

if (!defined('HFORCE_MEMBERSHIP_MAIN_PATH')) {
    define('HFORCE_MEMBERSHIP_MAIN_PATH', plugin_dir_path(__FILE__));
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_xa_woocommerce_membership() {

    $plugin = new Xa_Woocommerce_Membership();
    $plugin->run();
}

run_xa_woocommerce_membership();