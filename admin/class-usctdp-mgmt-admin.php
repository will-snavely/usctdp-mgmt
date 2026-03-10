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

class Web_Request_Exception extends Exception
{
}
class Usctdp_Mgmt_Admin
{
    private $plugin_name;
    private $version;
    private $select2_search_targets;

    public static $post_handlers = [
        'payment_checkout' => [
            'submit_hook' => 'usctdp_payment_checkout',
            'nonce_name' => 'usctdp_payment_checkout_nonce',
            'nonce_action' => 'usctdp_payment_checkout_nonce_action',
            'callback' => 'payment_checkout_handler'
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
        'select2_search' => [
            'action' => 'select2_search',
            'nonce' => 'select2_search_nonce',
            'callback' => 'ajax_select2_search'
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
        'payment_datatable' => [
            'action' => 'payment_datatable',
            'nonce' => 'payment_datatable_nonce',
            'callback' => 'ajax_payment_datatable'
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
        'get_family_fields' => [
            'action' => 'get_family_fields',
            'nonce' => 'get_family_fields_nonce',
            'callback' => 'ajax_get_family_fields'
        ],
        'save_family_fields' => [
            'action' => 'save_family_fields',
            'nonce' => 'save_family_fields_nonce',
            'callback' => 'ajax_save_family_fields'
        ],
        'get_family_balance' => [
            'action' => 'get_family_balance',
            'nonce' => 'get_family_balance_nonce',
            'callback' => 'ajax_get_family_balance'
        ],
        'create_family' => [
            'action' => 'create_family',
            'nonce' => 'create_family_nonce',
            'callback' => 'ajax_create_family'
        ],
        'create_student' => [
            'action' => 'create_student',
            'nonce' => 'create_student_nonce',
            'callback' => 'ajax_create_student'
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
        'create_woocommerce_order' => [
            'action' => 'create_woocommerce_order',
            'nonce' => 'create_woocommerce_order_nonce',
            'callback' => 'ajax_create_woocommerce_order'
        ],
        'commit_registrations' => [
            'action' => 'commit_registrations',
            'nonce' => 'commit_registrations_nonce',
            'callback' => 'ajax_commit_registrations'
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

        $this->select2_search_targets = [
            'session' => [
                'callback' => $this->select2_session_search(...),
                'filters' => [
                    'active' => intval(...),
                    'category' => intval(...)
                ]
            ],
            'activity' => [
                'callback' => $this->select2_activity_search(...),
                'filters' => [
                    'session_id' => intval(...),
                    'product_id' => intval(...)
                ]
            ],
            'product' => [
                'callback' => $this->select2_product_search(...),
                'filters' => [
                    'type' => intval(...)
                ]
            ],
            'family' => [
                'callback' => $this->select2_family_search(...),
                'filters' => []
            ],
            'student' => [
                'callback' => $this->select2_student_search(...),
                'filters' => [
                    'family_id' => intval(...)
                ]
            ],
        ];
    }

    public function enqueue_styles()
    {
    }
    public function enqueue_scripts()
    {
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
        wp_enqueue_script(
            'usctdp-primary-js',
            plugin_dir_url(__FILE__) . 'js/usctdp-mgmt-admin.js',
            ['jquery'],
            $this->version,
            true
        );
        wp_enqueue_script(
            'usctdp-vendor-js',
            USCTDP_DIR_PATH . 'dist/js/usctdp-mgmt-admin-vendor.js',
            array('jquery'),
            '4.1.0',
            true
        );

        $deps = $dependencies ? $dependencies : [
            'jquery',
            'usctdp-vendor-js',
            'usctdp-primary-js',
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
            'usctdp-primary-css',
            plugin_dir_url(__FILE__) . 'css/usctdp-mgmt-admin.css',
            [],
            $this->version,
            'all'
        );
        wp_enqueue_style(
            'usctdp-vendor-css',
            USCTDP_DIR_PATH . 'dist/css/usctdp-mgmt-admin-vendor.css'
        );

        $deps = $dependencies ? $dependencies : [
            'usctdp-vendor-css',
            'usctdp-primary-css',
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
                'select2_search',
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

    public function settings_init()
    {
    }

    public function usctdp_mgmt_sanitize_settings($input)
    {
    }

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
            'post_url' => admin_url('admin-post.php')
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
            'post_url' => admin_url('admin-post.php')
        ];
        $handlers = ['clinic_datatable', 'select2_search'];
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
            'post_url' => admin_url('admin-post.php')
        ];
        $handlers = [
            'select2_search',
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
            'post_url' => admin_url('admin-post.php')
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
            'post_url' => admin_url('admin-post.php')
        ];
        $ajax_handlers = [
            'select2_search',
            'activity_preregistration',
            'registrations_datatable',
            'create_woocommerce_order',
            'commit_registrations',
        ];
        foreach ($ajax_handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }

        $post_handlers = [
            'payment_checkout'
        ];
        foreach ($post_handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$post_handlers[$key];
            $js_data[$key . "_action"] = $handler['submit_hook'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce_action']);
            $js_data[$key . "_nonce_id"] = $handler['nonce_name'];
        }

        $context = $this->load_page_context(['activity_id', 'student_id']);
        $js_data['preload'] = $context;
        wp_localize_script($this->usctdp_script_id('register'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_history_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_url' => admin_url('admin-post.php')
        ];
        $handlers = [
            'select2_search',
            'registration_history_datatable',
            'payment_datatable',
            'save_registration_fields',
            'get_family_balance',
        ];
        foreach ($handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers[$key];
            $js_data[$key . "_action"] = $handler['action'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce']);
        }
        $context = $this->load_page_context(['family_id', 'student_id']);
        $js_data['preload'] = $context;

        if (isset($_GET['new_registrations'])) {
            $js_data['new_registrations'] = json_decode($_GET['new_registrations'], true);
        }
        wp_localize_script($this->usctdp_script_id('history'), 'usctdp_mgmt_admin', $js_data);
    }

    public function load_families_page()
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_url' => admin_url('admin-post.php')
        ];
        $handlers = [
            'get_family_fields',
            'save_family_fields',
            'student_datatable',
            'select2_search',
            'create_family',
            'create_student',
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
            $message = wp_kses_post($notice['message']);
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
            'id' => $activity_id,
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
        $capacity = (int) $activity->activity_capacity;
        $found_posts = (int) $this->get_activity_registration_count($activity_id);
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
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error generating roster: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            wp_send_json_error('An unexpected server error occurred during roster generation.', 500);
        }
    }

    private function create_entity_from_post($entity_name, $query_object, $fields)
    {
        $args = [];
        foreach ($fields as $field => $getter) {
            try {
                $args[$field] = $getter();
            } catch (Throwable $e) {
                throw new Web_Request_Exception('Field ' . $field . ' is invalid.', 400);
            }
        }
        $query = new $query_object([]);
        return $query->add_item($args);
    }

    private function save_entity_fields_from_post($entity_id, $query_object, $fields)
    {
        $query = new $query_object(['id' => $entity_id, 'number' => 1]);
        if (empty($query->items)) {
            throw new Web_Request_Exception("Entity with id $entity_id not found.");
        }
        $entity = $query->items[0];

        $args = [];
        foreach ($fields as $field => $transform) {
            if (array_key_exists($field, $_POST)) {
                $data = $transform($_POST[$field]);
                if ($data !== $entity->$field) {
                    $args[$field] = $data;
                }
            }
        }
        error_log(print_r($args, true));

        if (empty($args)) {
            return $entity;
        }

        $result = $query->update_item($entity_id, $args);
        if ($result) {
            $query = new $query_object(['id' => $entity_id, 'number' => 1]);
            return $query->items[0];
        } else {
            throw new Web_Request_Exception("Updating entity $entity_id failed.");
        }
    }

    function ajax_get_family_fields()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['get_family_fields'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        try {
            $family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : null;
            if (!$family_id) {
                wp_send_json_error('Missing required parameter family_id', 400);
            }

            $query = new Usctdp_Mgmt_Family_Query([
                'id' => $family_id,
                'number' => 1
            ]);
            if (empty($query->items)) {
                wp_send_json_error("No family found with id: $family_id", 400);
            }
            wp_send_json_success($query->items[0]);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error fetching family fields: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Request: ' . print_r($_POST, true));
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }


    function ajax_save_registration_fields()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['save_registration_fields'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $entity_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter registration_id', 400);
        }

        $post_fields = [
            'student_level' => sanitize_text_field(...),
            'activity_id' => intval(...),
            'credit' => sanitize_text_field(...),
            'debit' => sanitize_text_field(...),
            'notes' => function ($value) {
                return sanitize_textarea_field(stripslashes($value));
            },
        ];

        try {
            $result = $this->save_entity_fields_from_post(
                $entity_id,
                'Usctdp_Mgmt_Registration_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error updating registration: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Request: ' . print_r($_POST, true));
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    function ajax_save_family_fields()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['save_family_fields'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        $entity_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter family_id', 400);
        }

        $post_fields = [
            'email' => sanitize_text_field(...),
            'address' => sanitize_text_field(...),
            'city' => sanitize_text_field(...),
            'state' => sanitize_text_field(...),
            'zip' => sanitize_text_field(...),
            'notes' => function ($value) {
                return sanitize_textarea_field(stripslashes($value));
            },
            'phone_numbers' => json_encode(...)
        ];

        try {
            $result = $this->save_entity_fields_from_post(
                $entity_id,
                'Usctdp_Mgmt_Family_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error updating family: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Request: ' . print_r($_POST, true));
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    function ajax_get_family_balance()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['get_family_balance'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        try {
            $family_id = $this->get_sanitized_post_field_int('family_id');
            $conditions = [];
            $args = [];
            if ($family_id === null || $family_id === 0) {
                wp_send_json_error('Family ID is required.', 400);
            }
            $conditions[] = "stu.family_id = %d";
            $args[] = $family_id;

            $student_id = $this->get_sanitized_post_field_int('student_id');
            if ($student_id !== null && $student_id !== 0) {
                $conditions[] = "reg.student_id = %d";
                $args[] = $student_id;
            }

            global $wpdb;
            $query = $wpdb->prepare(
                "   SELECT
                    SUM(credit) as total_credits,
                    SUM(debit) as total_debits
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id
                WHERE " . implode(' AND ', $conditions),
                $args
            );
            $results = $wpdb->get_row($query);
            wp_send_json_success([
                'total_credits' => $results->total_credits,
                'total_debits' => $results->total_debits,
                'balance' => $results->total_debits - $results->total_credits
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error getting family balance: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            wp_send_json_error('An unexpected server error occurred during family balance retrieval.', 500);
        }
    }

    function get_sanitized_post_field_text($field)
    {
        if (array_key_exists($field, $_POST)) {
            return sanitize_text_field($_POST[$field]);
        }
        return null;
    }

    function get_sanitized_post_field_int($field)
    {
        if (array_key_exists($field, $_POST)) {
            return intval($_POST[$field]);
        }
        return null;
    }

    function ajax_create_family()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['create_family'];
        $family_id = null;
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }

        try {
            $fields = [
                'email' => function () {
                    return $this->get_sanitized_post_field_text('email');
                },
                'title' => function () {
                    $phone = trim($this->get_sanitized_post_field_text('phone'));
                    $last_name = $this->get_sanitized_post_field_text('last');
                    $last_four = substr($phone, -4);
                    return $last_name . ' ' . $last_four;
                },
                'search_term' => function () {
                    $phone = trim($this->get_sanitized_post_field_text('phone'));
                    $last_name = $this->get_sanitized_post_field_text('last');
                    $last_four = substr($phone, -4);
                    return Usctdp_Mgmt_Model::append_token_suffix($last_name . ' ' . $last_four);
                },
                'last' => function () {
                    return $this->get_sanitized_post_field_text('last');
                },
                'address' => function () {
                    return $this->get_sanitized_post_field_text('address');
                },
                'city' => function () {
                    return $this->get_sanitized_post_field_text('city');
                },
                'state' => function () {
                    return $this->get_sanitized_post_field_text('state');
                },
                'zip' => function () {
                    return $this->get_sanitized_post_field_text('zip');
                },
                'phone_numbers' => function () {
                    return json_encode([$_POST['phone']]);
                }
            ];
            $family_id = $this->create_entity_from_post('Family', 'Usctdp_Mgmt_Family_Query', $fields);
            if (!$family_id) {
                wp_send_json_error('Failed to create family.', 500);
            }

            $family_query = new Usctdp_Mgmt_Family_Query([
                'id' => $family_id,
                'number' => 1
            ]);
            if (empty($family_query->items)) {
                wp_send_json_error('Failed to create family.', 500);
            }
            $family = $family_query->items[0];
            $last_name = $family->last;
            $phone = trim($family->phone_numbers[0]);
            $last_four = substr($phone, -4);
            $userdata = array(
                'user_login' => $last_name . $last_four,
                'user_pass' => bin2hex(random_bytes(24)),
                'user_email' => $family->email,
                'first_name' => 'Family Account',
                'last_name' => $last_name,
                'display_name' => $last_name . ' ' . $last_four,
                'role' => 'subscriber'
            );
            $user_id = wp_insert_user($userdata);
            if (is_wp_error($user_id)) {
                $family_query->delete_item($family_id);
                throw new Web_Request_Exception(
                    $user_id->get_error_message(),
                    500
                );
            }
            wp_send_json_success([
                'user_id' => $user_id,
                'family_id' => $family_id
            ], 200);
        } catch (Web_Request_Exception $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error creating family: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            if ($family_id) {
                $family_query = new Usctdp_Mgmt_Family_Query([]);
                $family_query->delete_item($family_id);
            }
            wp_send_json_error($e->getMessage(), $e->getCode());
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error creating family: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            if ($family_id) {
                $family_query = new Usctdp_Mgmt_Family_Query([]);
                $family_query->delete_item($family_id);
            }
            wp_send_json_error('An unexpected server error occurred during family creation.', 500);
        }
    }

    function ajax_create_student()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['create_student'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 403);
        }
        try {
            $fields = [
                'family_id' => function () {
                    return $this->get_sanitized_post_field_int('family_id');
                },
                'title' => function () {
                    $first_name = $this->get_sanitized_post_field_text('first');
                    $last_name = $this->get_sanitized_post_field_text('last');
                    return $first_name . ' ' . $last_name;
                },
                'search_term' => function () {
                    $first_name = $this->get_sanitized_post_field_text('first');
                    $last_name = $this->get_sanitized_post_field_text('last');
                    return Usctdp_Mgmt_Model::append_token_suffix($first_name . ' ' . $last_name);
                },
                'first' => function () {
                    return $this->get_sanitized_post_field_text('first');
                },
                'last' => function () {
                    return $this->get_sanitized_post_field_text('last');
                },
                'birth_date' => function () {
                    $birth_date = $this->get_sanitized_post_field_text('birth_date');
                    if (empty($birth_date)) {
                        return null;
                    }
                    $date = new DateTime($birth_date);
                    return $date->format('Y-m-d');
                },
                'level' => function () {
                    return $this->get_sanitized_post_field_text('level');
                }
            ];
            $student_id = $this->create_entity_from_post('Student', 'Usctdp_Mgmt_Student_Query', $fields);
            if (!$student_id) {
                wp_send_json_error('Failed to create student.', 500);
            } else {
                wp_send_json_success([
                    'student_id' => $student_id
                ], 200);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error creating student: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            wp_send_json_error('An unexpected server error occurred during student creation.', 500);
        }
    }


    function ajax_select2_search()
    {
        $results = [];
        try {
            $handler = Usctdp_Mgmt_Admin::$ajax_handlers['select2_search'];
            if (!check_ajax_referer($handler['nonce'], 'security', false)) {
                wp_send_json_error('Security check failed. Invalid Nonce.', 403);
            }
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $target = isset($_GET['target']) ? sanitize_text_field($_GET['target']) : '';

            if (empty($target)) {
                wp_send_json_error('No search target specified.', 400);
            }
            if (!array_key_exists($target, $this->select2_search_targets)) {
                wp_send_json_error("Invalid target type: $target", 400);
            }

            $search_target = $this->select2_search_targets[$target];
            $filters = [];
            foreach ($search_target['filters'] as $key => $func) {
                $filters[$key] = isset($_GET[$key]) ? $func($_GET[$key]) : null;
            }
            $results = $search_target['callback']($search, $filters);
        } catch (Throwable $e) {
            $trace = $e->getTraceAsString();
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage() . "\n" . $trace);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('items' => $results));
    }


    function select2_session_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Session_Query();
        $active = $filters['active'] ?? null;
        $category = $filters['category'] ?? null;
        $query_results = $query->search_sessions($search, $active, $category, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title
                );
            }
        }
        return $results;
    }
    function select2_activity_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Activity_Query();
        $session_id = $filters['session_id'] ?? null;
        $product_id = $filters['product_id'] ?? null;
        $query_results = $query->search_activities($search, $session_id, $product_id, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'type' => intval($result->type)
                );
            }
        }
        return $results;
    }

