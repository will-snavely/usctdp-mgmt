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

    private function add_usctdp_submenu($page_slug, $title, $menu_slug = null, $load_callback = null)
    {
        $function_slug = str_replace('-', '_', $page_slug);
        $callback = [$this, 'fetch_' . $function_slug . '_page'];
        $capability = 'manage_options';
        $menu_slug = $menu_slug ? $menu_slug : 'usctdp-admin-' . $page_slug;
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

    public function add_admin_menu()
    {
        add_menu_page(
            'USCTDP Admin',
            'USCTDP Admin',
            'manage_options',
            'usctdp-admin-main',
            function () {}
        );

        // Override the slug on the first menu item
        $this->add_usctdp_submenu('classes', 'Classes', 'usctdp-admin-main', [$this, 'load_classes_page']);
        $this->add_usctdp_submenu('rosters', 'Rosters', null, [$this, 'load_rosters_page']);
        $this->add_usctdp_submenu('register', 'Registration', null, [$this, 'load_register_page']);
        $this->add_usctdp_submenu('new-session', 'New Session');
        $this->add_usctdp_submenu('families', 'Families', null, [$this, 'load_families_page']);
    }

    private function echo_admin_page($path)
    {
        if (file_exists($path)) {
            require_once($path);
        } else {
            echo '<div class="notice notice-error"><p>Admin view file not found.</p></div>';
        }
    }

    public function load_classes_page()
    {
        wp_localize_script($this->usctdp_script_id('classes'), 'usctdp_mgmt_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'class_action' => 'usctdp_fetch_classes',
            'class_nonce'  => wp_create_nonce('usctdp_class_search_nonce'),
            'search_action' => 'my_select2_post_search',
            'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce')
        ]);
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
                $parent = get_field('parent_session', $class_id);
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

    public function load_rosters_page()
    {
        $context = $this->extract_session_and_class_context();
        wp_localize_script($this->usctdp_script_id('rosters'), 'usctdp_mgmt_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_action' => 'my_select2_post_search',
            'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce'),
            'datatable_action' => 'fetch_posts_for_datatable',
            'datatable_nonce' => wp_create_nonce('usctdp_fetch_posts_for_datatable_nonce'),
            'preloaded_session_id' => $context['session_id'],
            'preloaded_session_name' => $context['session_name'],
            'preloaded_class_id' => $context['class_id'],
            'preloaded_class_name' => $context['class_name']
        ]);
    }

    public function load_register_page()
    {
        $context = $this->extract_session_and_class_context();
        wp_localize_script($this->usctdp_script_id('register'), 'usctdp_mgmt_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_action' => 'my_select2_post_search',
            'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce'),
            'qualification_action' => 'usctdp_class_qualification_data',
            'qualification_nonce' => wp_create_nonce('usctdp_class_qualification_data_nonce'),
            'preloaded_session_id' => $context['session_id'],
            'preloaded_session_name' => $context['session_name'],
            'preloaded_class_id' => $context['class_id'],
            'preloaded_class_name' => $context['class_name']
        ]);
    }

    public function load_families_page()
    {
        wp_localize_script($this->usctdp_script_id('families'), 'usctdp_mgmt_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_action' => 'my_select2_post_search',
            'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce'),
            'datatable_action' => 'fetch_posts_for_datatable',
            'datatable_nonce' => wp_create_nonce('usctdp_fetch_posts_for_datatable_nonce')
        ]);
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
                    if (!update_field('field_usctdp_class_parent', $session_id, $class_id)) {
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
            exit;
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
            exit;
        }
    }

    function my_select2_ajax_post_search()
    {
        if (! check_ajax_referer('usctdp_class_search2_nonce', 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.');
        }

        $post_id = isset($_GET['post_id']) ? sanitize_text_field($_GET['post_id']) : '';
        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        $include_acf = isset($_GET['acf']) ? sanitize_text_field($_GET['acf'] === 'true') : false;
        $filters = [];
        foreach ($_GET as $key => $value) {
            if (str_starts_with($key, 'filter_')) {
                $filters[preg_replace("/^filter_/", "", $key)] = sanitize_text_field($value);
            }
        }
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

        if ($filters) {
            $args['meta_query'] = [
                'relation' => 'AND',
            ];
            foreach ($filters as $key => $value) {
                $args['meta_query'][] = [
                    'key' => $key,
                    'value' => $value,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ];
            }
        }
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
    }

    function ajax_get_class_qualification_data()
    {
        if (! check_ajax_referer('usctdp_class_qualification_data_nonce', 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.');
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
        wp_send_json(array('capacity' => $capacity, 'registered' => $found_posts, 'student_registered' => $student_registered));
    }

    public function fetch_posts_for_datatable()
    {
        if (! check_ajax_referer('usctdp_fetch_posts_for_datatable_nonce', 'security', false)) {
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
            'orderby' => 'meta_value_num',
            'order'   => 'ASC',
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
                $fields = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'edit' => get_edit_post_link(get_the_ID()),
                    'permalink' => get_permalink(),
                );
                foreach ($acf_fields as $key => $value) {
                    $fields[$key] = $value;
                }
                $data_output[] = $fields;
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
    }

    public function fetch_classes_handler_datatables()
    {
        if (! check_ajax_referer('usctdp_class_search_nonce', 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $session_filter = isset($_POST['session_filter']) ? sanitize_text_field($_POST['session_filter']) : '';
        $paged = ($start / $length) + 1;
        $comparison_date = date("Ymd");
        $staff = $this->get_all_staff();

        $meta_query = [
            'relation' => 'AND',
            [
                'key'     => 'end_date',
                'value'   => $comparison_date,
                'compare' => '>=',
                'type'    => 'DATE',
            ]
        ];

        if (! empty($session_filter)) {
            $meta_query[] = [
                'key'     => 'parent_session',
                'value'   => $session_filter,
                'compare' => '=',
                'type'    => 'NUMERIC'
            ];
        }

        $args = array(
            'post_type'      => 'usctdp-class',
            'posts_per_page' => $length, // Posts per page comes from DataTables
            'paged'          => $paged,   // Page number is calculated from start/length

            // Always include the argument to fetch total posts before applying pagination
            'no_found_rows'  => false,

            'meta_query'     => $meta_query,
            'orderby' => 'meta_value_num',
            'order'   => 'ASC',
        );

        if (! empty($search_val)) {
            $args['s'] = $search_val;
        }

        $query = new WP_Query($args);
        $data_output = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $id = get_the_ID();
                $session = get_field('field_usctdp_class_parent');
                $level = get_field('field_usctdp_class_level');
                $raw_start = get_field('field_usctdp_class_start_date');
                $raw_end = get_field('field_usctdp_class_end_date');
                $capacity = get_field('field_usctdp_class_capacity');
                $instructor_ids = get_field('field_usctdp_class_instructors');
                $session_title = get_post_field('post_title', $session);

                $start_date = $raw_start ? DateTime::createFromFormat('Ymd', $raw_start)->format('m/d/Y') : '';
                $end_date = $raw_end ? DateTime::createFromFormat('Ymd', $raw_end)->format('m/d/Y') : '';
                $instructors = array_map(function ($id) use ($staff) {
                    return $staff[$id]['last_name'];
                }, $instructor_ids ? $instructor_ids : []);

                $data_output[] = array(
                    'name' => get_the_title(),
                    'level' => esc_html($level),
                    'start_date' => esc_html($start_date),
                    'end_date' => esc_html($end_date),
                    'capacity' => esc_html($capacity),
                    'session' => esc_html($session_title),
                    'instructors' => $instructors,
                    'id' => $id,
                );
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
    }

    private function get_all_staff()
    {
        $args = array(
            'post_type'      => 'usctdp-staff',
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);
        $results = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[get_the_ID()] = [
                    'first_name' => get_field('field_usctdp_staff_first_name'),
                    'last_name' => get_field('field_usctdp_staff_last_name'),
                    'edit_link' => get_edit_post_link(),
                ];
            }
        }
        wp_reset_postdata();
        return $results;
    }
}
