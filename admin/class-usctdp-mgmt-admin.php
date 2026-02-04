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

class Web_Request_Exception extends Exception {}

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
    ];

    public static $post_handlers = [
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
        'datatable_balances' => [
            'action' => 'datatable_balances',
            'nonce' => 'datatable_balances_nonce',
            'callback' => 'ajax_datatable_balances'
        ],
        'datatable_balances_detail' => [
            'action' => 'datatable_balances_detail',
            'nonce' => 'datatable_balances_detail_nonce',
            'callback' => 'ajax_datatable_balances_detail'
        ],
        'select2_search' => [
            'action' => 'select2_search',
            'nonce' => 'select2_search_nonce',
            'callback' => 'ajax_select2_search'
        ],
        'select2_session_search' => [
            'action' => 'select2_session_search',
            'nonce' => 'select2_session_search_nonce',
            'callback' => 'ajax_select2_session_search'
        ],
        'session_rosters' => [
            'action' => 'session_rosters',
            'nonce' => 'session_rosters_nonce',
            'callback' => 'ajax_session_rosters'
        ],
        'session_rosters_datatable' => [
            'action' => 'session_rosters_datatable',
            'nonce' => 'session_rosters_datatable_nonce',
            'callback' => 'ajax_session_rosters_datatable'
        ],
        'toggle_session_active' => [
            'action' => 'toggle_session_active',
            'nonce' => 'toggle_session_active_nonce',
            'callback' => 'ajax_toggle_session_active'
        ],
        'select2_student_search' => [
            'action' => 'select2_student_search',
            'nonce' => 'select2_student_search_nonce',
            'callback' => 'ajax_select2_student_search'
        ],
        'student_datatable' => [
            'action' => 'student_datatable',
            'nonce' => 'student_datatable_nonce',
            'callback' => 'ajax_student_datatable'
        ],
        'class_datatable' => [
            'action' => 'class_datatable',
            'nonce' => 'class_datatable_nonce',
            'callback' => 'ajax_class_datatable'
        ],
        'registrations_datatable' => [
            'action' => 'registrations_datatable',
            'nonce' => 'registrations_datatable_nonce',
            'callback' => 'ajax_registrations_datatable'
        ],
        'registration_history_datatable' => [
            'action' => 'registration_history_datatable',
            'nonce' => 'registration_history_datatable_nonce',
            'callback' => 'ajax_registration_history_datatable'
        ],

        'select2_family_search' => [
            'action' => 'select2_family_search',
            'nonce' => 'select2_family_search_nonce',
            'callback' => 'ajax_select2_family_search'
        ],
        'save_family_fields' => [
            'action' => 'save_family_fields',
            'nonce' => 'save_family_fields_nonce',
            'callback' => 'ajax_save_family_fields'
        ],
        'save_family_notes' => [
            'action' => 'save_family_notes',
            'nonce' => 'save_family_notes_nonce',
            'callback' => 'ajax_save_family_notes'
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
        ],
        'select2_class_search' => [
            'action' => 'select2_class_search',
            'nonce' => 'select2_class_search_nonce',
            'callback' => 'ajax_select2_class_search'
        ],
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
    public function enqueue_scripts() {}

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
        wp_enqueue_script(
            $this->plugin_name . 'primary-js',
            plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin.js',
            ['jquery', 'acf-input'],
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

        $deps = $dependencies ? $dependencies : [
            'jquery',
            'acf-input',
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
        wp_enqueue_style(
            $this->plugin_name . 'primary-css',
            plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin.css',
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
        $redirect_url = admin_url('admin.php?page=usctdp-admin-main');
        if (!isset($_GET['page']) || $_GET['page'] !== 'usctdp-admin-main') {
            return;
        }

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
                    update_option('usctdp_google_refresh_token_timestamp', date('Y-m-d H:i:s'));
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
        add_action('load-' . $main_menu_page, function () {
            acf_form_head();
            $this->enqueue_usctdp_page_script('main');
            $this->enqueue_usctdp_page_style('main');
            $js_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'family_url' => admin_url('admin.php?page=usctdp-admin-families')
            ];
            $handlers = [
                'select2_search',
                'datatable_search',
                'gen_roster',
                'select2_session_search',
                'select2_family_search',
                'session_rosters',
                'toggle_session_active'
            ];
            foreach ($handlers as $key) {
                $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
                $js_data[$key . "_action"] = $handler['action'];
                $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
            }
            wp_localize_script($this->usctdp_script_id('main'), 'usctdp_mgmt_admin', $js_data);
        });

        // Override the slug on the first menu item
        $this->add_usctdp_submenu('classes', 'Classes', [$this, 'load_classes_page']);
        $this->add_usctdp_submenu('families', 'Families', [$this, 'load_families_page']);
        $this->add_usctdp_submenu('session-rosters', 'Session Rosters', [$this, 'load_session_rosters_page']);
        $this->add_usctdp_submenu('clinic-rosters', 'Clinic Rosters', [$this, 'load_clinic_rosters_page']);
        $this->add_usctdp_submenu('register', 'Registration', [$this, 'load_register_page']);
        $this->add_usctdp_submenu('history', 'Registration History', [$this, 'load_history_page']);
        $this->add_usctdp_submenu('balances', 'Outstanding Balances', [$this, 'load_balances_page']);
    }

    public function settings_init() {}

    public function usctdp_mgmt_sanitize_settings($input) {}

    private function echo_admin_page($path)
    {
        if (file_exists($path)) {
            require_once($path);
        } else {
            echo '<div class="notice notice-error"><p>Admin view file not found.</p></div>';
        }
    }

    private function db_id_query($query_class, $id)
    {
        $result = [];
        $query = new $query_class([
            'id' => $id,
            'number' => 1
        ]);
        if (!empty($query->items)) {
            foreach ($query->items[0] as $key => $value) {
                if ((!str_contains($key, ":protected"))) {
                    $result[$key] = $value;
                }
            }
            return $result;
        }
        return null;
    }

    /**
     * 
     * @param array $args 
     * @return array 
     */
    private function load_page_context($expected_params = [])
    {
        $result = [];
        $query_map = [
            'session_id' => function ($id) {
                return $this->db_id_query('Usctdp_Mgmt_Session_Query', $id);
            },
            'class_id' => function ($id) {
                $class_query = new Usctdp_Mgmt_Clinic_Class_Query([]);
                $result = $class_query->get_class_data([
                    'id' => $id,
                    'number' => 1
                ]);
                if (!empty($result['data'])) {
                    return $result['data'][0];
                }
                return null;
            },
            'student_id' => function ($id) {
                $student_query = new Usctdp_Mgmt_Student_Query([]);
                $result = $student_query->get_student_data([
                    'id' => $id,
                    'number' => 1
                ]);
                if (!empty($result['data'])) {
                    return $result['data'][0];
                }
                return null;
            },
            'family_id' => function ($id) {
                return $this->db_id_query('Usctdp_Mgmt_Family_Query', $id);
            }
        ];
        foreach ($expected_params as $key) {
            if (isset($_GET[$key]) && is_numeric($_GET[$key]) && isset($query_map[$key])) {
                $id = intval($_GET[$key]);
                $result[$key] = [
                    $id => $query_map[$key]($id)
                ];
            }
        }
        return $result;
    }

    public function load_balances_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['datatable_balances', 'datatable_balances_detail'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        wp_localize_script($this->usctdp_script_id('balances'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_classes_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['datatable_search', 'select2_search', 'class_datatable', 'select2_session_search'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        wp_localize_script($this->usctdp_script_id('classes'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_clinic_rosters_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
            'select2_session_search',
            'select2_class_search',
            'gen_roster',
            'registrations_datatable'
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['class_id']);
        $js_data['preload'] = $context;
        wp_localize_script($this->usctdp_script_id('clinic-rosters'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_session_rosters_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
            'gen_roster',
            'session_rosters_datatable'
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        wp_localize_script($this->usctdp_script_id('session-rosters'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_register_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
            'select2_search',
            'class_qualification',
            'datatable_search',
            'registrations_datatable',
            'select2_family_search',
            'select2_student_search',
            'select2_session_search',
            'select2_class_search'
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['class_id', 'student_id']);
        $js_data['preload'] = $context;
        wp_localize_script($this->usctdp_script_id('register'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_history_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
            'select2_family_search',
            'select2_student_search',
            'registration_history_datatable'
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['class_id', 'student_id']);
        $js_data['preload'] = $context;
        wp_localize_script($this->usctdp_script_id('history'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_families_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
            'save_family_notes',
            'save_family_fields',
            'student_datatable',
            'select2_family_search'
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
            $context = $this->load_page_context(['family_id']);
            $js_data['preload'] = $context;
        }
        wp_localize_script($this->usctdp_script_id('families'), 'usctdp_mgmt_admin', $js_data);
    }

    public function show_admin_notice()
    {
        $unique_token = sanitize_key($_GET['usctdp_token'] ?? '');
        if (empty($unique_token)) {
            return;
        }

        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
        if ($notice = get_transient($transient_key)) {
            $class = 'notice-' . sanitize_html_class($notice['type']);
            $message = esc_html($notice['message']);
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . $message . '</p></div>';
            delete_transient($transient_key);
        }
    }

    function convertToCents(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^\d*(\.\d{1,2})?$/', $amount) || $amount === '.' || $amount === '') {
            return false;
        }
        return (int) round((float)$amount * 100);
    }

    function registration_handler()
    {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers['registration'];
        $nonce_name = $post_handler['nonce_name'];
        $nonce_action = $post_handler['nonce_action'];
        $unique_token = bin2hex(random_bytes(8));
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;

        $transient_data = null;
        $registration_id = null;
        $class_id = null;
        $transaction_started = false;
        $transaction_completed = false;
        global $wpdb;

        try {
            error_log(print_r($_POST, true));
            if (!current_user_can('manage_options')) {
                throw new Web_Request_Exception('You do not have permission to perform this action.');
            }

            if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
                throw new Web_Request_Exception('Request verification failed.');
            }

            if (!isset($_POST['class_id'])) {
                throw new Web_Request_Exception('Class ID not provided.');
            }
            if (!isset($_POST['student_id'])) {
                throw new Web_Request_Exception('Student ID not provided.');
            }

            $class_id = $_POST['class_id'];
            $student_id = $_POST['student_id'];
            if (!is_numeric($class_id)) {
                throw new Web_Request_Exception('Class ID is not a number.');
            }
            if (!is_numeric($student_id)) {
                throw new Web_Request_Exception('Student ID is not a number.');
            }

            $starting_level = 0;
            if (isset($_POST['starting_level'])) {
                if (!is_numeric($_POST['starting_level'])) {
                    throw new Web_Request_Exception('Starting level is not a number.');
                }
                $starting_level = (int) $_POST['starting_level'];
            }

            $balance = 0;
            if (isset($_POST['balance'])) {
                $balance = $this->convertToCents($_POST['balance']);
                if ($balance === false) {
                    throw new Web_Request_Exception('Balance is not a valid currency amount.');
                }
            }

            $notes = '';
            if (isset($_POST['notes'])) {
                $notes = sanitize_textarea_field(stripslashes($_POST['notes']));
            }

            $wpdb->query('START TRANSACTION');
            $transaction_started = true;
            $class_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}usctdp_clinic_class WHERE id = %d FOR UPDATE",
                    $class_id
                )
            );
            if (!$class_row) {
                throw new Web_Request_Exception('Class with ID ' . $class_id . ' not found.');
            }

            $student_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}usctdp_student WHERE id = %d",
                    $student_id
                )
            );
            if (!$student_row) {
                throw new Web_Request_Exception('Student with ID ' . $student_id . ' not found.');
            }

            if ($this->is_student_enrolled($student_id, $class_id)) {
                throw new Web_Request_Exception('Student is already enrolled in this class.');
            }

            $capacity = $this->get_class_capacity($class_id);
            $registrations = $this->get_class_registration_count($class_id);
            $ignore_full = isset($_POST['ignore-class-full']) && $_POST['ignore-class-full'] === 'true';
            if (!$ignore_full && $registrations >= $capacity) {
                throw new Web_Request_Exception('Class is full.');
            }

            $registration_query = new Usctdp_Mgmt_Registration_Query([]);
            $registration_id = $registration_query->add_item([
                'activity_id' => $class_id,
                'student_id' => $student_id,
                'starting_level' => $starting_level,
                'balance' => $balance,
                'notes' => $notes
            ]);

            if (!$registration_id) {
                throw new Web_Request_Exception('Failed to create registration.');
            }

            $wpdb->query('COMMIT');
            $transaction_completed = true;
            $message = "Registration created successfully!";
            $transient_data = [
                'type' => 'success',
                'message' => $message
            ];
        } catch (Throwable $e) {
            $user_message = $e->getMessage();
            if (!($e instanceof Web_Request_Exception)) {
                $user_message = 'A system error occurred. Please try again.';
            }
            $transient_data = [
                'type' => 'error',
                'message' => $user_message
            ];
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        } finally {
            if (!$transaction_completed) {
                if ($transaction_started) {
                    $wpdb->query('ROLLBACK');
                }
                if (!$transient_data) {
                    $transient_data = [
                        'type' => 'error',
                        'message' => 'An unknown error occurred.'
                    ];
                }
                $redirect_url = add_query_arg([
                    'usctdp_token' => $unique_token,
                ], $this->get_redirect_url('usctdp-admin-register'));
            } else {
                $redirect_url = add_query_arg([
                    'class_id' => $class_id,
                    'usctdp_token' => $unique_token,
                ], $this->get_redirect_url('usctdp-admin-rosters'));
            }
            set_transient($transient_key, $transient_data, 10);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    function is_student_enrolled($student_id, $class_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'student_id' => $student_id,
            'activity_id' => $class_id
        ]);
        return !empty($reg_query->items);
    }

    private function get_class_registration_count($class_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $class_id,
            'count' => true
        ]);
        return $reg_query->found_items;
    }

    private function get_class_capacity($class_id)
    {
        $class_query = new Usctdp_Mgmt_Clinic_Class_Query([
            'id' => $class_id,
            'number' => 1
        ]);
        if (empty($class_query->items)) {
            return null;
        }
        return $class_query->items[0]->capacity;
    }

    function ajax_get_class_qualification()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['class_qualification'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 400);
        }

        $class_id = isset($_GET['class_id']) ? sanitize_text_field($_GET['class_id']) : '';
        $student_id = isset($_GET['student_id']) ? sanitize_text_field($_GET['student_id']) : '';
        $capacity = $this->get_class_capacity($class_id);
        $found_posts = $this->get_class_registration_count($class_id);
        $student_registered = $this->is_student_enrolled($student_id, $class_id);

        wp_send_json_success([
            'capacity' => $capacity,
            'registered' => $found_posts,
            'student_registered' => $student_registered,
        ]);
    }

    public function ajax_gen_roster()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['gen_roster'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : '';
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : '';

        $target = null;
        if (!empty($class_id)) {
            $class_query = new Usctdp_Mgmt_Clinic_Class_Query([
                'id' => $class_id,
                'number' => 1
            ]);
            if (empty($class_query->items)) {
                wp_send_json_error('Class with ID "' . $class_id . '" not found.', 404);
            }
            $target = [
                'type' => 'class',
                'id' => $class_query->items[0]->id,
                'title' => $class_query->items[0]->title
            ];
        } else if (!empty($session_id)) {
            $session_query = new Usctdp_Mgmt_Session_Query([
                'id' => $session_id,
                'number' => 1
            ]);
            if (empty($session_query->items)) {
                wp_send_json_error('Session with ID "' . $session_id . '" not found.', 404);
            }
            $target = [
                'type' => 'session',
                'id' => $session_query->items[0]->id
            ];
        }

        if (!$target) {
            wp_send_json_error('Class ID or Session ID is required.', 400);
        }

        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();
            if ($target['type'] === 'class') {
                $document = $doc_gen->generate_class_roster($target['id']);
            } elseif ($target['type'] === 'session') {
                $document = $doc_gen->generate_session_roster($target['id']);
            }
            $drive_file = $doc_gen->upload_to_google_drive($document, $target['id'], $target['title']);
            wp_send_json_success([
                'message' => 'Roster generated successfully',
                'doc_id' => $drive_file->id,
                'doc_url' => $drive_file->webViewLink
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Error generating roster: ' . $e->getMessage()
            );
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Trace: ' . $e->getTraceAsString()
            );
            wp_send_json_error('An unexpected server error occurred during roster generation.', 500);
        }
    }

    function ajax_save_family_fields()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['save_family_fields'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $default_sanitizer = function ($value) {
            return sanitize_text_field($value);
        };
        $post_fields_sanitizers = [
            'email' => $default_sanitizer,
            'address' => $default_sanitizer,
            'city' => $default_sanitizer,
            'state' => $default_sanitizer,
            'zip' => $default_sanitizer,
            'phone' => function ($value) {
                $parts = explode('|', sanitize_text_field($value));
                return json_encode($parts);
            }
        ];

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }
        try {
            $args = [];
            foreach ($post_fields_sanitizers as $field => $handler) {
                if (isset($_POST[$field])) {
                    $args[$field] = $handler($_POST[$field]);
                }
            }
            if (empty($args)) {
                wp_send_json_success([
                    'message' => 'No work to do.'
                ]);
            }
            $query = new Usctdp_Mgmt_Family_Query([
                'id' => $family_id,
                'number' => 1
            ]);
            if (empty($query->items)) {
                wp_send_json_error('Family with ID ' . $family_id . ' not found.', 400);
            }
            $result = $query->update_item($family_id, $args);
            if ($result) {
                wp_send_json_success([
                    'message' => 'Family updated successfully'
                ]);
            } else {
                wp_send_json_error('Failed to update family.', 500);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Error updating family: ' . $e->getMessage()
            );
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Trace: ' . $e->getTraceAsString()
            );
            wp_send_json_error('An unexpected server error occurred during family update.', 500);
        }
    }

    function ajax_save_family_notes()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['save_family_notes'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $family_id = isset($_POST['family_id']) ? sanitize_text_field($_POST['family_id']) : '';
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        if (!is_numeric($family_id)) {
            wp_send_json_error('Invalid family ID provided.', 400);
        }

        $query = new Usctdp_Mgmt_Family_Query([
            'id' => $family_id,
            'number' => 1
        ]);
        if (empty($query->items)) {
            wp_send_json_error('Family with ID ' . $family_id . ' not found.', 400);
        }

        $notes = sanitize_textarea_field(stripslashes($notes));
        if ($notes === $query->items[0]->notes) {
            wp_send_json_success([
                'message' => 'No work to do.'
            ]);
        }
        $result = $query->update_item($family_id, [
            'notes' => $notes
        ]);
        if (!$result) {
            wp_send_json_error('Failed to update family notes due to an unexpected server error.', 500);
        }
        wp_send_json_success([
            'message' => 'Notes saved successfully'
        ]);
    }

    function ajax_select2_session_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_session_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }
            $query = new Usctdp_Mgmt_Session_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $active = isset($_GET['active']) ? intval($_GET['active']) : null;
            $category = isset($_GET['category']) ? intval($_GET['category']) : null;
            $query_results = $query->search_sessions($search, $active, $category, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => Usctdp_Mgmt_Model::strip_token_suffix($result->title)
                    );
                }
            }
        } catch (Throwable $e) {
            wp_send_json_error('A system error occurred. Please try again.', 500);
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        }
        wp_send_json(array('items' => $results));
    }

    function ajax_session_rosters()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['session_rosters'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }
            $query = new Usctdp_Mgmt_Session_Query();
            $query_results = $query->get_active_session_rosters();
        } catch (Throwable $e) {
            wp_send_json_error('A system error occurred. Please try again.', 500);
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        }
        wp_send_json(array('data' => $query_results));
    }

    public function ajax_session_rosters_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['session_rosters_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $active = isset($_POST['active']) ? intval($_POST['active']) : null;
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $session_query = new Usctdp_Mgmt_Session_Query();
        $result = $session_query->search_session_rosters([
            "q" => $search_val,
            "active" => $active,
            "number" => $length,
            "offset" => $start
        ]);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    function ajax_toggle_session_active()
    {
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['toggle_session_active'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }
            $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : '';
            $active = isset($_POST['active']) ? intval($_POST['active']) : '';
            if (!$session_id) {
                wp_send_json_error('No session ID provided.', 400);
            }
            if ($active && ($active != 0 && $active != 1)) {
                wp_send_json_error('Invalid active status provided.', 400);
            }
            $query = new Usctdp_Mgmt_Session_Query([]);
            $query_results = $query->update_item($session_id, [
                'is_active' => $active
            ]);
            if (!$query_results) {
                wp_send_json_error('Failed to update session active status due to an unexpected server error.', 500);
            }
        } catch (Throwable $e) {
            wp_send_json_error('A system error occurred. Please try again.', 500);
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        }
        wp_send_json_success([
            'message' => 'Session active status updated successfully'
        ]);
    }

    function ajax_select2_family_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_family_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }

            $query = new Usctdp_Mgmt_Family_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $query_results = $query->search_families($search, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => Usctdp_Mgmt_Model::strip_token_suffix($result->title),
                        'address' => $result->address,
                        'city' => $result->city,
                        'state' => $result->state,
                        'zip' => $result->zip,
                        'phone_numbers' => json_decode($result->phone_numbers),
                        'email' => $result->email,
                        'notes' => $result->notes,
                    );
                }
            }
        } catch (Throwable $e) {
            wp_send_json_error('A system error occurred. Please try again.', 500);
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        }
        wp_send_json(array('items' => $results));
    }

    function ajax_select2_student_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_student_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }

            $query = new Usctdp_Mgmt_Student_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : null;
            $query_results = $query->search_students($search, $family_id, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => Usctdp_Mgmt_Model::strip_token_suffix($result->title),
                    );
                }
            }
        } catch (Throwable $e) {
            wp_send_json_error('A system error occurred. Please try again.', 500);
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        }
        wp_send_json(array('items' => $results));
    }

    function ajax_select2_class_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_class_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }

            $query = new Usctdp_Mgmt_Clinic_Class_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;
            $query_results = $query->search_classes($search, $session_id, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => Usctdp_Mgmt_Model::strip_token_suffix($result->title),
                    );
                }
            }
        } catch (Throwable $e) {
            wp_send_json_error('A system error occurred. Please try again.', 500);
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
        }
        wp_send_json(array('items' => $results));
    }

    function age_from_birth_date($birth_date)
    {
        $today = new DateTime('now');
        $age = $today->diff($birth_date);
        return $age->y;
    }

    function ajax_student_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['student_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        $args = [
            'family_id' => $family_id,
            'orderby' => 'id',
            'order' => 'DESC',
        ];

        $reg_query = new Usctdp_Mgmt_Student_Query($args);
        $results = [];
        foreach ($reg_query->items as $row) {
            $birth_date_str = $row->birth_date ? $row->birth_date->format('m/d/Y') : '--';
            $age_str = $row->birth_date ? strval($this->age_from_birth_date($row->birth_date)) : '--';
            $results[] = [
                "id" => $row->id,
                "first" => $row->first,
                "last" => $row->last,
                "birth_date" => $birth_date_str,
                "age" => $age_str,
                "level" => $row->level,
            ];
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => count($results),
            "recordsFiltered" => count($results),
            "data" => $results,
        );
        wp_send_json($response);
    }

    public function ajax_class_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['class_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $clinic_id = isset($_POST['clinic_id']) ? intval($_POST['clinic_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($session_id) {
            $args['session_id'] = $session_id;
        }
        if ($clinic_id) {
            $args['clinic_id'] = $clinic_id;
        }

        $class_query = new Usctdp_Mgmt_Clinic_Class_Query([]);
        $result = $class_query->get_class_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    function ajax_select2_search()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_search'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $post_id = isset($_GET['post_id']) ? sanitize_text_field($_GET['post_id']) : '';
        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
        $include_acf = isset($_GET['acf']) ? sanitize_text_field($_GET['acf'] === 'true') : false;
        $tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
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

        if ($tag) {
            $args['tag'] = $tag;
        }

        $meta_query = [];
        if (isset($_GET["filter"])) {
            $meta_query = [
                'relation' => 'AND'
            ];
            foreach ($_GET["filter"] as $key => $filter) {
                $meta_query[] = [
                    'key' => $key,
                    'value' => sanitize_text_field($filter['value']),
                    'compare' => sanitize_text_field($filter['compare']),
                    'type' => sanitize_text_field($filter['type'])
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
                    'id' => get_the_ID(),
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

    public function ajax_registration_history_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['registration_history_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($student_id) {
            $args['student_id'] = $student_id;
        }

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $results = $reg_query->get_class_registration_data($args);
        foreach($results['data'] as $row) {
            $row->txns = $this->get_related_transactions($row->registration_id);
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $results['data']
        );
        wp_send_json($response);
    }

    public function get_related_transactions($reg_id) {
        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT txn.*
                FROM {$wpdb->prefix}usctdp_transaction_link AS tlink
                JOIN {$wpdb->prefix}usctdp_transaction AS txn ON tlink.transaction_id = txn.id 
                WHERE tlink.registration_id = %d",
            $reg_id
        );
        return $wpdb->get_results($query);
    }

    public function ajax_registrations_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['registrations_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($class_id) {
            $args['class_id'] = $class_id;
        }
        if ($student_id) {
            $args['student_id'] = $student_id;
        }

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $result = $reg_query->get_class_registration_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    public function ajax_datatable_balances()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['datatable_balances'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $min_balance = isset($_POST['min_balance']) ? intval($_POST['min_balance']) : 0;
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    fam.title as family_name,
                    fam.id as family_id,
                    SUM(reg.balance) AS total_family_balance,
                    COUNT(*) OVER() AS grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg 
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id 
                JOIN {$wpdb->prefix}usctdp_family AS fam ON fam.id = stu.family_id
                WHERE reg.balance > %d
                GROUP BY fam.id, fam.title
                ORDER BY total_family_balance DESC
                LIMIT %d OFFSET %d",
            $min_balance,
            $length,
            $start
        );

        $query_results = $wpdb->get_results($query);
        $output_data = [];
        $grand_total = 0;
        if ($query_results) {
            $grand_total = $query_results[0]->grand_total;
            foreach ($query_results as $result) {
                $output_data[] = [
                    "family_id" => $result->family_id,
                    "family_name" => $result->family_name,
                    "total_balance" => $amount_fmt->format($result->total_family_balance),
                ];
            }
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $grand_total,
            "recordsFiltered" => $grand_total,
            "data" => $output_data,
        );
        wp_send_json($response);
    }

    public function ajax_datatable_balances_detail()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['datatable_balances_detail'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    cls.title as activity_name,
                    stu.title as student_name,
                    REPLACE(sesh.title, '{$token_suffix}', '') as session_name,
                    reg.balance as balance,
                    COUNT(*) OVER() as grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg 
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id
                JOIN {$wpdb->prefix}usctdp_clinic_class AS cls ON reg.activity_id = cls.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON cls.session_id = sesh.id
                WHERE stu.family_id = %d AND balance > 0
                ORDER BY balance DESC
                LIMIT %d OFFSET %d",
            $family_id,
            $length,
            $start
        );
        error_log($query);

        $query_results = $wpdb->get_results($query);
        $output_data = [];
        $grand_total = 0;
        if ($query_results) {
            $grand_total = $query_results[0]->grand_total;
            foreach ($query_results as $result) {
                $output_data[] = [
                    "activity_name" => $result->activity_name,
                    "student_name" => $result->student_name,
                    "session_name" => $result->session_name,
                    "balance" => $amount_fmt->format($result->balance),
                ];
            }
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $grand_total,
            "recordsFiltered" => $grand_total,
            "data" => $output_data,
        );
        wp_send_json($response);
    }

    public function ajax_datatable_search()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['datatable_search'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $tag = isset($_POST['tag']) ? sanitize_text_field($_POST['tag']) : '';
        $expand = isset($_POST['expand']) ? $_POST['expand'] : [];
        $paged = ($start / $length) + 1;

        if (is_array($expand)) {
            $expand = array_map('sanitize_text_field', $expand);
        } else {
            $expand = [sanitize_text_field($expand)];
        }

        $meta_query = [];
        if (isset($_POST["filter"])) {
            $meta_query = [
                'relation' => 'AND'
            ];
            foreach ($_POST["filter"] as $key => $filter) {
                $meta_query[] = [
                    'key' => $key,
                    'value' => sanitize_text_field($filter['value']),
                    'compare' => sanitize_text_field($filter['compare']),
                    'type' => sanitize_text_field($filter['type'])
                ];
            }
        }

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => $length,
            'paged' => $paged,
            'no_found_rows' => false,
            'meta_query' => $meta_query,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        if ($tag) {
            $args['tag'] = $tag;
        }

        if (!empty($search_val)) {
            $args['s'] = $search_val;
        }

        $query = new WP_Query($args);
        $data_output = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $acf_fields = get_fields(get_the_ID());
                $output_fields = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'edit' => get_edit_post_link(get_the_ID())
                ];
                foreach ($acf_fields as $field_name => $field_value) {
                    if ($field_value instanceof WP_Post) {
                        $output_fields[$field_name] = [
                            'id' => $field_value->ID,
                            'title' => $field_value->post_title
                        ];
                        if (in_array($field_value->post_type, $expand)) {
                            $sub_fields = get_fields($field_value->ID);
                            foreach ($sub_fields as $sub_name => $sub_value) {
                                $output_fields[$field_name][$sub_name] = $sub_value;
                            }
                        }
                    } else {
                        $output_fields[$field_name] = $field_value;
                    }
                }
                $data_output[] = $output_fields;
            }
            wp_reset_postdata();
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $query->found_posts,
            "recordsFiltered" => $query->found_posts,
            "data" => $data_output,
        );
        wp_send_json($response);
    }
}
