<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.wsnavely.com
 * @since      1.0.0
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/admin
 * @author     Will Snavely <will.snavely@gmail.com>
 */

define( 'USCTDP_NEW_SESSION_ACTION', 'usctdp_admin_new_session' );

class Usctdp_Mgmt_Admin
{
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

    private $hidden_title_post_types = [
        'usctdp-student',
        'usctdp-family',
        'usctdp-staff',
        'usctdp-session',
        'usctdp-class',
        'usctdp-registration'
    ];

    // A static property holding configuration settings
    public static $post_handlers =  [
        'new_session' => [
            'submit_hook' => 'usctdp_new_session',
            'nonce_name' => 'usctdp_new_session_nonce',
            'nonce_action' => 'usctdp_new_session_nonce_action',
            'callback' => 'new_session_handler'
        ]
    ];

    public static $transient_prefix = 'usctdp_admin_transient';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name . 'primary-css',
            plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin.css',
            [],
            $this->version,
            'all');

        $screen = get_current_screen();
        if(in_array($screen->post_type, $this->hidden_title_post_types)) {
            wp_enqueue_style(
                $this->plugin_name . 'hidden-title-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-custom-post-hidden-title.css',
                [],
                $this->version,
                'all');
        }

        if($screen->base == 'usctdp-admin_page_usctdp-admin-new-session') {
            wp_enqueue_style(
                $this->plugin_name . 'new-session-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-new-session.css',
                [],
                $this->version,
                'all');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name . 'primary-js',
            plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin.js',
            ['jquery', 'acf-input'],
            $this->version,
            true);

        $screen = get_current_screen();
        if($screen->base == 'usctdp-admin_page_usctdp-admin-new-session') {
            wp_enqueue_script(
                $this->plugin_name . 'new-session-js',
                plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-new-session.js',
                ['jquery', 'acf-input'],
                $this->version,
                true);
        }  
    }

    private function get_redirect_url($page_slug) {
        return admin_url( 'admin.php?page=' . $page_slug );
    }

    private function echo_admin_page($path) {
        if (file_exists( $path) ) {
            require_once($path);
        } else {
            echo '<div class="notice notice-error"><p>Admin view file not found.</p></div>';
        }
    }

    public function fetch_main_page() {
        $admin_dir = plugin_dir_path( __FILE__ );
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-main.php';
        $this->echo_admin_page($main_display);
    }

    public function load_new_session_page() {
        acf_form_head();
    }

    public function fetch_new_session_page() {
        $admin_dir = plugin_dir_path( __FILE__ );
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-new-session.php';
        $this->echo_admin_page($main_display);
    }

    public function new_session_handler() {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers['new_session'];
        $nonce_name = $post_handler['nonce_name'];
        $nonce_action = $post_handler['nonce_action'];
        
        $unique_token = bin2hex(random_bytes( 8 ));
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
        $redirect_url = add_query_arg(
            'usctdp_token', $unique_token, 
            $this->get_redirect_url( 'usctdp-admin-new-session'));
           
        if (isset( $_POST[$nonce_name] ) && wp_verify_nonce( $_POST[$nonce_name], $nonce_action) ) {
            
            // 1. Create Session Post
            $session_title = 'Session ' . date('Y-m-d H:i:s'); // Temporary title, maybe update based on dates?
            $session_id = wp_insert_post([
                'post_type' => 'usctdp-session',
                'post_status' => 'publish',
                'post_title' => $session_title
            ]);

            if (is_wp_error($session_id)) {
                $message = 'Error creating session: ' . $session_id->get_error_message();
                set_transient( $transient_key, array( 'type' => 'error', 'message' => $message ), 10);
                wp_safe_redirect($redirect_url);
                exit;
            }

            // 2. Update Session ACF Fields
            // $_POST['acf'] contains the session fields if we used acf_form
            if (isset($_POST['acf']) && is_array($_POST['acf'])) {
                foreach ($_POST['acf'] as $key => $value) {
                    update_field($key, $value, $session_id);
                }
            }

            // Update Session Title based on dates
            $start_date = get_field('field_usctdp_session_start_date', $session_id);
            $end_date = get_field('field_usctdp_session_end_date', $session_id);
            if ($start_date && $end_date) {
                $new_title = 'Session: ' . $start_date . ' - ' . $end_date;
                wp_update_post([
                    'ID' => $session_id,
                    'post_title' => $new_title
                ]);
            }

            // 3. Process Classes
            $count = 0;
            if (isset($_POST['usctdp_classes']) && is_array($_POST['usctdp_classes'])) {
                foreach ($_POST['usctdp_classes'] as $class_data) {
                    // Create Class Post
                    // Title: Class Type - Day - Time
                    // We need to map the values to readable labels for the title, or just use the raw values for now.
                    // Actually, let's create the post first, update fields, then generate title.
                    
                    $class_id = wp_insert_post([
                        'post_type' => 'usctdp-class',
                        'post_status' => 'publish',
                        'post_title' => 'New Class' // Will update
                    ]);

                    if (!is_wp_error($class_id)) {
                        $count++;
                        // Update ACF Fields
                        foreach ($class_data as $key => $value) {
                            update_field($key, $value, $class_id);
                        }
                        
                        // Link to Parent Session
                        update_field('field_usctdp_class_parent', $session_id, $class_id);

                        // Generate Title
                        $type = get_field('field_usctdp_class_type', $class_id); // This returns the value, maybe object?
                        // If return format is value/label, we might get the value.
                        // Let's assume we get the value.
                        $day = get_field('field_usctdp_class_dow', $class_id);
                        $time = get_field('field_usctdp_class_start_time', $class_id);
                        
                        // We can get the label from the field object if needed, but for now let's use the values.
                        // Or better, use the field object to get the label.
                        $type_field = get_field_object('field_usctdp_class_type', $class_id);
                        $type_label = $type_field['choices'][$type] ?? $type;

                        $day_field = get_field_object('field_usctdp_class_dow', $class_id);
                        $day_label = $day_field['choices'][$day] ?? $day;

                        $class_title = "$type_label - $day_label - $time";
                        wp_update_post([
                            'ID' => $class_id,
                            'post_title' => $class_title
                        ]);
                    }
                }
            }

            $message = "Session created with $count classes!";
            set_transient( $transient_key, array( 'type' => 'success', 'message' => $message ), 10);
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            $message = 'This request failed verification. Report this to a developer.';
            set_transient( $transient_key, array( 'type' => 'error', 'message' => $message ), 10);
            wp_safe_redirect($redirect_url);
	    }
    }

    public function add_admin_menu() {
        add_menu_page(
            'USCTDP Admin Portal',
            'USCTDP Admin',
            'manage_options',
            'usctdp-admin-main',
            [$this, 'fetch_main_page']
        );

        $new_session_hook = add_submenu_page(
		    'usctdp-admin-main',
		    'Create New Session',
            'Create New Session',
            'manage_options',
            'usctdp-admin-new-session',
            [$this, 'fetch_new_session_page']
        );
        add_action( 'load-' . $new_session_hook, [$this, 'load_new_session_page']);
    } 

    public function show_admin_notice() {
        $unique_token = sanitize_key( $_GET['usctdp_token'] ?? '' );
        if ( empty( $unique_token ) ) {
            return;
        }
        
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix  . '_' . $unique_token;

        if ( $notice = get_transient( $transient_key ) ) {
            $class = 'notice-' . sanitize_html_class( $notice['type'] );
            $message = esc_html( $notice['message'] );
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . $message . '</p></div>';
            delete_transient( $transient_key );
        }
    }
}
