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

use Google\Client;
use Google\Service\Docs;
use Google\Service\Docs\Request as DocsRequest;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Docs\InsertTextRequest;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

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

    public static $post_handlers =  [
        'new_session' => [
            'submit_hook' => 'usctdp_new_session',
            'nonce_name' => 'usctdp_new_session_nonce',
            'nonce_action' => 'usctdp_new_session_nonce_action',
            'callback' => 'new_session_handler'
        ],
        'registration' => [
            'submit_hook' => 'usctdp_registration',
            'nonce_name' => 'usctdp_registration_nonce',
            'nonce_action' => 'usctdp_registration_nonce_action',
            'callback' => 'registration_handler'
        ]
    ];

    public static $ajax_handlers = [
        'datatable_search' => [
            'action' => 'datatable_search',
            'nonce' => 'datatable_search_nonce',
            'callback' => 'ajax_datatable_search'
        ],
        'select2_search' => [
            'action' => 'select2_search',
            'nonce' => 'select2_search_nonce',
            'callback' => 'ajax_select2_search'
        ],
        'gen_roster' => [
            'action' => 'gen_roster',
            'nonce' => 'gen_roster_nonce',
            'callback' => 'ajax_gen_roster'
        ],
        'class_qualification' => [
            'action' => 'get_class_qualification',
            'nonce' => 'get_class_qualification_nonce',
            'callback' => 'ajax_get_class_qualification'
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
            'all'
        );
        wp_enqueue_style(
            $this->plugin_name . 'external-flatpickr-css',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            [],
            $this->version,
            'all'
        );
        wp_enqueue_style(
            $this->plugin_name . 'external-datatables-css',
            'https://cdn.datatables.net/2.3.5/css/dataTables.dataTables.min.css',
            [],
            $this->version,
            'all'
        );

        $screen = get_current_screen();
        if (in_array($screen->post_type, $this->hidden_title_post_types)) {
            wp_enqueue_style(
                $this->plugin_name . 'hidden-title-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-custom-post-hidden-title.css',
                [],
                $this->version,
                'all'
            );
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
            true
        );
        wp_enqueue_script(
            $this->plugin_name . 'external-flatpickr-js',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            ['jquery'],
            $this->version,
            true
        );
        wp_enqueue_script(
            $this->plugin_name . 'external-datatables-js',
            'https://cdn.datatables.net/2.3.5/js/dataTables.min.js',
            ['jquery'],
            $this->version,
            true
        );
    }

    private function usctdp_script_id($suffix)
    {
        return $this->plugin_name . '-admin-' . $suffix . '-js';
    }

    private function usctdp_style_id($suffix)
    {
        return $this->plugin_name . '-admin-' . $suffix . '-css';
    }

    private function enqueue_usctdp_page_script($suffix, $dependencies = [])
    {
        $deps = $dependencies ? $dependencies : [
            'jquery',
            'acf-input',
            $this->plugin_name . 'external-flatpickr-js',
            $this->plugin_name . 'external-datatables-js',
            $this->plugin_name . 'primary-js'
        ];
        wp_enqueue_script(
            $this->usctdp_script_id($suffix),
            plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin-' . $suffix . '.js',
            $deps,
            $this->version,
            true
        );
    }

    private function enqueue_usctdp_page_style($suffix, $dependencies = [])
    {
        $deps = $dependencies ? $dependencies : [];
        wp_enqueue_style(
            $this->usctdp_style_id($suffix),
            plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin-' . $suffix . '.css',
            $deps,
            $this->version,
            'all'
        );
    }

    private function get_redirect_url($page_slug)
    {
        return admin_url('admin.php?page=' . $page_slug);
    }

    private function add_usctdp_submenu($page_slug, $title, $load_callback = null)
    {
        $function_slug = str_replace('-', '_', $page_slug);
        $callback = [$this, 'fetch_' . $function_slug . '_page'];
        $capability = 'manage_options';
        $menu_slug = 'usctdp-admin-' . $page_slug;
        $hook = add_submenu_page(
            'usctdp-admin-main',
            $title,
            $title,
            $capability,
            $menu_slug,
            function () use ($page_slug) {
                $admin_dir = plugin_dir_path(__FILE__);
                $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-' . $page_slug . '.php';
                $this->echo_admin_page($main_display);
            }
        );
        add_action('load-' . $hook, function () use ($page_slug) {
            acf_form_head();
            $this->enqueue_usctdp_page_script($page_slug);
            $this->enqueue_usctdp_page_style($page_slug);
        });
        if ($load_callback) {
            add_action('load-' . $hook, $load_callback);
        }
        return $hook;
    }

    public function usctdp_google_oauth_handler()
    {
        $redirect_url = "http://127.0.0.1/wp/wp-admin/admin.php?page=usctdp-admin-main";
        if (!isset($_GET['page']) || $_GET['page'] !== 'usctdp-admin-main') {
            return;
        }

        // HANDLE THE INITIAL BUTTON CLICK (Initiate Redirect to Google)
        if (isset($_GET['usctdp_google_auth']) && $_GET['usctdp_google_auth'] === '1') {
            error_log('USCTDP: Google OAuth Initiated');
            $scopes = ['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/documents'];
            $client = new Client();
            $client->setClientId(env('GOOGLE_DOCS_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_DOCS_CLIENT_SECRET'));
            $client->setRedirectUri($redirect_url);

            $client->setAccessType('offline');
            $client->setPrompt('consent');
            $client->setScopes($scopes);
            $authUrl = $client->createAuthUrl();
            wp_redirect(filter_var($authUrl, FILTER_SANITIZE_URL));
            exit;
        } else if (isset($_GET['code'])) {
            error_log('USCTDP: Google OAuth Code Received');
            $unique_token = bin2hex(random_bytes(8));
            $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
            $transient_data = null;
            if (!current_user_can('manage_options')) {
                $transient_data = [
                    'type' => 'error',
                    'message' => 'You do not have permission to perform this action.'
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect(add_query_arg(['usctdp_auth_status' => 'error', 'code' => false], $redirect_url));
                exit;
            }

            $client = new Client();
            $client->setClientId(env('GOOGLE_DOCS_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_DOCS_CLIENT_SECRET'));
            $client->setRedirectUri($redirect_url);

            $code = sanitize_text_field(wp_unslash($_GET['code']));
            try {
                $token = $client->fetchAccessTokenWithAuthCode($code);
                if (isset($token['refresh_token'])) {
                    error_log('USCTDP: Google OAuth Refresh Token Received');
                    update_option('usctdp_google_refresh_token', $token['refresh_token']);
                    $message = 'Authorization successful! Refresh Token stored.';
                } else {
                    error_log('USCTDP: Google OAuth Refresh Token Not Received');
                    $message = 'Authorization successful, but Refresh Token was not returned (user may have authorized previously).';
                }
                $transient_data = [
                    'type' => 'success',
                    'message' => $message
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect(add_query_arg(['usctdp_auth_status' => 'success', 'code' => false], $redirect_url));
                exit;
            } catch (\Exception $e) {
                error_log("Google OAuth Error: " . $e->getMessage());
                $transient_data = [
                    'type' => 'error',
                    'message' => 'An unknown error occurred.'
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect(add_query_arg('usctdp_auth_status', 'error', $redirect_url));
                exit;
            }
        }
    }

    public function add_admin_menu()
    {
        $main_menu_page = add_menu_page(
            'USCTDP Admin',
            'USCTDP Admin',
            'manage_options',
            'usctdp-admin-main',
            function () {
                $admin_dir = plugin_dir_path(__FILE__);
                $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-main.php';
                $this->echo_admin_page($main_display);
            }
        );
        add_action('load-' . $main_menu_page, function () {});

        // Override the slug on the first menu item
        $this->add_usctdp_submenu('classes', 'Classes', [$this, 'load_classes_page']);
        $this->add_usctdp_submenu('rosters', 'Rosters', [$this, 'load_rosters_page']);
        $this->add_usctdp_submenu('register', 'Registration', [$this, 'load_register_page']);
        $this->add_usctdp_submenu('new-session', 'New Session');
        $this->add_usctdp_submenu('families', 'Families', [$this, 'load_families_page']);
    }

    private function echo_admin_page($path)
    {
        if (file_exists($path)) {
            require_once($path);
        } else {
            echo '<div class="notice notice-error"><p>Admin view file not found.</p></div>';
        }
    }

    private function extract_session_and_class_context()
    {
        $session_id_key = 'session_id';
        $session_id = '';
        $session_name = '';
        $class_id_key = 'class_id';
        $class_id = '';
        $class_name = '';

        if (isset($_GET[$class_id_key]) && is_numeric($_GET[$class_id_key])) {
            $class_id = intval($_GET[$class_id_key]);
            $class_post = get_post($class_id);

            if ($class_post && $class_post->post_type === 'usctdp-class') {
                $class_id = $class_id;
                $class_name = $class_post->post_title;
                $parent = get_field('session', $class_id);
                $session_id = $parent->ID;
                $session_name = $parent->post_title;
            }
        } else if (isset($_GET[$session_id_key]) && is_numeric($_GET[$session_id_key])) {
            $session_id = intval($_GET[$session_id_key]);
            $session_post = get_post($session_id);
            if ($session_post && $session_post->post_type === 'usctdp-session') {
                $session_id = $session_id;
                $session_name = $session_post->post_title;
            }
        }

        return [
            'session_id' => $session_id,
            'session_name' => $session_name,
            'class_id' => $class_id,
            'class_name' => $class_name,
        ];
    }

    public function load_classes_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['datatable_search', 'select2_search'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        wp_localize_script($this->usctdp_script_id('classes'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_rosters_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['datatable_search', 'select2_search', 'gen_roster'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->extract_session_and_class_context();
        $js_data['preloaded_session_id'] = $context['session_id'];
        $js_data['preloaded_session_name'] = $context['session_name'];
        $js_data['preloaded_class_id'] = $context['class_id'];
        $js_data['preloaded_class_name'] = $context['class_name'];

        wp_localize_script($this->usctdp_script_id('rosters'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_register_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['select2_search', 'class_qualification'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->extract_session_and_class_context();
        $js_data['preloaded_session_id'] = $context['session_id'];
        $js_data['preloaded_session_name'] = $context['session_name'];
        $js_data['preloaded_class_id'] = $context['class_id'];
        $js_data['preloaded_class_name'] = $context['class_name'];

        wp_localize_script($this->usctdp_script_id('register'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_families_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['datatable_search', 'select2_search'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        wp_localize_script($this->usctdp_script_id('families'), 'usctdp_mgmt_admin', $js_data);
    }

    public function show_admin_notice()
    {
        $unique_token = sanitize_key($_GET['usctdp_token'] ?? '');
        if (empty($unique_token)) {
            return;
        }

        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix  . '_' . $unique_token;
        if ($notice = get_transient($transient_key)) {
            $class = 'notice-' . sanitize_html_class($notice['type']);
            $message = esc_html($notice['message']);
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . $message . '</p></div>';
            delete_transient($transient_key);
        }
    }

    public function new_session_handler()
    {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers['new_session'];
        $nonce_name = $post_handler['nonce_name'];
        $nonce_action = $post_handler['nonce_action'];

        $unique_token = bin2hex(random_bytes(8));
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
        $redirect_url = add_query_arg(
            'usctdp_token',
            $unique_token,
            $this->get_redirect_url('usctdp-admin-main')
        );

        $request_completed = false;
        $created_ids = [];
        $transient_data = null;

        try {
            if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
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
                    if (!update_field($key, sanitize_text_field($value), $session_id)) {
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
                        if (!update_field($key, sanitize_text_field($value), $class_id)) {
                            throw new ErrorException('Failed to update class field: ' . $key);
                        }
                    }
                    if (!update_field('field_usctdp_class_session', $session_id, $class_id)) {
                        throw new ErrorException('Failed to update class session field with: ' . $session_id);
                    }
                }
            }
            $message = "Session created successfully!";
            $request_completed = true;
            $transient_data = [
                'type' => 'success',
                'message' => $message
            ];
        } catch (Exception $e) {
            $transient_data = [
                'type' => 'error',
                'message' => $message
            ];
            Usctdp_Mgmt_Logger::getLogger()->log_error($message);
            $post_data = print_r($_POST, true);
            Usctdp_Mgmt_Logger::getLogger()->log_error($post_data);
        } finally {
            if (!$request_completed) {
                foreach ($created_ids as $id) {
                    if (!wp_delete_post($id, true)) {
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
            set_transient($transient_key, $transient_data, 10);
            wp_safe_redirect($redirect_url);
            wp_die();
        }
    }

    function registration_handler()
    {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers['registration'];
        $nonce_name = $post_handler['nonce_name'];
        $nonce_action = $post_handler['nonce_action'];

        $unique_token = bin2hex(random_bytes(8));
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
        $redirect_url = add_query_arg(
            'usctdp_token',
            $unique_token,
            $this->get_redirect_url('usctdp-admin-rosters')
        );

        $request_completed = false;
        $created_ids = [];
        $transient_data = null;

        try {
            if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
                throw new ErrorException('Request verification failed.');
            }

            if (!isset($_POST['class_id'])) {
                throw new ErrorException('Class ID not found.');
            }
            if (!isset($_POST['student_id'])) {
                throw new ErrorException('Student ID not found.');
            }

            $class_id = $_POST['class_id'];
            $student_id = $_POST['student_id'];
            $registration_id = wp_insert_post([
                'post_title' => '',
                'post_type' => 'usctdp-registration',
                'post_status' => 'publish'
            ]);
            if (is_wp_error($registration_id)) {
                throw new ErrorException('Error creating registration: ' . $registration_id->get_error_message());
            }

            if (!update_field('field_usctdp_registration_class', $class_id, $registration_id)) {
                throw new ErrorException('Failed to update class field with: ' . $class_id);
            }
            if (!update_field('field_usctdp_registration_student', $student_id, $registration_id)) {
                throw new ErrorException('Failed to update student field with: ' . $student_id);
            }
            $redirect_url = add_query_arg(
                'class_id',
                $class_id,
                $redirect_url
            );

            $message = "Registration created successfully!";
            $request_completed = true;
            $transient_data = [
                'type' => 'success',
                'message' => $message
            ];
        } catch (Exception $e) {
            $transient_data = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        } finally {
            if (!$request_completed) {
                foreach ($created_ids as $id) {
                    if (!wp_delete_post($id, true)) {
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
            set_transient($transient_key, $transient_data, 10);
            wp_safe_redirect($redirect_url);
            wp_die();
        }
    }


    function ajax_get_class_qualification()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['get_class_qualification'];
        if (! check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 400);
        }

        $class_id = isset($_GET['class_id']) ? sanitize_text_field($_GET['class_id']) : '';
        $student_id = isset($_GET['student_id']) ? sanitize_text_field($_GET['student_id']) : '';
        $capacity = get_field('capacity', $class_id);
        $cap_query = new WP_Query([
            'post_type'      => 'usctdp-registration',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key' => 'class',
                    'value' => $class_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ]
            ],
        ]);
        $found_posts = 0;
        if ($cap_query->have_posts()) {
            $found_posts = $cap_query->found_posts;
        }
        wp_reset_postdata();

        $student_query = new WP_Query([
            'post_type'      => 'usctdp-registration',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key' => 'student',
                    'value' => $student_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ],
                [
                    'key' => 'class',
                    'value' => $class_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ]
            ]
        ]);
        $student_registered = false;
        if ($student_query->have_posts()) {
            $student_registered = true;
        }
        wp_reset_postdata();
        wp_send_json_success([
            'capacity' => $capacity,
            'registered' => $found_posts,
            'student_registered' => $student_registered
        ]);
        wp_die();
    }

    private function get_class_registrations($class_id)
    {
        $args = array(
            'post_type'      => 'usctdp-registration',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key' => 'class',
                    'value' => $class_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ]
            ]
        );
        $query = new WP_Query($args);
        $result = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $acf_fields = get_fields(get_the_ID());
                $output_fields = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                );
                foreach ($acf_fields as $field_name => $field_value) {
                    $output_fields[$field_name] = $field_value;
                }
                $result[] = $output_fields;
            }
            wp_reset_postdata();
        }
        return $result;
    }

    private function create_google_client()
    {
        $refreshToken = get_option('usctdp_google_refresh_token');
        if (empty($refreshToken)) {
            throw new ErrorException('No refresh token found. User must re-authorize.');
        }

        $client = new Client();
        $client->setClientId(env('GOOGLE_DOCS_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_DOCS_CLIENT_SECRET'));
        $client->fetchAccessTokenWithRefreshToken($refreshToken);
        return $client;
    }

    private function text_replace($search, $replace)
    {
        return new Google_Service_Docs_Request([
            'replaceAllText' => [
                'containsText' => [
                    'text' => $search,
                    'matchCase' => true
                ],
                'replaceText' => $replace
            ]
        ]);
    }

    private function age_from_birthdate($birthdate)
    {
        $birthDate = new DateTime($birthdate);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
        return $age;
    }

    public function ajax_gen_roster()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['gen_roster'];
        if (! check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $class_id = isset($_GET['class_id']) ? sanitize_text_field($_GET['class_id']) : '';
        if (empty($class_id)) {
            wp_send_json_error('Class ID is required.', 400);
        }

        $class = get_post($class_id);
        if (!$class) {
            wp_send_json_error('Class with ID "' . $class_id . '" not found.', 404);
        }

        $sourceDocId = env('GOOGLE_DOC_ROSTER_TEMPLATE_ID');
        $destinationFolderId = env('GOOGLE_DRIVE_FOLDER_ID');
        if (empty($sourceDocId) || empty($destinationFolderId)) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Server is not configured for Google Docs.'
            );
            wp_send_json_error('Server configuration error. Please contact the administrator.', 500);
        }

        $class_fields = get_fields($class_id);
        $registrations = $this->get_class_registrations($class_id);

        try {
            $newFileName = 'Roster: ' . $class->post_title;
            $client = $this->create_google_client();
            $driveService = new Drive($client);
            $docsService = new Docs($client);

            $newFileMetadata = new DriveFile([
                'name' => $newFileName,
                'parents' => [$destinationFolderId]
            ]);
            $copiedFile = $driveService->files->copy($sourceDocId, $newFileMetadata);
            $copyId = $copiedFile->getId();

            $requests = [
                $this->text_replace('{{session_name}}',  get_field('session_name', $class_fields['session'])),
                $this->text_replace('{{day_of_week}}', $class_fields['day_of_week']),
                $this->text_replace('{{start_time}}', $class_fields['start_time']),
                $this->text_replace('{{end_time}}', $class_fields['end_time']),
                $this->text_replace('{{level}}', $class_fields['level']),
                $this->text_replace('{{limit}}', $class_fields['capacity']),
                $this->text_replace('{{age_group}}', get_field('age_group', $class_fields['course'])),
                $this->text_replace('{{start_date}}', isset($class_fields['start_date']) ? $class_fields['start_date'] : ''),
                $this->text_replace('{{end_date}}', isset($class_fields['end_date']) ? $class_fields['end_date'] : ''),
            ];

            if (isset($class_fields['instructors'])) {
                $instructor_first_names = array_map(function ($instructor) {
                    return $instructor['first_name'];
                }, $class_fields['instructors']);
                $requests[] = $this->text_replace('{{instructors}}', implode(', ', $instructor_first_names));
            } else {
                $requests[] = $this->text_replace('{{instructors}}', '');
            }

            $student_data = [];
            foreach ($registrations as $registration) {
                $student_id = $registration['student'];
                $student_fields = get_fields($student_id);
                $student_data[] = [
                    'last' => $student_fields['last_name'],
                    'first' => $student_fields['first_name'],
                    'age' => strval($this->age_from_birthdate($student_fields['birth_date'])),
                    'level' => "",
                    'phone' => ""
                ];
            }

            $index = 1;
            foreach ($student_data as $student) {
                $requests[] = $this->text_replace('{{att_' . $index . '}}', "____" . $index);
                $requests[] = $this->text_replace('{{last_' . $index . '}}', $student['last']);
                $requests[] = $this->text_replace('{{first_' . $index . '}}', $student['first']);
                $requests[] = $this->text_replace('{{age_' . $index . '}}', $student['age']);
                $requests[] = $this->text_replace('{{level_' . $index . '}}', $student['level']);
                $requests[] = $this->text_replace('{{phone_' . $index . '}}', $student['phone']);
                $index += 1;
            }

            for ($i = $index; $i <= 20; $i++) {
                $requests[] = $this->text_replace('{{att_' . $i . '}}', "");
                $requests[] = $this->text_replace('{{last_' . $i . '}}', "");
                $requests[] = $this->text_replace('{{first_' . $i . '}}', "");
                $requests[] = $this->text_replace('{{level_' . $i . '}}', "");
                $requests[] = $this->text_replace('{{age_' . $i . '}}', "");
                $requests[] = $this->text_replace('{{phone_' . $i . '}}', "");
            }

            $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);
            $docsService->documents->batchUpdate($copyId, $batchUpdateRequest);
            wp_send_json_success([
                'message' => 'Roster generated successfully',
                'doc_id' => $copyId
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Error generating roster: ' . $e->getMessage()
            );
            wp_send_json_error('An unexpected server error occurred during roster generation.', 500);
        }
    }

    function ajax_select2_search()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_search'];
        if (! check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $post_id = isset($_GET['p']) ? sanitize_text_field($_GET['p']) : '';
        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        $include_acf = isset($_GET['acf']) ? sanitize_text_field($_GET['acf'] === 'true') : false;
        $results = array();
        $args = [
            'post_type' => $post_type,
        ];

        if ($post_id) {
            $args['p'] = $post_id;
            $args['posts_per_page'] = 1;
        } else {
            $args['s'] = $search_term;
            $args['posts_per_page'] = 10;
        }

        $meta_query = [];
        if (isset($_GET["filter"])) {
            $meta_query = [
                'relation' => 'AND'
            ];
            foreach ($_GET["filter"] as $key => $filter) {
                $meta_query[] = [
                    'key'     => $key,
                    'value'   => sanitize_text_field($filter['value']),
                    'compare' => sanitize_text_field($filter['compare']),
                    'type'    => sanitize_text_field($filter['type'])
                ];
            }
        }
        $args['meta_query'] = $meta_query;
        $args['orderby'] = 'title';
        $args['order'] = 'ASC';
        $query = new WP_Query($args);
        $found_posts = 0;

        if ($query->have_posts()) {
            $found_posts = $query->found_posts;
            while ($query->have_posts()) {
                $query->the_post();
                $result = array(
                    'id'   => get_the_ID(),
                    'text' => html_entity_decode(get_the_title())
                );
                if ($include_acf) {
                    $result['acf'] = get_fields(get_the_ID());
                }
                $results[] = $result;
            }
        }
        wp_reset_postdata();
        wp_send_json(array('items' => $results, 'found_posts' => $found_posts));
        wp_die();
    }

    public function ajax_datatable_search()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['datatable_search'];
        if (! check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $paged = ($start / $length) + 1;

        $meta_query = [];
        if (isset($_POST["filter"])) {
            $meta_query = [
                'relation' => 'AND'
            ];
            foreach ($_POST["filter"] as $key => $filter) {
                $meta_query[] = [
                    'key'     => $key,
                    'value'   => sanitize_text_field($filter['value']),
                    'compare' => sanitize_text_field($filter['compare']),
                    'type'    => sanitize_text_field($filter['type'])
                ];
            }
        }

        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $length,
            'paged'          => $paged,
            'no_found_rows'  => false,
            'meta_query'     => $meta_query,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if (! empty($search_val)) {
            $args['s'] = $search_val;
        }

        $query = new WP_Query($args);
        $data_output = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $acf_fields = get_fields(get_the_ID());
                $output_fields = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'edit' => get_edit_post_link(get_the_ID()),
                    'permalink' => get_permalink(),
                );
                foreach ($acf_fields as $field_name => $field_value) {
                    $output_fields[$field_name] = $field_value;
                }
                $data_output[] = $output_fields;
            }
            wp_reset_postdata();
        }

        $response = array(
            "draw"            => $draw,
            "recordsTotal"    => $query->found_posts,
            "recordsFiltered" => $query->found_posts,
            "data"            => $data_output,
        );
        wp_send_json($response);
        wp_die();
    }
}