    function select2_product_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Product_Query();
        $activity_type = $filters['type'] ?? null;
        $type_enum = Usctdp_Product_Type::tryFrom($activity_type);
        $query_results = $query->search_products($search, $type_enum, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'category' => intval($result->session_category),
                    'type' => intval($result->type)
                );
            }
        }
        return $results;
    }

    function select2_family_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Family_Query();
        $query_results = $query->search_families($search, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
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
        return $results;
    }

    function select2_student_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Student_Query();
        $family_id = $filters['family_id'] ?? null;
        $query_results = $query->search_students($search, $family_id, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'level' => $result->level,
                    'first' => $result->first,
                    'last' => $result->last,
                );
            }
        }
        return $results;
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

    public function ajax_payment_datatable()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['payment_datatable'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($registration_id) {
            $args['registration_id'] = $registration_id;
        } else {
            wp_send_json_error('No registration ID provided.', 400);
        }

        $query = new Usctdp_Mgmt_Payment_Query([]);
        $results = $query->get_payment_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $results['data']
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
        $owes = isset($_POST['owes']) ? intval($_POST['owes']) : null;

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
        if ($owes == 1) {
            $args['owes'] = $owes;
        }

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $results = $reg_query->get_registration_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $results['data']
        );
        wp_send_json($response);
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
                    SUM(reg.debit - reg.credit) AS total_family_balance,
                    COUNT(*) OVER() AS grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg 
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id 
                JOIN {$wpdb->prefix}usctdp_family AS fam ON fam.id = stu.family_id
                WHERE (reg.debit > reg.credit) AND (reg.debit - reg.credit) > %d
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
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    act.title as activity_name,
                    stu.title as student_name,
                    sesh.title as session_name,
                    reg.credit as credit,
                    reg.debit as debit,
                    (reg.debit - reg.credit) as balance,
                    COUNT(*) OVER() as grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg 
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                WHERE stu.family_id = %d AND reg.debit > reg.credit
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
                    "credit" => $amount_fmt->format($result->credit),
                    "debit" => $amount_fmt->format($result->debit),
                    "balance" => $amount_fmt->format($result->debit - $result->credit)
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
            throw new Web_Request_Exception('Product with ID "' . $product_id . '" not found.', 404);
        }
        $product = $product_query->items[0];
        $woo_product = wc_get_product($product->woocommerce_id);
        if (!$woo_product) {
            throw new Web_Request_Exception('WooCommerce product with ID "' . $product->woocommerce_id . '" not found.', 404);
        }

        $session_name = null;
        $session_meta = $woo_product->get_meta('_session_post_ids');
        foreach ($session_meta as $name => $session_id) {
            if ($session_id == $session_id) {
                $session_name = $name;
                break;
            }
        }
        if ($product->type == Usctdp_Product_Type::Clinic) {
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
    private function find_woo_product_by_code($product_code)
    {
        $product_query = new Usctdp_Mgmt_Product_Query([
            'code' => $product_code,
            'number' => 1
        ]);
        if (empty($product_query->items)) {
            throw new Web_Request_Exception('Product with code "' . $product_code . '" not found.', 404);
        }
        $product = $product_query->items[0];
        return wc_get_product($product->woocommerce_id);
    }

    public function ajax_create_woocommerce_order()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['create_woocommerce_order'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $order_data = isset($_POST['order_data']) ? $_POST['order_data'] : null;
        if (empty($order_data)) {
            wp_send_json_error('No order data provided.', 400);
        }

        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : null;
        if (empty($payment_method)) {
            wp_send_json_error('No payment method provided.', 400);
        }
        $check_number = isset($_POST['check_number']) ? sanitize_text_field($_POST['check_number']) : 'None';

        $family_id = null;
        foreach ($order_data as $order_item) {
            if ($family_id == null) {
                $family_id = $order_item["family_id"];
            } else {
                if ($family_id != $order_item["family_id"]) {
                    wp_send_json_error('All items must belong to the same family.', 400);
                }
            }

            if ($order_item["type"] == "registration") {
                $registration_id = $order_item["registration_id"];
                $line_item_id = $order_item["line_item_id"];
                if (empty($registration_id)) {
                    $error_message = "Registration ID missing for line item $line_item_id.";
                    throw new Web_Request_Exception($error_message, 400);
                }
                $registration_query = new Usctdp_Mgmt_Registration_Query(['id' => $registration_id, 'number' => 1]);
                if (empty($registration_query->items)) {
                    $error_message = "Registration with ID \"$registration_id\" not found.";
                    throw new Web_Request_Exception($error_message, 404);
                }
            }
        }

        $family_query = new Usctdp_Mgmt_Family_Query(['id' => $family_id, 'number' => 1]);
        if (empty($family_query->items)) {
            wp_send_json_error('Family with ID "' . $family_id . '" not found.', 404);
        }
        $family = $family_query->items[0];
        $user_id = $family->user_id;

        $order = null;
        $order = wc_create_order(['customer_id' => $user_id]);
        if (is_wp_error($order)) {
            wp_send_json_error('Failed to create woocommerce order.', 500);
        }
        $is_order_paid = $payment_method === 'cash' || $payment_method === 'check';
        $created_payments = [];

        try {
            $total = 0;
            foreach ($order_data as $order_item) {
                $student_query = new Usctdp_Mgmt_Student_Query(['id' => $order_item["student_id"], 'number' => 1]);
                if (empty($student_query->items)) {
                    throw new Web_Request_Exception('Student with ID "' . $order_item["student_id"] . '" not found.', 404);
                }
                $student = $student_query->items[0];
                if ($order_item["type"] == "equipment") {
                    $product_code = $order_item["product_code"];
                    $woo_product = $this->find_woo_product_by_code($product_code);
                    $item_id = $order->add_product($woo_product, 1);
                    $custom_price = floatval($order_item["credit"]);
                    $total += $custom_price;
                    $item = $order->get_item($item_id);
                    $item->add_meta_data('Student', $student->title);
                    $item->set_props(array('subtotal' => $custom_price, 'total' => $custom_price));
                    $item->save();
                } else if ($order_item["type"] == "registration") {
                    $session_id = $order_item["session_id"];
                    $session_query = new Usctdp_Mgmt_Session_Query(['id' => $session_id, 'number' => 1]);
                    if (empty($session_query->items)) {
                        throw new Web_Request_Exception("Session with ID $session_id not found.", 404);
                    }
                    $session = $session_query->items[0];
                    $activity_id = $order_item["activity_id"];
                    $activity_query = new Usctdp_Mgmt_Activity_Query(['id' => $activity_id, 'number' => 1]);
                    if (empty($activity_query->items)) {
                        throw new Web_Request_Exception("Activity with ID $activity_id not found.", 404);
                    }
                    $activity = $activity_query->items[0];
                    $product_id = $activity->product_id;
                    $variation_ids = $this->find_variations_for_session($product_id, $session_id);
                    if (empty($variation_ids)) {
                        $msg = "No variations found for product $product_id and session $session_id";
                        throw new Web_Request_Exception($msg, 404);
                    }
                    $variation_id = $variation_ids[0];
                    $product = wc_get_product($variation_id);
                    $item_id = $order->add_product($product, 1);
                    $custom_price = floatval($order_item["credit"]);
                    $total += $custom_price;

                    $item = $order->get_item($item_id);
                    $item->add_meta_data('Student', $student->title);
                    $item->add_meta_data('Session', $session->title);
                    $item->add_meta_data('Activity', $activity->title);
                    $item->set_props(array('subtotal' => $custom_price, 'total' => $custom_price));
                    $item->save();

                    $registration_id = $order_item["registration_id"];
                    $payment_status = $is_order_paid ? 'paid' : 'pending';
                    $current_time = current_time('mysql');

                    $args = [
                        'registration_id' => $registration_id,
                        'order_id' => $order->get_id(),
                        'amount' => number_format($custom_price, 2),
                        'method' => $payment_method,
                        'status' => $payment_status,
                        'created_by' => get_current_user_id(),
                        'created_at' => $current_time,
                    ];
                    if ($payment_method === 'check') {
                        $args['reference_number'] = $check_number;
                    }
                    if ($is_order_paid) {
                        $args['completed_at'] = $current_time;
                    }
                    $payment_query = new Usctdp_Mgmt_Payment_Query([]);
                    $result = $payment_query->add_item($args);
                    if ($result) {
                        $created_payments[] = $result;
                    } else {
                        $msg = "Failed to create payment for registration $registration_id";
                        throw new Web_Request_Exception($msg, 404);
                    }
                }
            }

            $order->set_total($total);
            if ($payment_method === 'cash') {
                $order->set_payment_method('cod');
                $order->set_payment_method_title('Cash');
                $order->add_order_note("Admin recorded payment via Cash");
                $order->payment_complete();
                $order->set_status('completed');
            } else if ($payment_method === 'check') {
                $order->set_payment_method('cheque');
                $order->set_payment_method_title('Check');
                $order->update_meta_data('_check_number', $check_number);
                $order->add_order_note("Admin recorded payment via Check #" . $check_number);
                $order->payment_complete();
                $order->set_status('completed');
            } else {
                $order->update_status('pending', 'Awaiting payment via ' . $payment_method);
            }
            $order->save();

            wp_send_json_success([
                "order_id" => $order->get_id(),
                "user_id" => $user_id,
                "family_id" => $family_id,
                "payment_url" => $order->get_checkout_payment_url(),
                "order_url" => get_edit_post_link($order->get_id())
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Error creating order: ' . $e->getMessage());
            Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
            if ($order instanceof WC_Order) {
                try {
                    $order->delete(true);
                } catch (Throwable $e) {
                    Usctdp_Mgmt_Logger::getLogger()->log_critical('Error cleaning up order: ' . $e->getMessage());
                    Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
                }
            }
            foreach ($created_payments as $payment) {
                try {
                    $payment_query = new Usctdp_Mgmt_Payment_Query([]);
                    if (!$payment_query->delete_item($payment)) {
                        Usctdp_Mgmt_Logger::getLogger()->log_critical('Error cleaning up payment: ' . $payment->id);
                    }
                } catch (Throwable $e) {
                    Usctdp_Mgmt_Logger::getLogger()->log_critical('Error cleaning up payment: ' . $e->getMessage());
                    Usctdp_Mgmt_Logger::getLogger()->log_critical('Trace: ' . $e->getTraceAsString());
                }
            }

            if ($e instanceof Web_Request_Exception) {
                wp_send_json_error($e->getMessage(), $e->getCode());
            } else {
                wp_send_json_error('An unexpected server error occurred during order creation.', 500);
            }
        }
    }

    function payment_checkout_handler()
    {
        $post_handler = Usctdp_Mgmt_Admin::$post_handlers['payment_checkout'];
        $nonce_name = $post_handler['nonce_name'];
        $nonce_action = $post_handler['nonce_action'];
        $unique_token = bin2hex(random_bytes(8));
        $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;

        try {
            if (!current_user_can('manage_options')) {
                throw new Web_Request_Exception('You do not have permission to perform this action.');
            }
            if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
                throw new Web_Request_Exception('Request verification failed.');
            }
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : 0;
            $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : "";
            $payment_url = isset($_POST['payment_url']) ? sanitize_url($_POST['payment_url']) : '';
            $order_url = isset($_POST['order_url']) ? sanitize_url($_POST['order_url']) : '';
            $registrations = isset($_POST['registrations']) ? json_decode($_POST['registrations'], true) : [];

            if ($user_id === 0) {
                throw new Web_Request_Exception('User ID is not set or invalid.');
            }
            if ($family_id === 0) {
                throw new Web_Request_Exception('Family ID is not set or invalid.');
            }
            if (empty($payment_url)) {
                throw new Web_Request_Exception('Payment URL is not set.');
            }
            if (empty($order_url)) {
                throw new Web_Request_Exception('Order URL is not set.');
            }
            if (empty($payment_method)) {
                throw new Web_Request_Exception('Payment method is not set.');
            }

            if ($payment_method === 'card') {
                if (function_exists('WC') && WC()->session === null) {
                    $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
                    WC()->session = new $session_class();
                    WC()->session->init();
                }
                if (function_exists('switch_to_user')) {
                    Usctdp_Mgmt_Logger::getLogger()->log_info("Switching to user: " . $user_id);
                    if (ob_get_length())
                        ob_clean();
                    switch_to_user($user_id);
                } else {
                    throw new Web_Request_Exception('User switching not enabled.');
                }
                Usctdp_Mgmt_Logger::getLogger()->log_info("Redirecting to payment URL: " . $payment_url);
                wp_redirect($payment_url);
                exit;
            } else {
                $redirect_url = add_query_arg([
                    'usctdp_token' => $unique_token,
                    'family_id' => $family_id,
                    'new_registrations' => json_encode($registrations)
                ], $this->get_redirect_url('usctdp-admin-history'));
                $message = "Registrations completed successfully!";
                $transient_data = [
                    'type' => 'success',
                    'message' => $message
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect($redirect_url);
                exit;
            }
        } catch (Throwable $e) {
            $trace = $e->getTraceAsString();
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage() . "\n" . $trace);

            $user_message = $e->getMessage();
            if (!($e instanceof Web_Request_Exception)) {
                $user_message = 'A system error occurred. Please try again.';
            }

            $redirect_url = add_query_arg([
                'usctdp_token' => $unique_token,
            ], $this->get_redirect_url('usctdp-admin-register'));

            if (function_exists('WC') && isset(WC()->session)) {
                WC()->session->destroy_session();
            }

            if (function_exists('restore_current_user')) {
                restore_current_user();
            }

            $transient_data = [
                'type' => 'error',
                'message' => $user_message
            ];
            set_transient($transient_key, $transient_data, 10);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    private function parse_registration_data($data)
    {
        if (!isset($data['activity_id'])) {
            throw new Web_Request_Exception('Activity ID missing from registration data.');
        }
        if (!isset($data['student_id'])) {
            throw new Web_Request_Exception('Student ID missing from registration data.');
        }
        if (!is_numeric($data['activity_id'])) {
            throw new Web_Request_Exception('Activity ID is not a number.');
        }
        if (!is_numeric($data['student_id'])) {
            throw new Web_Request_Exception('Student ID is not a number.');
        }

        $activity_id = $data['activity_id'];
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'id' => $activity_id,
            'number' => 1
        ]);
        if (empty($activity_query->items)) {
            throw new Web_Request_Exception('Activity with ID ' . $activity_id . ' not found.');
        }
        $activity = $activity_query->items[0];

        $student_id = $data['student_id'];
        $student_query = new Usctdp_Mgmt_Student_Query([
            'id' => $student_id,
            'number' => 1
        ]);
        if (empty($student_query->items)) {
            throw new Web_Request_Exception('Student with ID ' . $student_id . ' not found.');
        }
        $student = $student_query->items[0];

        $student_level = '';
        if (isset($data['student_level'])) {
            $student_level = sanitize_text_field($data['student_level']);
        }
        if (empty($student_level)) {
            $student_level = $student->level;
        }

        $notes = '';
        if (isset($data['notes'])) {
            $notes = sanitize_textarea_field(stripslashes($data['notes']));
        }

        $credit = 0;
        if (isset($data['credit'])) {
            $credit = sanitize_text_field($data['credit']);
        }

        $debit = 0;
        if (isset($data['debit'])) {
            $debit = sanitize_text_field($data['debit']);
        }

        $line_item_id = 0;
        if (isset($data['line_item_id'])) {
            $line_item_id = sanitize_text_field($data['line_item_id']);
        }

        return [
            "student" => $student,
            "activity" => $activity,
            "line_item_id" => $line_item_id,
            "sql_args" => [
                'activity_id' => $activity_id,
                'student_id' => $student_id,
                'student_level' => $student_level,
                'credit' => $credit,
                'debit' => $debit,
                'notes' => $notes
            ]
        ];
    }
    function ajax_commit_registrations()
    {
        $handler = Usctdp_Mgmt_Admin::$ajax_handlers['commit_registrations'];
        if (!check_ajax_referer($handler['nonce'], 'security', false)) {
            wp_send_json_error('Nonce check failed.', 403);
        }

        $transaction_started = false;
        $transaction_completed = false;
        $response_message = '';
        $registration_ids = [];
        global $wpdb;

        try {
            if (!current_user_can('manage_options')) {
                throw new Web_Request_Exception('You do not have permission to perform this action.');
            }

            $ignore_full = isset($_POST['ignore-class-full']) && $_POST['ignore-class-full'] === 'true';
            $registration_data = isset($_POST['registration_data']) ? $_POST['registration_data'] : [];
            if (empty($registration_data)) {
                throw new Web_Request_Exception('No registrations provided.');
            }

            $registration_records = [];
            foreach ($registration_data as $registration) {
                $registration_records[] = $this->parse_registration_data($registration);
            }

            $wpdb->query('START TRANSACTION');
            $transaction_started = true;
            $registration_query = new Usctdp_Mgmt_Registration_Query([]);
            foreach ($registration_records as $record) {
                $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}usctdp_activity WHERE id = %d FOR UPDATE",
                        $record["activity"]->id,
                    )
                );
            }

            $current_user = get_current_user_id();
            foreach ($registration_records as &$record) {
                $args = $record['sql_args'];
                $line_item_id = $record['line_item_id'];
                if ($this->is_student_enrolled($args['student_id'], $args['activity_id'])) {
                    throw new Web_Request_Exception('Student is already enrolled in activity: ' . $record['activity']->title);
                }

                $capacity = $this->get_activity_capacity($args['activity_id']);
                $registrations = $this->get_activity_registration_count($args['activity_id']);
                if (!$ignore_full && $registrations >= $capacity) {
                    throw new Web_Request_Exception('Class is full: ' . $record['activity']->title);
                }

                $current_time = current_time('mysql');
                $args['created_at'] = $current_time;
                $args['created_by'] = $current_user;
                $args['last_modified_at'] = $current_time;
                $args['last_modified_by'] = $current_user;
                $registration_id = $registration_query->add_item($args);
                if (!$registration_id) {
                    throw new Web_Request_Exception('Failed to create registration.');
                }
                $registration_ids[$line_item_id] = $registration_id;
            }
            $wpdb->query('COMMIT');
            $transaction_completed = true;
        } catch (Web_Request_Exception $e) {
            $trace = $e->getTraceAsString();
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage() . "\n" . $trace);
            $response_message = $e->getMessage();
        } catch (Throwable $e) {
            $trace = $e->getTraceAsString();
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage() . "\n" . $trace);
            $response_message = 'A system error occurred. Please try again.';
        } finally {
            if (!$transaction_completed) {
                if ($transaction_started) {
                    $wpdb->query('ROLLBACK');
                }
                if ($response_message === '') {
                    $response_message = 'A system error occurred. Please try again.';
                }
                wp_send_json_error($response_message, 500);
            } else {
                wp_send_json_success([
                    "ids" => $registration_ids
                ]);
            }
        }
    }
}
