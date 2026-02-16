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
    private $plugin_name;
    private $version;

    public static $post_handlers = [
        'registration' => [
            'submit_hook' => 'usctdp_registration',
            'nonce_name' => 'usctdp_registration_nonce',
            'nonce_action' => 'usctdp_registration_nonce_action',
            'callback' => 'registration_handler'
        ],
        'registration_checkout' => [
            'submit_hook' => 'usctdp_registration_checkout',
            'nonce_name' => 'usctdp_registration_checkout_nonce',
            'nonce_action' => 'usctdp_registration_checkout_nonce_action',
            'callback' => 'registration_checkout_handler'
        ],
    ];

    public static $ajax_handlers = [
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
        'select2_session_search' => [
            'action' => 'select2_session_search',
            'nonce' => 'select2_session_search_nonce',
            'callback' => 'ajax_select2_session_search'
        ],
        'select2_activity_search' => [
            'action' => 'select2_activity_search',
            'nonce' => 'select2_activity_search_nonce',
            'callback' => 'ajax_select2_activity_search'
        ],
        'select2_product_search' => [
            'action' => 'select2_product_search',
            'nonce' => 'select2_product_search_nonce',
            'callback' => 'ajax_select2_product_search'
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
        'clinic_datatable' => [
            'action' => 'clinic_datatable',
            'nonce' => 'clinic_datatable_nonce',
            'callback' => 'ajax_clinic_datatable'
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
        'save_registration_fields' => [
            'action' => 'save_registration_fields',
            'nonce' => 'save_registration_fields_nonce',
            'callback' => 'ajax_save_registration_fields'
        ],
        'gen_roster' => [
            'action' => 'gen_roster',
            'nonce' => 'gen_roster_nonce',
            'callback' => 'ajax_gen_roster'
        ],
        'activity_preregistration' => [
            'action' => 'activity_preregistration',
            'nonce' => 'activity_preregistration_nonce',
            'callback' => 'ajax_activity_preregistration'
        ],
        'select2_clinic_search' => [
            'action' => 'select2_clinic_search',
            'nonce' => 'select2_clinic_search_nonce',
            'callback' => 'ajax_select2_clinic_search'
        ],
        'create_woocommerce_order' => [
            'action' => 'create_woocommerce_order',
            'nonce' => 'create_woocommerce_order_nonce',
            'callback' => 'ajax_create_woocommerce_order'
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

    public function enqueue_styles() {}
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
        wp_enqueue_script(
            'usctdp-select2-js',
            USCTDP_DIR_PATH . 'assets/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );

        $deps = $dependencies ? $dependencies : [
            'jquery',
            $this->plugin_name . 'external-datatables-js',
            $this->plugin_name . 'primary-js',
            'usctdp-select2-js',
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

        wp_enqueue_style(
            'usctdp-select2-css',
            USCTDP_DIR_PATH . 'assets/css/select2.min.css'
        );

        $deps = $dependencies ? $dependencies : [
            'usctdp-select2-css',
            $this->plugin_name . 'external-datatables-css',
            $this->plugin_name . 'primary-css'
        ];
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

    public function usctdp_google_oauth_handler()
    {
        $redirect_url = admin_url('admin.php?page=usctdp-admin-main');
        if (!isset($_GET['page']) || $_GET['page'] !== 'usctdp-admin-main') {
            return;
        }

        if (isset($_GET['usctdp_google_auth']) && $_GET['usctdp_google_auth'] === '1') {
            Usctdp_Mgmt_Logger::getLogger()->log_info('USCTDP: Google OAuth Initiated');
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
            Usctdp_Mgmt_Logger::getLogger()->log_info('USCTDP: Google OAuth Code Received');
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
                    Usctdp_Mgmt_Logger::getLogger()->log_info('USCTDP: Google OAuth Refresh Token Received');
                    update_option('usctdp_google_refresh_token', $token['refresh_token']);
                    update_option('usctdp_google_refresh_token_timestamp', date('Y-m-d H:i:s'));
                    $message = 'Authorization successful! Refresh Token stored.';
                } else {
                    Usctdp_Mgmt_Logger::getLogger()->log_info('USCTDP: Google OAuth Refresh Token Not Received');
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
                Usctdp_Mgmt_Logger::getLogger()->log_error("Google OAuth Error: " . $e->getMessage());
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
            $this->enqueue_usctdp_page_script('main');
            $this->enqueue_usctdp_page_style('main');
            $js_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'family_url' => admin_url('admin.php?page=usctdp-admin-families')
            ];
            $handlers = [
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
        $this->add_usctdp_submenu('clinics', 'Clinics', [$this, 'load_clinics_page']);
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

    private function load_page_context($expected_params = [])
    {
        $result = [];
        $query_map = [
            'session_id' => function ($id) {
                return $this->db_id_query('Usctdp_Mgmt_Session_Query', $id);
            },
            'clinic_id' => function ($id) {
                $clinic_query = new Usctdp_Mgmt_Clinic_Query([]);
                $result = $clinic_query->get_clinic_data([
                    'id' => $id,
                    'number' => 1
                ]);
                if (!empty($result['data'])) {
                    return $result['data'][0];
                }
                return null;
            },
            'activity_id' => function ($id) {
                $activity_query = new Usctdp_Mgmt_Activity_Query([]);
                $result = $activity_query->get_activity_data([
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

    public function load_clinics_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = ['clinic_datatable', 'select2_session_search', 'select2_product_search'];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        wp_localize_script($this->usctdp_script_id('clinics'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_clinic_rosters_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
            'select2_session_search',
            'select2_activity_search',
            'gen_roster',
            'registrations_datatable'
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['activity_id']);
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
            'activity_preregistration',
            'registrations_datatable',
            'select2_family_search',
            'select2_student_search',
            'select2_session_search',
            'select2_activity_search',
            'create_woocommerce_order',
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['activity_id', 'student_id']);
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
            'select2_session_search',
            'select2_clinic_search',
            'select2_activity_search',
            'registration_history_datatable',
            'save_registration_fields',
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['family_id', 'student_id']);
        $js_data['preload'] = $context;
        wp_localize_script($this->usctdp_script_id('history'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_families_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
        $handlers = [
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
        return (int) round((float) $amount * 100);
    }

    function is_student_enrolled($student_id, $activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'student_id' => $student_id,
            'activity_id' => $activity_id
        ]);
        return !empty($reg_query->items);
    }

    private function get_activity_registration_count($activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $activity_id,
            'count' => true
        ]);
        return $reg_query->found_items;
    }

    private function get_activity_capacity($activity_id)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'activity_id' => $activity_id,
            'number' => 1
        ]);
        if (empty($activity_query->items)) {
            return null;
        }
        return $activity_query->items[0]->capacity;
    }

    function ajax_activity_preregistration()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['activity_preregistration'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 400);
        }

        $activity_id = isset($_GET['activity_id']) ? sanitize_text_field($_GET['activity_id']) : '';
        $student_id = isset($_GET['student_id']) ? sanitize_text_field($_GET['student_id']) : '';

        $student_query = new Usctdp_Mgmt_Student_Query([
            'student_id' => $student_id,
            'number' => 1
        ]);
        if (empty($student_query->items)) {
            wp_send_json_error('Student with ID "' . $student_id . '" not found.', 404);
        }
        $student = $student_query->items[0];

        $activity_query = new Usctdp_Mgmt_Activity_Query([]);
        $result = $activity_query->get_activity_data([
            'id' => $activity_id,
            'number' => 1
        ]);
        if (empty($result['data'])) {
            wp_send_json_error('Activity with ID "' . $activity_id . '" not found.', 404);
        }
        $activity = $result['data'][0];

        $pricing_query = new Usctdp_Mgmt_Pricing_Query([
            'session_id' => $activity->session_id,
            'product_id' => $activity->product_id,
            'number' => 1
        ]);
        if (empty($pricing_query->items)) {
            wp_send_json_error('Pricing for activity "' . $activity_id . '" not found.', 404);
        }
        $pricing = $pricing_query->items[0];
        $capacity = $this->get_activity_capacity($activity_id);
        $found_posts = $this->get_activity_registration_count($activity_id);
        $student_registered = $this->is_student_enrolled($student_id, $activity_id);

        wp_send_json_success([
            'capacity' => $capacity,
            'session_id' => $activity->session_id,
            'product_id' => $activity->product_id,
            'woocommerce_id' => $activity->product_woocommerce_id,
            'registered' => $found_posts,
            'student_registered' => $student_registered,
            'student_level' => $student->level,
            'pricing' => $pricing->pricing
        ]);
    }

    public function ajax_gen_roster()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['gen_roster'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : '';
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : '';

        $target = null;
        if (!empty($activity_id)) {
            $activity_query = new Usctdp_Mgmt_Activity_Query([
                'id' => $activity_id,
                'number' => 1
            ]);
            if (empty($activity_query->items)) {
                wp_send_json_error('Activity with ID "' . $activity_id . '" not found.', 404);
            }
            $target = [
                'id' => $activity_query->items[0]->id,
                'title' => $activity_query->items[0]->title,
                'type' => $activity_query->items[0]->type,
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
                'id' => $session_query->items[0]->id,
                'title' => $session_query->items[0]->title,
                'type' => "session",
            ];
        }

        if (!$target) {
            wp_send_json_error('Activity ID or Session ID is required.', 400);
        }
        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();
            $document = null;
            if ($target['type'] === Usctdp_Activity_Type::Clinic) {
                $document = $doc_gen->generate_clinic_roster($target['id']);
            } elseif ($target['type'] === 'session') {
                $document = $doc_gen->generate_session_roster($target['id']);
            }
            if (!$document) {
                wp_send_json_error('Document not generated.', 400);
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

    private function save_entity_fields_from_post($entity_name, $query_object, $post_fields_sanitizers)
    {
        $entity_id = isset($_POST['id']) ? intval($_POST['id']) : '';
        if (!$entity_id) {
            wp_send_json_error('No id provided.', 400);
        }
        try {
            $query = new $query_object([
                'id' => $entity_id,
                'number' => 1
            ]);
            if (empty($query->items)) {
                wp_send_json_error($entity_name . ' with ID ' . $entity_id . ' not found.', 400);
            }
            $entity = $query->items[0];

            $args = [];
            foreach ($post_fields_sanitizers as $field => $sanitizer) {
                if (isset($_POST[$field])) {
                    $sanitized = $sanitizer($_POST[$field]);
                    if ($sanitized !== $entity->$field) {
                        $args[$field] = $sanitized;
                    }
                }
            }

            if (empty($args)) {
                wp_send_json_success([
                    'message' => 'No fields have changed.'
                ]);
            }

            $result = $query->update_item($entity_id, $args);
            if ($result) {
                wp_send_json_success([
                    'message' => $entity_name . ' updated successfully'
                ]);
            } else {
                wp_send_json_error('Failed to update ' . $entity_name . '.', 500);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Error updating ' . $entity_name . ': ' . $e->getMessage()
            );
            Usctdp_Mgmt_Logger::getLogger()->log_critical(
                'Trace: ' . $e->getTraceAsString()
            );
            wp_send_json_error('An unexpected server error occurred during ' . $entity_name . ' update.', 500);
        }
    }

    function ajax_save_family_fields()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['save_family_fields'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $string_sanitizer = function ($value) {
            return sanitize_text_field($value);
        };

        $post_fields_sanitizers = [
            'email' => $string_sanitizer,
            'address' => $string_sanitizer,
            'city' => $string_sanitizer,
            'state' => $string_sanitizer,
            'zip' => $string_sanitizer,
            'notes' => function ($value) {
                return sanitize_textarea_field(stripslashes($value));
            },
            'phone' => function ($value) {
                $parts = explode('|', sanitize_text_field($value));
                return json_encode($parts);
            }
        ];

        $this->save_entity_fields_from_post('Family', 'Usctdp_Mgmt_Family_Query', $post_fields_sanitizers);
    }

    function ajax_save_registration_fields()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['save_registration_fields'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $string_sanitizer = function ($value) {
            return sanitize_text_field($value);
        };

        $int_sanitizer = function ($value) {
            return intval($value);
        };

        $textarea_sanitizer = function ($value) {
            return sanitize_textarea_field(stripslashes($value));
        };

        $post_fields_sanitizers = [
            'student_level' => $string_sanitizer,
            'activity_id' => $int_sanitizer,
            'session_id' => $int_sanitizer,
            'credit' => $int_sanitizer,
            'debit' => $int_sanitizer,
            'notes' => $textarea_sanitizer,
        ];

        $this->save_entity_fields_from_post('Registration', 'Usctdp_Mgmt_Registration_Query', $post_fields_sanitizers);
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
                        'text' => $result->title
                    );
                }
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('items' => $results));
    }

    function ajax_select2_activity_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_activity_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }
            $query = new Usctdp_Mgmt_Activity_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
            $query_results = $query->search_activities($search, $session_id, null, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => $result->title
                    );
                }
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('items' => $results));
    }

    function ajax_select2_product_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_product_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }
            $query = new Usctdp_Mgmt_Product_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $activity_string = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : "clinic";
            $activity_type = null;
            if ($activity_string == "clinic") {
                $activity_type = Usctdp_Activity_Type::Clinic;
            } else if ($activity_string == "tournament") {
                $activity_type = Usctdp_Activity_Type::Tournament;
            } else if ($activity_string == "camp") {
                $activity_type = Usctdp_Activity_Type::Camp;
            }
            $query_results = $query->search_products($search, $activity_type, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => $result->title
                    );
                }
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
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
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
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
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
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
                        'text' => $result->title,
                        'title' => $result->title,
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
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
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
                    $birth_date = new DateTime($result->birth_date);
                    $results[] = array(
                        'id' => $result->id,
                        'text' => $result->title,
                        'level' => $result->level,
                    );
                }
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('items' => $results));
    }

    function ajax_select2_clinic_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_clinic_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }

            $query = new Usctdp_Mgmt_Clinic_Query();
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;
            $query_results = $query->search_clinics($search, $session_id, 10);
            if ($query_results) {
                foreach ($query_results as $result) {
                    $results[] = array(
                        'id' => $result->id,
                        'text' => $result->title,
                    );
                }
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage());
            wp_send_json_error('A system error occurred. Please try again.', 500);
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

    public function ajax_clinic_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['clinic_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
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
        if ($product_id) {
            $args['product_id'] = $product_id;
        }

        $clinic_query = new Usctdp_Mgmt_Clinic_Query([]);
        $result = $clinic_query->get_clinic_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    public function ajax_registration_history_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['registration_history_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($family_id) {
            $args['family_id'] = $family_id;
        }
        if ($student_id) {
            $args['student_id'] = $student_id;
        }
        if ($session_id) {
            $args['session_id'] = $session_id;
        }

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $results = $reg_query->get_registration_data($args);
        foreach ($results['data'] as $row) {
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

    public function get_related_transactions($reg_id)
    {
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

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($activity_id) {
            $args['activity_id'] = $activity_id;
        }
        if ($student_id) {
            $args['student_id'] = $student_id;
        }

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $result = $reg_query->get_registration_data($args);
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
                    sesh.title as session_name,
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

    function find_variations($product, $match_criteria)
    {
        if (!$product || !$product->is_type('variable')) {
            return null;
        }

        $results = [];
        error_log("product: " . print_r($product->get_available_variations(), true));
        foreach ($product->get_available_variations() as $variation_data) {
            $variation_attributes = $variation_data['attributes'];
            $is_match = true;
            foreach ($match_criteria as $key => $value) {
                $search_key = 'attribute_' . sanitize_title($key);
                if (isset($variation_attributes[$search_key])) {
                    if ($variation_attributes[$search_key] !== '' && $variation_attributes[$search_key] !== $value) {
                        $is_match = false;
                        break;
                    }
                } else {
                    $is_match = false;
                    break;
                }
            }

            if ($is_match) {
                $results[] = $variation_data['variation_id'];
            }
        }

        return $results;
    }

    private function find_variations_for_session($product_id, $session_id)
    {
        $product_query = new Usctdp_Mgmt_Product_Query([
            'id' => $product_id,
            'number' => 1
        ]);
        if (empty($product_query->items)) {
            wp_send_json_error('Product with ID "' . $product_id . '" not found.', 404);
        }
        $product = $product_query->items[0];
        $woo_product = wc_get_product($product->woocommerce_id);

        $session_name = null;
        $session_meta = $woo_product->get_meta('_session_post_ids');
        foreach ($session_meta as $name => $session_id) {
            if ($session_id == $session_id) {
                $session_name = $name;
                break;
            }
        }
        if ($product->type == Usctdp_Activity_Type::Clinic) {
            return $this->find_variations($woo_product, [
                'session' => $session_name,
                'days-per-week' => "One",
            ]);
        } else {
            return $this->find_variations($woo_product, [
                'session' => $session_name,
            ]);
        }
    }

    public function ajax_create_woocommerce_order()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['create_woocommerce_order'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }
        error_log(print_r($_POST, true));

        $order_data = $_POST['order_data'];
        if (empty($order_data)) {
            wp_send_json_error('No order data provided.', 400);
        }

        // Ensure family_id is consistent across all items
        $family_id = null;
        foreach ($order_data as $order_item) {
            if ($family_id == null) {
                $family_id = $order_item["family_id"];
            } else {
                if ($family_id != $order_item["family_id"]) {
                    wp_send_json_error('All items must belong to the same family.', 400);
                }
            }
        }

        // Lookup the family_id
        $family_query = new Usctdp_Mgmt_Family_Query([
            'id' => $family_id,
            'number' => 1
        ]);
        if (empty($family_query->items)) {
            wp_send_json_error('Family with ID "' . $family_id . '" not found.', 404);
        }
        $family = $family_query->items[0];
        $user_id = $family->user_id;

        $order = wc_create_order(['customer_id' => $user_id]);
        if (is_wp_error($order)) {
            wp_send_json_error('Failed to create woocommerce order.', 500);
        }

        $total = 0;
        foreach ($order_data as $order_item) {
            $session_id = $order_item["session_id"];
            $product_id = $order_item["product_id"];
            $variation_ids = $this->find_variations_for_session($product_id, $session_id);
            error_log("variation_ids: " . print_r($variation_ids, true));
            if (empty($variation_ids)) {
                wp_send_json_error(
                    'No variations found for product "' . $product_id . '" and session "' . $session_id . '".',
                    404
                );
            }
            $variation_id = $variation_ids[0];
            $product = wc_get_product($variation_id);
            $item_id = $order->add_product($product, 1);

            $custom_price = floatval($order_item["price"]);
            $total += $custom_price;
            $item = $order->get_item($item_id);
            $item->set_props(array(
                'subtotal' => $custom_price,
                'total'    => $custom_price,
            ));
            $item->save();
        }

        $order->set_total($total);
        $order->save();
        $order->update_status('pending', 'Order created by admin.');
        wp_send_json_success([
            "order_id" => $order->get_id(),
            "user_id" => $user_id,
            "payment_url" => $order->get_checkout_payment_url(),
        ]);
    }

    function registration_checkout_handler()
    {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers['registration_checkout'];
        $nonce_name = $post_handler['nonce_name'];
        $nonce_action = $post_handler['nonce_action'];

        try {
            if (!current_user_can('manage_options')) {
                throw new Web_Request_Exception('You do not have permission to perform this action.');
            }
            if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
                throw new Web_Request_Exception('Request verification failed.');
            }
            $user_id = $_POST['user_id'];
            $payment_url = $_POST['payment_url'];

            if ($user_id === 0) {
                throw new Web_Request_Exception('User ID is not set or invalid.');
            }
            if (empty($payment_url)) {
                throw new Web_Request_Exception('Payment URL is not set or invalid.');
            }

            if (function_exists('WC') && WC()->session === null) {
                $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
                WC()->session  = new $session_class();
                WC()->session->init();
            }

            if (function_exists('switch_to_user')) {
                Usctdp_Mgmt_Logger::getLogger()->log_info("Switching to user: " . $user_id);
                if (ob_get_length()) ob_clean();
                switch_to_user($user_id);
            } else {
                throw new Web_Request_Exception('User switching not enabled.');
            }
            Usctdp_Mgmt_Logger::getLogger()->log_info("Redirecting to payment URL: " . $payment_url);
            wp_redirect($payment_url);
            exit;
        } catch (Throwable $e) {
            $trace = $e->getTraceAsString();
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage() . "\n" . $trace);
            $user_message = $e->getMessage();
            if (!($e instanceof Web_Request_Exception)) {
                $user_message = 'A system error occurred. Please try again.';
            }
            $unique_token = bin2hex(random_bytes(8));
            $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
            $redirect_url = add_query_arg([
                'usctdp_token' => $unique_token,
            ], $this->get_redirect_url('usctdp-admin-register'));
            $transient_data = [
                'type' => 'error',
                'message' => $user_message
            ];

            if (function_exists('WC') && isset(WC()->session)) {
                WC()->session->destroy_session();
            }

            if (function_exists('restore_current_user')) {
                restore_current_user();
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

        $transient_data = null;
        $registration_id = null;
        $activity_id = null;
        $transaction_started = false;
        $transaction_completed = false;
        global $wpdb;

        try {
            if (!current_user_can('manage_options')) {
                throw new Web_Request_Exception('You do not have permission to perform this action.');
            }

            if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
                throw new Web_Request_Exception('Request verification failed.');
            }

            if (!isset($_POST['activity_id'])) {
                throw new Web_Request_Exception('Activity ID not provided.');
            }
            if (!isset($_POST['student_id'])) {
                throw new Web_Request_Exception('Student ID not provided.');
            }

            $activity_id = $_POST['activity_id'];
            $student_id = $_POST['student_id'];
            if (!is_numeric($activity_id)) {
                throw new Web_Request_Exception('Activity ID is not a number.');
            }
            if (!is_numeric($student_id)) {
                throw new Web_Request_Exception('Student ID is not a number.');
            }

            $student_level = null;
            if (isset($_POST['student_level'])) {
                if (!is_numeric($_POST['student_level'])) {
                    throw new Web_Request_Exception('Student level is not a number.');
                }
                $student_level = (int) $_POST['student_level'];
            }

            $notes = '';
            if (isset($_POST['notes'])) {
                $notes = sanitize_textarea_field(stripslashes($_POST['notes']));
            }

            $wpdb->query('START TRANSACTION');
            $transaction_started = true;
            $activity_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}usctdp_activity WHERE id = %d FOR UPDATE",
                    $activity_id
                )
            );
            if (!$activity_row) {
                throw new Web_Request_Exception('Activity with ID ' . $activity_id . ' not found.');
            }

            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $student_id,
                'number' => 1
            ]);
            if (empty($student_query->items)) {
                throw new Web_Request_Exception('Student with ID ' . $student_id . ' not found.');
            }
            $student = $student_query->items[0];

            if (empty($student_level)) {
                $student_level = $student->level;
            }

            if ($this->is_student_enrolled($student->id, $activity_id)) {
                throw new Web_Request_Exception('Student is already enrolled in this activity.');
            }

            $capacity = $this->get_activity_capacity($activity_id);
            $registrations = $this->get_activity_registration_count($activity_id);
            $ignore_full = isset($_POST['ignore-class-full']) && $_POST['ignore-class-full'] === 'true';
            if (!$ignore_full && $registrations >= $capacity) {
                throw new Web_Request_Exception('Class is full.');
            }

            $registration_query = new Usctdp_Mgmt_Registration_Query([]);
            $registration_id = $registration_query->add_item([
                'activity_id' => $activity_id,
                'student_id' => $student_id,
                'student_level' => $student_level,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
                'last_modified_at' => current_time('mysql'),
                'last_modified_by' => get_current_user_id(),
                'credit' => 0,
                'debit' => 0,
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
                    'activity_id' => $activity_id,
                    'usctdp_token' => $unique_token,
                ], $this->get_redirect_url('usctdp-admin-clinic-rosters'));
            }
            set_transient($transient_key, $transient_data, 10);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
