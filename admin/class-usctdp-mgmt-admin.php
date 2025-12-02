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
        wp_enqueue_style(
            $this->plugin_name . 'external-flatpickr-css',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
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
        } else if($screen->base == 'toplevel_page_usctdp-admin-main') {
            wp_enqueue_style(
                $this->plugin_name . 'admin-main-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin-main.css',
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
        wp_enqueue_script(
            $this->plugin_name . 'external-flatpickr-js',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            [],
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
            $this->get_redirect_url('usctdp-admin-new-session'));

        $request_completed = false;
        $created_ids = [];
        $transient_data = null;

        try {
            if (!isset( $_POST[$nonce_name] ) || !wp_verify_nonce( $_POST[$nonce_name], $nonce_action) ) {
                throw new ErrorException('Request verification failed.');
            }
            if (isset($_POST['session']) && is_array($_POST['session'])) {
                $session_name = sanitize_text_field($_POST['session']['field_usctdp_session_name']);
                $session_start = sanitize_text_field($_POST['session']['field_usctdp_session_start_date']);
                $session_end = sanitize_text_field($_POST['session']['field_usctdp_session_end_date']);
                $start_date = DateTime::createFromFormat('Y-m-d', $session_start);
                $end_date = DateTime::createFromFormat('Y-m-d', $session_end);
                $session_id = wp_insert_post([
                    'post_type' => 'usctdp-session',
                    'post_status' => 'publish',
                    'post_title' => Usctdp_Mgmt_Session::create_session_title($session_name, $start_date, $end_date)
                ]);

                if (is_wp_error($session_id)) {
                    throw new ErrorException('Error creating session: ' . $session_id->get_error_message());
                } 
                $created_ids[] = $session_id;
                foreach ($_POST['session'] as $key => $value) {
                    if(!update_field($key, sanitize_text_field($value), $session_id)) {
                        throw new ErrorException('Failed to update session field: ' . $key);
                    }
                }
            } else {
                throw new ErrorException('Session data not found.');
            }

            if (isset($_POST['usctdp_classes']) && is_array($_POST['usctdp_classes'])) {
                foreach ($_POST['usctdp_classes'] as $key => $class_data) {     
                    $class_id = wp_insert_post([
                        'post_title' => '',
                        'post_type' => 'usctdp-class',
                        'post_status' => 'publish',
                        'meta_input' => [ 
                            'class_index' => $key 
                        ]
                    ]);
                    if (is_wp_error($class_id)) {
                        throw new ErrorException('Error creating class: ' . $class_id->get_error_message());
                    }   
                    $created_ids[] = $class_id;
                    foreach ($class_data as $key => $value) {
                        if(!update_field($key, sanitize_text_field($value), $class_id)) {
                            throw new ErrorException('Failed to update class field: ' . $key);
                        }
                    }
                    if(!update_field('field_usctdp_class_parent', $session_id, $class_id)) {
                        throw new ErrorException('Failed to update class parent field with: ' . $session_id);
                    }
                }
            }
            $message = "Session created successfully!";
            $request_completed = true;
            $transient_data = [
                'type' => 'success',
                'message' => $message
            ];
        } catch(Exception $e) {
            $transient_data = [
                'type' => 'error',
                'message' => $message            
            ];
            Usctdp_Mgmt_Logger::getLogger()->log_error($message);
            $post_data = print_r($_POST, true);     
            Usctdp_Mgmt_Logger::getLogger()->log_error($post_data);
	    } finally {
            if(!$request_completed) {
                foreach($created_ids as $id) {
                    if(!wp_delete_post($id, true)) {
                        Usctdp_Mgmt_Logger::getLogger()->log_critical(
                            'Failed to delete post "' . $id . '" in new_session_handler()'
                        );
                    }
                }

                if (!$transient_data) {
                    $transient_data = [
                        'type' => 'error',
                        'message' => 'An unknown error occurred.'
                    ];
                }
            }

            set_transient( $transient_key, $transient_data, 10);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'USCTDP Admin',
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