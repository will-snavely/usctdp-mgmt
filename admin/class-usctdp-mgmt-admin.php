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

    // A static property holding configuration settings
    public static $post_handlers =  [
        "new_session" => [
            "submit_hook" => "usctdp_new_session",
            "nonce_name" => "usctdp_new_session_nonce",
            "nonce_action" => "usctdp_new_session_nonce_action",
            "callback" => "new_session_handler"
        ]
    ];

    public static $transient_prefix = "usctdp_admin_transient";

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
            $this->plugin_name,
            plugin_dir_url(__FILE__) . "css/usctdp-mgmt-admin.css",
            [],
            $this->version,
            "all",
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . "js/usctdp-mgmt-admin.js",
            ["jquery", "acf-input"],
            $this->version,
            false,
        );

        $screen = get_current_screen();
        error_log("On Screen: " . $screen->id);
        if ( $screen->id == 'usctdp-admin_page_usctdp-admin-new-session' ) {
            do_action( 'acf/input/admin_enqueue_scripts' );
/*            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . "js/usctdp-mgmt-admin-acf-init.js",
                ["jquery", "acf-input"],
                $this->version,
                false
             );
             */
        }
    }

    private function echo_admin_page($path) {
        if (file_exists( $path) ) {
            require_once($path);
        } else {
            echo '<div class="notice notice-error"><p>Admin view file not found.</p></div>';
        }
    }

    public function fetch_admin_main_page() {
        $admin_dir = plugin_dir_path( __FILE__ );
        $main_display = $admin_dir . "partials/usctdp-mgmt-admin-main.php";
        $this->echo_admin_page($main_display);
    }

    public function fetch_admin_new_session_page() {
        $admin_dir = plugin_dir_path( __FILE__ );
        $main_display = $admin_dir . "partials/usctdp-mgmt-admin-new-session.php";
        $this->echo_admin_page($main_display);
    }

    private function get_redirect_url($page_slug) {
        return admin_url( 'admin.php?page=' . $page_slug );
    }

    public function new_session_handler() {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers["new_session"];
        $nonce_name = $post_handler["nonce_name"];
        $nonce_action = $post_handler["nonce_action"];
        
        $unique_token = bin2hex(random_bytes( 8 ));
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
        $redirect_url = add_query_arg(
            'usctdp_token', $unique_token, 
            $this->get_redirect_url( "usctdp-admin-new-session"));
           
        if (isset( $_POST[$nonce_name] ) && wp_verify_nonce( $_POST[$nonce_name], $nonce_action) ) {
            $message = "Request completed successfully!";
            set_transient( $transient_key, array( 'type' => 'success', 'message' => $message ), 10);
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            $message = "This request failed verification. Report this to a developer.";
            set_transient( $transient_key, array( 'type' => 'error', 'message' => $message ), 10);
            wp_safe_redirect($redirect_url);
	    }
    }

    public function validate_start_end_dates() {
        $post_id = acf_maybe_get_POST('post_ID');
        $current_post_type = get_post_type( $post_id );
        $target_post_type = 'usctdp-session'; 
        if ( $current_post_type !== $target_post_type ) {
            return;
        }
        
        $start_date_value = acf_maybe_get_POST("acf[field_usctdp_session_start_date]"); 
        $end_date_value = acf_maybe_get_POST("acf[field_usctdp_session_end_date]");  

        if ( $start_date_value && $end_date_value ) {
            // Convert the 'Ymd' strings to DateTime objects for comparison
            $start_date_obj = DateTime::createFromFormat('Ymd', $start_date_value);
            $end_date_obj   = DateTime::createFromFormat('Ymd', $end_date_value);

            // Check if the end date is less than the start date
            if ( $end_date_obj < $start_date_obj ) {
                // Add a validation error to the End Date field
                acf_add_validation_error(
                    "acf[acf_id_for_end_date]", // Use the End Date's field KEY here
                    'The End Date must be after the Start Date.'
                );
            }
        }
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'USCTDP Admin Portal',
            'USCTDP Admin',
            'manage_options',
            'usctdp-admin-main',
            [$this, 'fetch_admin_main_page']
        );

        add_submenu_page(
		    'usctdp-admin-main',
		    'Create New Session',
            'Create New Session',
            'manage_options',
            'usctdp-admin-new-session',
            [$this, 'fetch_admin_new_session_page']
        );
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
