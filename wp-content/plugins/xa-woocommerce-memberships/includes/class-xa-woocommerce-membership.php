<?php
if (!defined('WPINC'))
    exit;

class Xa_Woocommerce_Membership {

    protected $loader;
    protected $plugin_name;
    protected $version;
    
            // setting tab slug
    const PLUGIN_ID = 'hf_memberships';

    public function __construct() {
        if (defined('XA_WOO_MEMBERSHIP_VERSION')) {
            $this->version = XA_WOO_MEMBERSHIP_VERSION;
        } else {
            $this->version = '1.0.3';
        }
        $this->plugin_name = 'xa-woocommerce-membership';
        $this->plugin_base_name = XA_MEMBERSHIP_BASE_NAME;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {


        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-xa-woocommerce-membership-loader.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-xa-woocommerce-membership-i18n.php';


        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-xa-woocommerce-membership-plan.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-xa-woocommerce-membership-plans.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-xa-woocommerce-user-membership.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-xa-woocommerce-user-memberships.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-xa-woocommerce-membership-admin.php';


        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-xa-woocommerce-membership-public.php';

        $this->loader = new Xa_Woocommerce_Membership_Loader();
    }

    private function set_locale() {

        $plugin_i18n = new Xa_Woocommerce_Membership_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {

        $plugin_admin = new Xa_Woocommerce_Membership_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'register_access_metabox');
        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'membership_plan_metabox');

        $this->loader->add_action('save_post', $plugin_admin, 'save_access_metabox');

        $this->loader->add_filter('plugin_action_links_' . $this->get_plugin_base_name(), $plugin_admin, 'xa_wt_membership_action_links');

        $this->loader->add_action('init', $plugin_admin, 'init_hf_membership_post_types');
        $this->loader->add_action('init', $plugin_admin, 'init_hf_membership_user_roles');
        $this->loader->add_action('init', $plugin_admin, 'init_hf_user_membership');

        $this->loader->add_action('admin_head', $plugin_admin, 'hf_hide_from_menus');

        $this->loader->add_action('all_admin_notices', $plugin_admin, 'print_tab_html', 5);

        $this->loader->add_action('save_post', $plugin_admin, 'save_membership_plan_metabox', 5, 2);

        $this->loader->add_filter('manage_edit-hf_membership_plan_columns', $plugin_admin, 'render_membership_plan_columns');

        $this->loader->add_action('manage_hf_membership_plan_posts_custom_column', $plugin_admin, 'render_membership_plan_content', 10, 2);


        $this->loader->add_action('current_screen', $plugin_admin, 'init_member_metabox');
        $this->loader->add_action('save_post', $plugin_admin, 'save_user_membership_metabox', 10, 3);
        $this->loader->add_action('hforce_user_memberships_save_meta_box', $plugin_admin, 'update_user_membership_data', 10, 3);



        //add_action( 'hforce_user_memberships_save_meta_box', array( $this, 'update_user_membership_data' ), 10, 3 );

        $this->loader->add_filter('manage_edit-hf_user_membership_columns', $plugin_admin, 'hf_user_membership_columns');
        $this->loader->add_action('manage_hf_user_membership_posts_custom_column', $plugin_admin, 'hf_user_membership_column_content', 10, 2);
        $this->loader->add_filter('post_row_actions', $plugin_admin, 'hf_user_membership_row_actions', 10, 2);
        $this->loader->add_filter('the_title', $plugin_admin, 'hf_user_membership_title', 10, 2);

        $this->loader->add_filter('request', $plugin_admin, 'hforce_request_query');

        $this->loader->add_filter('woocommerce_screen_ids', $plugin_admin, 'load_wc_admin_css_scripts');
        
        
        $this->loader->add_filter( 'woocommerce_settings_tabs_array', $plugin_admin, 'add_memberships_settings_tab', 50);
        $this->loader->add_action( 'woocommerce_settings_tabs_hf_memberships', $plugin_admin, 'add_memberships_settings_page');
        $this->loader->add_action( 'woocommerce_update_options_' . self::PLUGIN_ID, $plugin_admin, 'save_memberships_settings');
        
        
    }

    private function define_public_hooks() {

        $plugin_public = new Xa_Woocommerce_Membership_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    public function get_plugin_base_name() {
        return $this->plugin_base_name;
    }

}


// move to admin class

add_action('save_post', 'hf_user_membership_save_post');

function hf_user_membership_save_post($post_id) {

    if (!isset($_POST['_hf_memberships_user_membership_data_nonce']) || !wp_verify_nonce($_POST['_hf_memberships_user_membership_data_nonce'], 'update-hf-memberships-user-membership-data')) {
        return;
    }

    // if this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // bail out if not a supported post type
//		if ( ! in_array( $post->post_type, array('hf_user_membership',), true ) ) {
//			return;
//		}
    // check the user's permissions.
    if (isset($_POST['post_type']) && 'page' === $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    if (!current_user_can('manage_woocommerce_hf_user_memberships')) {
        return;
    }
    //Check your nonce!
    $_post_status = ($_POST['_post_status']) ? $_POST['_post_status'] : 'hf-active';
    $_membership_user_id = isset($_POST['_membership_user_id']) ? $_POST['_membership_user_id'] : get_current_user_id();
    //echo $_post_status;exit;
    //If calling wp_update_post, unhook this function so it doesn't loop infinitely
    remove_action('save_post', 'hf_user_membership_save_post');
    //echo "hellofff;".$_membership_user_id;exit;
    // call wp_update_post update, which calls save_post again. E.g:


    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => $_post_status,
        'post_author' => $_membership_user_id,
    ));

    // re-hook this function
    add_action('save_post', 'hf_user_membership_save_post');
}