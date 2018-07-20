<?php
if (!defined('WPINC'))
    exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wordpress.org/plugins/xa-woocommerce-subscriptions
 * @since      1.0.0
 *
 * @package    Xa_Woocommerce_Membership
 * @subpackage Xa_Woocommerce_Membership/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Xa_Woocommerce_Membership
 * @subpackage Xa_Woocommerce_Membership/admin
 * @author     Mark <mark@hikeforce.com>
 */
class Xa_Woocommerce_Membership_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;
    public $managed_post_types = array('page', 'post', 'product');
    public static $option_prefix = 'hf_memberships';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

        if (!is_admin()) {
            add_filter('the_posts', array($this, 'filter_posts'));
            add_filter('get_pages', array($this, 'filter_posts'));
        }
        add_filter('display_post_states', '__return_false');
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Xa_Woocommerce_Membership_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Xa_Woocommerce_Membership_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/xa-woocommerce-membership-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Xa_Woocommerce_Membership_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Xa_Woocommerce_Membership_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/xa-woocommerce-membership-admin.js', array('jquery', 'jquery-ui-datepicker',), $this->version, false);
    }

    public function register_access_metabox($post_type) {

        if (in_array($post_type, $this->managed_post_types)) {
            add_meta_box('hforce-restrict-access-metabox', __('Access allowed', 'xa-woocommerce-membership'), array($this, 'render_restrict_access_metabox'), null, 'side', 'high');
        }
    }

    public function render_restrict_access_metabox($post) {


        $membership_plans_obj = new Xa_Woocommerce_Membership_Plans();
        $membership_plans = $membership_plans_obj->get_membership_plans();
        $content_access_restriction = get_post_meta($post->ID, '_hforce_content_access_restriction', true);
        $content_access_restriction = ($content_access_restriction) ? maybe_unserialize($content_access_restriction) : array();
        $params = array('post' => $post, 'content_access_restriction' => $content_access_restriction, 'memebership_plans' => $membership_plans);
        wc_get_template('/metabox/content_access_restriction.php', $params, '', HFORCE_MEMBERSHIP_MAIN_PATH . 'admin/templates/');
    }

    public function save_access_metabox($post_id) {


        if (!empty($_POST['_hforce_content_access_restriction'])) {
            $content_access_restriction = maybe_serialize($_POST['_hforce_content_access_restriction']);
            update_post_meta($post_id, '_hforce_content_access_restriction', $content_access_restriction);
        }
    }

    /**
     * Add action links to the plugin page.
     *
     * @since    1.0.0
     */
    public function xa_wt_membership_action_links($links) {


        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=hf_memberships') . '">' . __('Settings', 'xa-woocommerce-membership') . '</a>',
            '<a target="_blank" href="https://wordpress.org/support/plugin/xa-woocommerce-memberships">' . __('Support', 'xa-woocommerce-membership') . '</a>',
            '<a target="_blank" href="https://wordpress.org/support/plugin/xa-woocommerce-memberships/reviews?rate=5#new-post">' . __('Review', 'xa-woocommerce-membership') . '</a>',
        );
        if (array_key_exists('deactivate', $links)) {
           $links['deactivate'] = str_replace('<a', '<a class="hfmembership-deactivate-link"', $links['deactivate']);
        }
        return array_merge($plugin_links, $links);
    }

    public function init_hf_membership_user_roles() {
        global $wp_roles;

        if (class_exists('WP_Roles') && !isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        
        if (is_object($wp_roles)) {

            foreach (array('hf_membership_plan', 'hf_user_membership') as $post_type) {

                $args = new stdClass();
                $args->map_meta_cap = true;
                $args->capability_type = $post_type;
                $args->capabilities = array();

                foreach (get_post_type_capabilities($args) as $builtin => $mapped) {

                    $wp_roles->add_cap('shop_manager', $mapped);
                    $wp_roles->add_cap('administrator', $mapped);
                }
            }

            $wp_roles->add_cap('shop_manager', 'manage_woocommerce_hf_membership_plans');
            $wp_roles->add_cap('administrator', 'manage_woocommerce_hf_membership_plans');

            $wp_roles->add_cap('shop_manager', 'manage_woocommerce_hf_user_memberships');
            $wp_roles->add_cap('administrator', 'manage_woocommerce_hf_user_memberships');
        }
    }

    public function init_hf_membership_post_types() {

        $show_in_menu = ( current_user_can('manage_woocommerce') ) ? 'woocommerce' : true;

        register_post_type('hf_membership_plan', array(
            'labels' => array(
                'name' => __('WebToffee Membership Plans', 'xa-woocommerce-membership'),
                'singular_name' => __('WebToffee Membership Plan', 'xa-woocommerce-membership'),
                'menu_name' => __('WebToffee Memberships', 'xa-woocommerce-membership'),
                'add_new' => __('Add Membership Plan', 'xa-woocommerce-membership'),
                'add_new_item' => __('Add New Membership Plan', 'xa-woocommerce-membership'),
                'edit' => __('Edit', 'xa-woocommerce-membership'),
                'edit_item' => __('Edit Membership Plan', 'xa-woocommerce-membership'),
                'new_item' => __('New Membership Plan', 'xa-woocommerce-membership'),
                'view' => __('View Membership Plans', 'xa-woocommerce-membership'),
                'view_item' => __('View Membership Plan', 'xa-woocommerce-membership'),
                'search_items' => __('Search Membership Plans', 'xa-woocommerce-membership'),
                'not_found' => __('No Membership Plans found', 'xa-woocommerce-membership'),
                'not_found_in_trash' => __('No Membership Plans found in trash', 'xa-woocommerce-membership'),
            ),
            'description' => __('You can add new Membership Plans here.', 'xa-woocommerce-membership'),
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'hf_membership_plan',
            'map_meta_cap' => true,
            'show_in_menu' => $show_in_menu,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => array('title'),
                )
        );
    }

    public function init_hf_user_membership() {

        $show_in_menu = ( current_user_can('manage_woocommerce') ) ? 'woocommerce' : true;

        register_post_type('hf_user_membership', array(
            'labels' => array(
                'name' => __('WebToffe Members', 'xa-woocommerce-membership'),
                'singular_name' => __('WebToffe User Membership', 'xa-woocommerce-membership'),
                'menu_name' => __('WebToffe Memberships', 'xa-woocommerce-membership'),
                'add_new' => __('Add Member', 'xa-woocommerce-membership'),
                'add_new_item' => __('Add New User Membership', 'xa-woocommerce-membership'),
                'edit' => __('Edit', 'xa-woocommerce-membership'),
                'edit_item' => __('Edit User Membership', 'xa-woocommerce-membership'),
                'new_item' => __('New User Membership', 'xa-woocommerce-membership'),
                'view' => __('View User Memberships', 'xa-woocommerce-membership'),
                'view_item' => __('View User Membership', 'xa-woocommerce-membership'),
                'search_items' => __('Search Members', 'xa-woocommerce-membership'),
                'not_found' => __('No User Memberships found', 'xa-woocommerce-membership'),
                'not_found_in_trash' => __('No User Memberships found in trash', 'xa-woocommerce-membership'),
            ),
            'description' => __('You can add new User Memberships here.', 'xa-woocommerce-membership'),
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'hf_user_membership',
            'map_meta_cap' => true,
            'show_in_menu' => $show_in_menu,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => array(''),
                )
        );


        $user_memberships_obj = new XA_Woocommerce_User_Memberships();
        $statuses = $user_memberships_obj->wt_get_user_membership_statuses();

        foreach ($statuses as $status => $args) {

            $args = wp_parse_args($args, array(
                'label' => ucfirst($status),
                'public' => false,
                'protected' => true,
            ));

            register_post_status($status, $args);
        }
    }

    public function hf_hide_from_menus() {

        global $submenu;
        if (isset($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $key => $menu) {
                if ('edit.php?post_type=hf_membership_plan' === $menu[2]) {
                    unset($submenu['woocommerce'][$key]);
                }
            }
        }
    }

    public function print_tab_html() {

        $membership_screens = array(
            'hf_user_membership',
            'edit-hf_user_membership',
            'hf_membership_plan',
            'edit-hf_membership_plan',
        );

        $screen = get_current_screen();

        if ($screen && in_array($screen->id, $membership_screens, true)) :

            $tabs = apply_filters('hf_memberships_admin_tabs', array(
                'members' => array(
                    'title' => __('Members', 'xa-woocommerce-membership'),
                    'url' => admin_url('edit.php?post_type=hf_user_membership'),
                ),
                'memberships' => array(
                    'title' => __('Membership Plans', 'xa-woocommerce-membership'),
                    'url' => admin_url('edit.php?post_type=hf_membership_plan'),
                ),
            ));

            if (is_array($tabs)) :
                ?>
                <div class="wrap woocommerce">
                    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
                        <?php
                        $current_tab = 'members';
                        if (strpos($screen->id, 'plan') !== false) {
                            $current_tab = 'memberships';
                        }
                        ?>
                        <?php foreach ($tabs as $tab_id => $tab) : ?>
                            <?php $class = $tab_id === $current_tab ? array('nav-tab', 'nav-tab-active') : array('nav-tab'); ?>
                            <?php printf('<a href="%1$s" class="%2$s">%3$s</a>', esc_url($tab['url']), implode(' ', array_map('sanitize_html_class', $class)), esc_html($tab['title'])); ?>
                        <?php endforeach; ?>
                    </h2>
                </div>
                <?php
            endif;
        endif;
    }

    public function membership_plan_metabox() {

        global $post;

        if (!$post instanceof WP_Post) {
            return;
        }

        $screen = get_current_screen();
        if ('hf_membership_plan' !== $screen->id) {
            return;
        }

        if (!current_user_can('manage_woocommerce_hf_membership_plans')) {
            return;
        }

        add_meta_box('hf-memberships-membership-plan-data', __('Membership Plan Data', 'xa-woocommerce-membership'), array($this, 'render_membership_plan_metabox'), 'hf_membership_plan', 'normal', 'high');
        add_filter('postbox_classes_hf_membership_plan_hf-memberships-membership-plan-data', array($this, 'hforce_membership_plan_postbox_classes'));
    }

    public function hforce_membership_plan_postbox_classes($classes) {
        return wp_parse_args($classes, array('hf-memberships', 'woocommerce'));
    }

    public function render_membership_plan_metabox(WP_Post $post) {

        $plans_obj = new Xa_Woocommerce_Membership_Plans( );
        $membership_plan = $plans_obj->hforce_get_membership_plan($post);
        ?>
        <div class="panel-wrap data">
            <?php wp_nonce_field('update-hf-memberships-membership-plan-data', '_hf_memberships_membership_plan_data_nonce'); ?>
            <ul class="hf_membership_plan_data_tabs wc-tabs">
                <?php
                $membership_plan_data_tabs = apply_filters('hforce_membership_plan_data_tabs', array(
                    'general' => array(
                        'label' => __('General', 'xa-woocommerce-membership'),
                        'target' => 'hf-membership-plan-data-general',
                        'class' => array('active'),
                    ),
                ));


                foreach ($membership_plan_data_tabs as $key => $tab) :

                    $class = isset($tab['class']) ? $tab['class'] : array();
                    ?>
                    <li class="<?php echo sanitize_html_class($key); ?>_options <?php echo sanitize_html_class($key); ?>_tab <?php echo implode(' ', array_map('sanitize_html_class', $class)); ?>">
                        <a href="#<?php echo esc_attr($tab['target']); ?>"><span><?php echo esc_html($tab['label']); ?></span></a>
                    </li>
                    <?php
                endforeach;

                do_action('hf_membership_plan_write_panel_tabs');
                ?>
            </ul>
            <?php
            if (!empty($membership_plan_data_tabs)) {

                $membership_plans_obj = new Xa_Woocommerce_Membership_Plans();
                
                foreach (array_keys($membership_plan_data_tabs) as $tab) {

                    switch ($tab) {
                        case 'general':
                            $membership_plans_obj->render_general_tab_html($membership_plan, $post);
                            break;

                        default:
                            break;
                    }
                }
            }

            do_action('hf_membership_plan_data_panels');
            ?>
            <div class="clear"></div>
        </div>
        <?php
    }

    public function save_membership_plan_metabox($post_id, WP_Post $post) {


       
        if (!isset($_POST['_hf_memberships_membership_plan_data_nonce']) || !wp_verify_nonce($_POST['_hf_memberships_membership_plan_data_nonce'], 'update-hf-memberships-membership-plan-data')) {
            return;
        }

        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!in_array($post->post_type, array('hf_membership_plan',), true)) {
            return;
        }

        if (isset($_POST['post_type']) && 'page' === $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        if (!current_user_can('manage_woocommerce_hf_membership_plans')) {
            return;
        }


        $membership_plans_obj = new Xa_Woocommerce_Membership_Plans();
        $membership_plans_obj->update_plan_data($post_id, $post);

        do_action('hforce_memberships_save_meta_box', $_POST, 'hf-memberships-membership-plan-data', $post_id, $post);
    }

    public function render_membership_plan_columns($columns) {

        unset($columns['cb']);
        $columns['access'] = __('Product', 'xa-woocommerce-membership');
        $columns['members'] = __('Members', 'xa-woocommerce-membership');
        return $columns;
    }

    public function render_membership_plan_content($column, $post_id) {
        global $post;

        $plans_obj = new Xa_Woocommerce_Membership_Plans();
        $membership_plan = $plans_obj->hforce_get_membership_plan($post);


        if ($membership_plan) {

            switch ($column) {


                case 'length':

                    echo __('Unlimited', 'xa-woocommerce-membership');

                    break;

                case 'access':

                    esc_html_e('Purchase', 'xa-woocommerce-membership');
                    $membership_plan->list_products_granting_access($membership_plan);


                    break;

                case 'members':

                    $view_members = admin_url("edit.php?post_type=hf_user_membership?s&post_type=hf_user_membership&action=-1&post_parent={$post_id}");

                    echo '<a href="' . esc_url($view_members) . '" title="' . esc_html__('View Members', 'xa-woocommerce-membership') . '">';
                    echo $membership_plan->get_memberships_count();
                    echo '</a>';

                    break;
            }
        }
    }

    public function init_member_metabox() {

        global $pagenow;
        if ($screen = get_current_screen()) {

            if (in_array($screen->id, array('hf_user_membership', 'edit-hf_user_membership'), true)) {


                if (!$screen || ( 'post-new.php' !== $pagenow && 'post.php' !== $pagenow )) {
                    return;
                }


                if ('hf_user_membership' === $screen->id) {

                    add_meta_box('hf-memberships-user-membership-data', __('User Membership Data', 'xa-woocommerce-membership'), array($this, 'render_user_membership_metabox'), 'hf_user_membership', 'normal', 'high');
                    add_filter('postbox_classes_hf_user_membership_hf-memberships-user-membership-data', array($this, 'hforce_user_membership_postbox_classes'));
                }
            }
        }
    }

    public function hforce_user_membership_postbox_classes($classes) {
        return wp_parse_args($classes, array('hf-user-memberships', 'woocommerce'));
    }

    public function render_user_membership_metabox(WP_Post $post) {

        
        $this->post = $post;

        $user_memberships_obj = new XA_Woocommerce_User_Memberships( );
        $user_membership = $user_memberships_obj->get_user_membership($post->ID);

        $user = $this->get_membership_user($user_membership);

        


        $plans_obj = new Xa_Woocommerce_Membership_Plans();
        $membership_plans = $plans_obj->get_available_membership_plans();

        $user_memberships_obj = new XA_Woocommerce_User_Memberships();

        $user_memberships = array();
        
        $status_options = array();
        foreach ($user_memberships_obj->wt_get_user_membership_statuses() as $status => $labels) {
            $status_options[$status] = $labels['label'];
        }



        $current_membership = null;
        ?>
        <h3 class="membership-plans">
            <?php wp_nonce_field('update-hf-memberships-user-membership-data', '_hf_memberships_user_membership_data_nonce'); ?>
            <ul class="sections">

                <?php if (!empty($user_memberships)) : ?>

                    <?php foreach ($user_memberships as $membership) : ?>

                        <?php if ($membership->get_plan()) : ?>

                            <li <?php if ((int) $membership->get_id() === (int) $post->ID) : $current_membership = $membership->get_id(); ?>class="active"<?php endif; ?>>
                                <a href="<?php echo esc_url(get_edit_post_link($membership->get_id())); ?>"><?php echo wp_kses_post($membership->get_plan()->get_name()); ?></a>
                            </li>

                        <?php endif; ?>

                    <?php endforeach; ?>

                <?php endif; ?>

            </ul>
        </h3>
        <?php
        $this->output_plan_details_panel($user_membership, $status_options, $plans_obj);
        echo '<div class="clear"></div>';
    }

    private function output_plan_details_panel($user_membership, $status_options, $plans_obj) {


        global $post, $pagenow;
        ?>
        <div class="plan-details">
            <h4><?php esc_html_e('Membership Details', 'xa-woocommerce-membership'); ?></h4>
            <div class="woocommerce_options_panel">
                <?php
                do_action('hf_memberships_before_user_membership_details', $user_membership);


                $membership_plan_options = $this->get_membership_plan_options($user_membership, ($user_membership) ? $user_membership->get_user_id() : 0);

                if (($user_membership) ? $user_membership->get_plan_id() : 0) {
                    $membership_plan_id = $user_membership->get_plan_id();
                } else {
                    $membership_plan_id = !empty($membership_plan_options) ? key($membership_plan_options) : '';
                }

                $membership_plan = is_numeric($membership_plan_id) ? $plans_obj->get_membership_plan($membership_plan_id) : null;

                
                woocommerce_wp_select(array(
                    'id' => 'post_parent',
                    'label' => __('Plan:', 'xa-woocommerce-membership'),
                    'options' => $membership_plan_options,
                    'value' => $membership_plan_id,
                    'class' => 'wide',
                    'wrapper_class' => 'hf-membership-plan',
                ));

                $status_string = ($post) ? $post->post_status : 'hf-active';


                
                woocommerce_wp_select(array(
                    'id' => '_post_status',
                    'label' => __('Status:', 'xa-woocommerce-membership'),
                    'options' => $status_options,
                    'value' => $status_string,
                    'class' => 'wide',
                ));

                $date_hint = __('YYYY-MM-DD', 'xa-woocommerce-membership');

                
                if ('post.php' === $pagenow) {

                    if(!empty($user_membership))
                        $start_date = $user_membership->get_local_start_date('Y-m-d');
                    else
                        $start_date  = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
                } else {

                    $start_date = date_i18n('Y-m-d', current_time('timestamp'));
                }

                woocommerce_wp_text_input(array(
                    'id' => '_start_date',
                    'label' => __('Member since:', 'xa-woocommerce-membership'),
                    'class' => 'hf-user-membership-date',
                    'value' => substr($start_date, 0, 10),
                ));

                
                $end_date = $user_membership->get_local_end_date('Y-m-d', false);

                if (null === $end_date) {

                    
                    $end_date = '';

                    if ('auto-draft' === $post->post_status) {

                        $end_date = ( $membership_plan ) ? ($membership_plan->get_expiration_date($start_date)) : $end_date;
                    }
                }

                woocommerce_wp_text_input(array(
                    'id' => '_end_date',
                    'label' => __('Expires:', 'xa-woocommerce-membership'),
                    'class' => 'hf-user-membership-date',
                    'value' => substr($end_date, 0, 10),
                ));
                ?>
                <p class="form-field form-field-wide wc-customer-user hf-wc-customer-search">

                    <label for="_membership_user_id"><?php _e('Customer:', 'woocommerce') ?></label>
                    <?php
                    $user_string = '';
                    $user_id = '';
                    if ($user_membership) {
                        $user_id = absint($user_membership->get_member_user_id($user_membership->id));
                        $user = get_user_by('id', $user_id);
                        if ($user) {
                            $user_string = sprintf(
                                    esc_html__('%1$s (#%2$s &ndash; %3$s)', 'woocommerce'), $user->display_name, absint($user->ID), $user->user_email
                            );
                        } else {
                            $user_string = '';
                        }
                    }
                    ?>
                    <select class="wc-customer-search hf-wc-customer-search" id="_membership_user_id" name="_membership_user_id" data-placeholder="<?php esc_attr_e('Search users', 'woocommerce'); ?>" data-allow_clear="true">
                        <option value="<?php echo esc_attr($user_id); ?>" selected="selected"><?php echo htmlspecialchars($user_string); ?></option>
                    </select>

                </p>
                <?php
                do_action('hf_memberships_after_user_membership_details', $user_membership);
                ?>
            </div>
        </div>
        <?php
    }

    public function get_membership_plan_options($user_membership = null, $user_id = 0) {

        $membership_plan_options = array();

        $plans_obj = new Xa_Woocommerce_Membership_Plans();
        $membership_plans = $plans_obj->get_available_membership_plans();

        $user = get_userdata($user_id);

        if ($user && !user_can($user_id, 'create_users')) {

            
            $usr = new XA_Woocommerce_User_Memberships();
            $user_memberships = $usr->get_user_memberships($user->ID);

            foreach ($membership_plans as $membership_plan) {
                $exists = false;

                // All user can only have 1 membership per plan. check if user already has a membership for this plan.
                
                if (!empty($user_memberships)) {

                    foreach ($user_memberships as $membership) {

                        if ($membership->get_plan_id() === $membership_plan->get_id()) {
                            $exists = true;
                            break;
                        }
                    }
                }

                if (!$exists || $user_membership->get_plan_id() === $membership_plan->get_id()) {
                    $membership_plan_options[$membership_plan->get_id()] = $membership_plan->get_name();
                }
            }
        } else {
            foreach ($membership_plans as $membership_plan) {


                $membership_plan_options[$membership_plan->get_id()] = $membership_plan->get_name();
            }
            return $membership_plan_options;
        }

        return $membership_plan_options;
    }

    public function get_membership_user($user_membership = null) {
        global $pagenow;

        $user = null;
        $user_id = null;

        if ('post.php' === $pagenow && $user_membership) {
            $user_id = $user_membership->get_user_id();
        } elseif (isset($_GET['user'])) {
            $user_id = $_GET['user'];
        }

        if (is_numeric($user_id)) {
            $user = get_user_by('id', (int) $user_id);
        }

        return $user;
    }

    public function save_user_membership_metabox($post_id, WP_Post $post, $updated) {


        if (!isset($_POST['_hf_memberships_user_membership_data_nonce']) || !wp_verify_nonce($_POST['_hf_memberships_user_membership_data_nonce'], 'update-hf-memberships-user-membership-data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!in_array($post->post_type, array('hf_user_membership',), true)) {
            return;
        }

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



        $_POST['_post_update'] = false;

        $_post_status = ($_POST['_post_status']) ? $_POST['_post_status'] : 'hf-active';


        do_action('hforce_user_memberships_save_meta_box', $_POST, 'hf-memberships-user-membership-data', $post_id);
    }

    function update_user_membership_data($post, $id, $post_id) {



        $_post_status = ($_POST['_post_status']) ? $_POST['_post_status'] : 'hf-active';

        $_usr_id = isset($_POST['_membership_user_id']) ? $_POST['_membership_user_id'] : 0;

        $_post_parent = isset($_POST['post_parent']) ? $_POST['post_parent'] : 0;

        $user_membership_obj = new Xa_Woocommerce_User_Membership($post_id, $_usr_id);
        $plan_obj = new XA_Woocommerce_Membership_Plan($_post_parent);
        $product_ids = $plan_obj->get_product_ids();

        if (is_string($product_ids)) {
            $product_ids = explode(',', $product_ids);
        }

        $product_ids = array_map('intval', (array) $product_ids);


        $user_membership_obj->set_member_user_id($_usr_id);

        if (!empty($product_ids)) {
            $user_membership_obj->set_product_id($product_ids);
        }

        //echo '<pre>';print_r($user_membership_obj);exit;
        $user_membership_obj->hf_update_user_membership_data($post_id, $post, $user_membership_obj);
    }

    public function hf_user_membership_columns($columns) {

        unset($columns['title']);
        $columns['title'] = __('Name', 'xa-woocommerce-membership');
        $columns['email'] = __('Email', 'xa-woocommerce-membership');
        $columns['plan'] = __('Plan', 'xa-woocommerce-membership');
        $columns['status'] = __('Status', 'xa-woocommerce-membership');
        $columns['member_since'] = __('Member since', 'xa-woocommerce-membership');

        unset($columns['date']);

        return $columns;
    }

    public function hf_user_membership_column_content($column, $post_id) {

        $usr = new Xa_Woocommerce_User_Memberships();

        $user_membership = $usr->get_user_membership($post_id);

        $user = get_userdata($user_membership->get_user_id());
        $date_format = wc_date_format();
        $time_format = wc_time_format();

        switch ($column) {

            case 'email':
                echo $user ? $user->user_email : '';
                break;

            case 'plan':

                if ($plan = $user_membership->get_plan()) {
                    echo '<a href="' . esc_url(get_edit_post_link($user_membership->get_plan_id())) . '">' . $plan->get_name() . '</a>';
                } else {
                    echo '-';
                }

                break;

            case 'status':

                $statuses = $usr->wt_get_user_membership_statuses();
                $status = $user_membership->get_status();
                if ('trash' == $status) {
                    $label = __('Trash', 'xa-woocommerce-membership');
                } else {
                    $label = $statuses[$status]['label'];
                }
                echo esc_html($label);

                break;

            case 'member_since':

                $since_time = $user_membership->get_local_start_date('timestamp');

                $date = esc_html(date_i18n($date_format, (int) $since_time));
                $time = esc_html(date_i18n($time_format, (int) $since_time));

                printf('%1$s %2$s', $date, $time);

                break;
        }
    }

    public function hf_user_membership_row_actions($actions, WP_Post $post) {


        if ('hf_user_membership' === $post->post_type) {
            unset($actions['inline hide-if-no-js'], $actions['trash']);
        }

        return $actions;
    }

    public function hf_user_membership_title($title, $post_id) {

        global $pagenow;

        if ('hf_user_membership' === get_post_type($post_id)) {

            $usr = new XA_Woocommerce_User_Memberships();
            $user_membership = $usr->get_user_membership($post_id);


            if ($user_membership) {

                $user = get_userdata($user_membership->get_user_id());
                $plan = $user_membership->get_plan();

                if ($user && ( 'edit.php' === $pagenow || !$plan )) {
                    $title = $user->display_name;
                } elseif ($plan) {
                    $title = $plan->get_name();
                }
            }
        }
        return $title;
    }

    public function hforce_request_query($vars) {
        global $typenow;

        if ('hf_user_membership' === $typenow) {

            // filter by plan ID (post parent)
            if (isset($_GET['post_parent'])) {
                $vars['post_parent'] = $_GET['post_parent'];
            }
        }

        return $vars;
    }

    public function filter_posts($posts) {



        $current_user_id = get_current_user_id();
        foreach ($posts as $post_key => $post) {
            if (!$this->user_has_access_to_post($current_user_id, $post->ID)) {
                unset($posts[$post_key]);
            }
        }
        if(empty($posts) && !isset($_GET['s'])){
           $default_page = get_option(self::$option_prefix . '_default_access_denied_page', '');
           if(!empty($default_page)){
           $posts = array($default_page);
           }
        }
        return apply_filters('wt_user_allowed_posts', $posts);
    }

    public function user_has_access_to_post($user_id, $post_id) {
        $not_allowed_for_this_user = $this->get_not_allowed_post_ids_for_user($user_id);
        return !in_array($post_id, $not_allowed_for_this_user);
    }

    public function get_not_allowed_post_ids_for_user($user_id) {

        if (user_can($user_id, 'create_users'))
            return array();

        $usr = new XA_Woocommerce_User_Memberships();
        $user_memberships = $usr->get_user_memberships($user_id);
        
        $current_user_only_allowed_products = array();
        $plan_ids = array();
        if (!empty($user_memberships)) {
            foreach ($user_memberships as $user_membership) {

                $plan_id = $user_membership->plan->id;
                $plan_ids[] = $plan_id;

            }
        }

        
        $not_allowed_for_this_user = array();
        $restricted_items = $this->get_restricted_items($plan_ids);
        
        if (!empty($restricted_items)) {
            foreach ($restricted_items as $restricted_item) {
                
                $not_allowed_for_this_user[] = $restricted_item;
                
            }
        }
        

        return $not_allowed_for_this_user;
    }

    public function get_restricted_items($plan_ids) {
        
        $posts = array();
        
        $user_id = get_current_user_id();
        
        $current_visitor_is_member = FALSE;

        $memberships = new XA_Woocommerce_User_Memberships();
        $current_visitor_is_member = $memberships->is_user_member();
        
        
        foreach ($this->managed_post_types as $post_type) {
            $args = array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_key' => '_hforce_content_access_restriction',
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_hforce_content_access_restriction',
                        'value' => 'none";', // Anyone
                        'compare' => 'NOT LIKE'
                    ),
                    
                )
            );
            
            
            if( $current_visitor_is_member && (is_numeric($user_id) || 0 !== $user_id) ){
                $args['meta_query'][] = array(
                        'key' => '_hforce_content_access_restriction',
                        'value' => 'all_members";',
                        'compare' => 'NOT LIKE'
                    );
            }
            
            foreach ($plan_ids as $plan_id) {

                $args['meta_query'][] = array(
                            'key' => '_hforce_content_access_restriction',
                            'value' => '' . $plan_id . '',
                            'compare' => 'NOT LIKE'
                );
            }
            
            $this_posts = get_posts($args);

            if ($this_posts)
                $posts = array_merge($posts, $this_posts);
        }

        
        return array_unique($posts);
    }

    public function load_wc_admin_css_scripts($screen_ids) {

        $screen = get_current_screen();

        if ('hf_membership_plan' == $screen->id || 'hf_user_membership' == $screen->id) {
            //wp_enqueue_script( 'wc-enhanced-select' );
            return array_merge($screen_ids, array('hf_membership_plan', 'hf_user_membership',));
        }
        return $screen_ids;
    }
    
    
     //settings page
    
    public function add_memberships_settings_tab($settings_tabs) {

        $settings_tabs[Xa_Woocommerce_Membership::PLUGIN_ID] = __('WebToffee Memberships', 'xa-woocommerce-membership');
        return $settings_tabs;
    }
    
    public static function add_memberships_settings_page() {
        
        woocommerce_admin_fields(self::get_settings());
        wp_nonce_field('hf_memberships_settings', '_hfnonce', false);
    }
    

    public static function get_settings() {


        $page_ids = get_all_page_ids();
        $pages = array('' => __('No Default', 'xa-woocommerce-membership'));

        foreach ($page_ids as $page_id) {
            $pages[$page_id] = get_the_title($page_id);
        }

        return apply_filters('hf_memberships_settings', array(
            array(
                'name' => __('Membership Settings', 'xa-woocommerce-membership'),
                'type' => 'title',
                'desc' => '',
                'id' => self::$option_prefix . '_button_text',
            ),
            array(
                'name' => __('Default Access Denied Page', 'xa-woocommerce-membership'),
                'desc' => __('When a page is restricted for current visitor, set redirect to this page. Otherwise "Oops! That page can\'t be found"  message will be shown.', 'xa-woocommerce-membership'),
                'tip' => '',
                'id' => self::$option_prefix . '_default_access_denied_page',
                'css' => 'min-width:150px;',
                'default' => '',
                'type' => 'select',
                'options' => $pages,
                'desc_tip' => true,
            ),
            array('type' => 'sectionend', 'id' => self::$option_prefix . '_button_text'),
        ));
    }

    public function save_memberships_settings() {

        if (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_memberships_settings')) {
            return;
        }        
        woocommerce_update_options(self::get_settings());
    }

    
}