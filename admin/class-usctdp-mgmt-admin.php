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

define('USCTDP_NEW_SESSION_ACTION', 'usctdp_admin_new_session');

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
        if ($screen->base == 'usctdp-admin_page_usctdp-admin-new-session') {
            wp_enqueue_style(
                $this->plugin_name . 'new-session-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-new-session.css',
                [],
                $this->version,
                'all'
            );
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-classes') {
            wp_enqueue_style(
                $this->plugin_name . 'admin-classes-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin-classes.css',
                [],
                $this->version,
                'all'
            );
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-families') {
            wp_enqueue_style(
                $this->plugin_name . 'admin-families-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin-families.css',
                [],
                $this->version,
                'all'
            );
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-rosters') {
            wp_enqueue_style(
                $this->plugin_name . 'admin-rosters-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin-rosters.css',
                [],
                $this->version,
                'all'
            );
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-register') {
            wp_enqueue_style(
                $this->plugin_name . 'admin-register-css',
                plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin-register.css',
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

        $screen = get_current_screen();
        if ($screen->base == 'usctdp-admin_page_usctdp-admin-new-session') {
            wp_enqueue_script(
                $this->plugin_name . 'new-session-js',
                plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-new-session.js',
                ['jquery', 'acf-input'],
                $this->version,
                true
            );
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-classes') {
            wp_enqueue_script(
                $this->plugin_name . 'admin-classes-js',
                plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin-classes.js',
                [
                    'jquery',
                    'acf-input',
                    $this->plugin_name . 'external-flatpickr-js',
                    $this->plugin_name . 'external-datatables-js'
                ],
                $this->version,
                true
            );

            wp_localize_script($this->plugin_name . 'admin-classes-js', 'usctdp_mgmt_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'class_action' => 'usctdp_fetch_classes',
                'class_nonce'  => wp_create_nonce('usctdp_class_search_nonce'),
                'search_action' => 'my_select2_post_search',
                'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce')
            ]);
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-families') {
            wp_enqueue_script(
                $this->plugin_name . 'admin-families-js',
                plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin-families.js',
                ['jquery', 'acf-input'],
                $this->version,
                true
            );
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-rosters') {
            wp_enqueue_script(
                $this->plugin_name . 'admin-rosters-js',
                plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin-rosters.js',
                ['jquery', 'acf-input'],
                $this->version,
                true
            );

            wp_localize_script($this->plugin_name . 'admin-rosters-js', 'usctdp_mgmt_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'search_action' => 'my_select2_post_search',
                'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce'),
                'datatable_action' => 'fetch_posts_for_datatable',
                'datatable_nonce' => wp_create_nonce('usctdp_fetch_posts_for_datatable_nonce')
            ]);
        } else if ($screen->base == 'usctdp-admin_page_usctdp-admin-register') {
            wp_enqueue_script(
                $this->plugin_name . 'admin-register-js',
                plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin-register.js',
                ['jquery', 'acf-input'],
                $this->version,
                true
            );
            wp_localize_script($this->plugin_name . 'admin-register-js', 'usctdp_mgmt_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'search_action' => 'my_select2_post_search',
                'search_nonce'  => wp_create_nonce('usctdp_class_search2_nonce')
            ]);
        }
    }

    public function get_all_staff()
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


    private function get_redirect_url($page_slug)
    {
        return admin_url('admin.php?page=' . $page_slug);
    }

    public function add_admin_menu()
    {
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
        $classes_hook = add_submenu_page(
            'usctdp-admin-main',
            'Classes',
            'Classes',
            'manage_options',
            'usctdp-admin-classes',
            [$this, 'fetch_classes_page']
        );
        $families_hook = add_submenu_page(
            'usctdp-admin-main',
            'Families',
            'Families',
            'manage_options',
            'usctdp-admin-families',
            [$this, 'fetch_families_page']
        );
        $rosters_hook = add_submenu_page(
            'usctdp-admin-main',
            'Rosters',
            'Rosters',
            'manage_options',
            'usctdp-admin-rosters',
            [$this, 'fetch_rosters_page']
        );
        $register_hook = add_submenu_page(
            'usctdp-admin-main',
            'Register',
            'Register',
            'manage_options',
            'usctdp-admin-register',
            [$this, 'fetch_register_page']
        );
        add_action('load-' . $new_session_hook, [$this, 'load_new_session_page']);
        add_action('load-' . $classes_hook, [$this, 'load_classes_page']);
        add_action('load-' . $families_hook, [$this, 'load_families_page']);
        add_action('load-' . $rosters_hook, [$this, 'load_rosters_page']);
        add_action('load-' . $register_hook, [$this, 'load_register_page']);
    }

    private function echo_admin_page($path)
    {
        if (file_exists($path)) {
            require_once($path);
        } else {
            echo '<div class="notice notice-error"><p>Admin view file not found.</p></div>';
        }
    }

    public function fetch_main_page()
    {
        $admin_dir = plugin_dir_path(__FILE__);
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-main.php';
        $this->echo_admin_page($main_display);
    }

    public function fetch_classes_page()
    {
        $admin_dir = plugin_dir_path(__FILE__);
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-classes.php';
        $this->echo_admin_page($main_display);
    }

    public function fetch_families_page()
    {
        $admin_dir = plugin_dir_path(__FILE__);
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-families.php';
        $this->echo_admin_page($main_display);
    }

    public function fetch_rosters_page()
    {
        $admin_dir = plugin_dir_path(__FILE__);
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-rosters.php';
        $this->echo_admin_page($main_display);
    }

    public function fetch_register_page()
    {
        $admin_dir = plugin_dir_path(__FILE__);
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-register.php';
        $this->echo_admin_page($main_display);
    }

    public function fetch_new_session_page()
    {
        $admin_dir = plugin_dir_path(__FILE__);
        $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-new-session.php';
        $this->echo_admin_page($main_display);
    }

    public function load_new_session_page()
    {
        acf_form_head();
    }

    public function load_classes_page()
    {
        acf_form_head();
    }

    public function load_families_page()
    {
        acf_form_head();
    }

    public function load_rosters_page()
    {
        acf_form_head();
    }

    public function load_register_page()
    {
        acf_form_head();
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
            $this->get_redirect_url('usctdp-admin-new-session')
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

    function my_select2_ajax_post_search()
    {
        if (! check_ajax_referer('usctdp_class_search2_nonce', 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.');
        }

        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        $filters = [];
        foreach ($_GET as $key => $value) {
            if (str_starts_with($key, 'filter_')) {
                $filters[preg_replace("/^filter_/", "", $key)] = sanitize_text_field($value);
            }
        }
        $results = array();
        $args = array(
            's'              => $search_term,
            'posts_per_page' => 10,
            'post_type'      => array($post_type),
        );
        if ($filters) {
            $args['meta_query'] = [
                'relation' => 'AND',
            ];
            foreach ($filters as $key => $value) {
                error_log($key . ': ' . $value);
                $args['meta_query'][] = [
                    'key' => $key,
                    'value' => $value,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ];
            }
        }
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = array(
                    'id'   => get_the_ID(),
                    'text' => html_entity_decode(get_the_title())
                );
            }
        }
        wp_reset_postdata();
        wp_send_json(array('items' => $results));
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
        error_log(print_r($args, true));

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

        // Add Search Parameter
        if (! empty($search_val)) {
            // DataTables search value is applied to 's'
            $args['s'] = $search_val;
        }

        // 4. Run the Query
        $query = new WP_Query($args);

        // 5. Format Data for DataTables
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
}
