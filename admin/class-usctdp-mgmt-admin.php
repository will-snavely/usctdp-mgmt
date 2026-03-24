<?php

class Web_Request_Exception extends Exception
{
}

class Usctdp_Mgmt_Admin
{
    private $plugin_name;
    private $version;

    public static $post_handlers = [
        'payment_checkout' => [
            'submit_hook' => 'usctdp_payment_checkout',
            'nonce_name' => 'usctdp_payment_checkout_nonce',
            'nonce_action' => 'usctdp_payment_checkout_nonce_action',
            'callback' => 'payment_checkout_handler'
        ],
    ];
    public static $submenu_config = [
        'clinics' => [
            'title' => 'Clinics',
            'ajax' => ['clinic_datatable', 'select2_search']
        ],
        'families' => [
            'title' => 'Families',
            'ajax' => [
                'get_family',
                'student_datatable',
                'select2_search',
                'create_family',
                'create_student',
                'update_family',
            ],
            'context' => ['family_id']
        ],
        'session-rosters' => [
            'title' => 'Session Rosters',
            'ajax' => ['gen_roster', 'session_rosters_datatable']
        ],
        'clinic-rosters' => [
            'title' => 'Clinic Rosters',
            'ajax' => ['clinic_datatable', 'select2_search']
        ],
        'register' => [
            'title' => 'Registration',
            'ajax' => [
                'select2_search',
                'activity_preregistration',
                'get_pricing',
                'registrations_datatable',
                'create_woocommerce_order',
                'commit_registrations',
                'create_ledger_entries',
            ],
            'post' => ['payment_checkout'],
            'context' => ['activity_id', 'student_id']
        ],
        'history' => [
            'title' => 'Purchase History',
            'ajax' => [
                'select2_search',
                'purchase_history_datatable',
                'update_registration',
                'get_family_balance',
                'create_woocommerce_order',
                'ledger_datatable',
                'ledger_events_datatable',
                'create_ledger_entries',
            ],
            'post' => ['payment_checkout'],
            'context' => ['family_id', 'student_id']
        ],
        'balances' => [
            'title' => 'Outstanding Balances',
            'ajax' => [
                'select2_search',
                'datatable_balances',
                'datatable_balances_detail',
                'ledger_datatable',
                'ledger_events_datatable',
            ],
            'post' => ['payment_checkout'],
            'context' => ['family_id', 'student_id']
        ]
    ];

    public static $transient_prefix = 'usctdp_admin_transient';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
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

    private function enqueue_usctdp_page_script($suffix)
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
            ['jquery'],
            '4.1.0',
            true
        );

        $deps = [
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

    private function enqueue_usctdp_page_style($suffix)
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

        $deps = [
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

    private function load_admin_page($slug, $ajax_handlers, $post_handlers, $preloads)
    {
        $js_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_url' => admin_url('admin-post.php'),
            'admin_url' => admin_url('admin.php'),
            'family_url' => admin_url('admin.php?page=usctdp-admin-families')
        ];

        foreach ($ajax_handlers as $key) {
            if (!isset(Usctdp_Mgmt_Admin_Ajax::$ajax_handlers[$key])) {
                throw new Exception("No handler found for key: $key");
            }
            $js_data[$key . "_action"] = $key;
            $js_data[$key . "_nonce"] = wp_create_nonce($key . "_nonce");
        }

        foreach ($post_handlers as $key) {
            $handler = Usctdp_Mgmt_Admin::$post_handlers[$key];
            $js_data[$key . "_action"] = $handler['submit_hook'];
            $js_data[$key . "_nonce"] = wp_create_nonce($handler['nonce_action']);
            $js_data[$key . "_nonce_id"] = $handler['nonce_name'];
        }

        if (isset($_GET['new_registrations'])) {
            $js_data['new_registrations'] = json_decode($_GET['new_registrations'], true);
        }

        $js_data['preload'] = $this->load_page_context($preloads);
        wp_localize_script($this->usctdp_script_id($slug), 'usctdp_mgmt_admin', $js_data);
    }

    private function add_usctdp_submenu(
        $slug,
        $title,
        $ajax_handlers,
        $post_handlers = [],
        $preloads = []
    ) {
        $capability = 'manage_options';
        $menu_slug = 'usctdp-admin-' . $slug;
        $hook = add_submenu_page(
            'usctdp-admin-main',
            $title,
            $title,
            $capability,
            $menu_slug,
            function () use ($slug) {
                $admin_dir = plugin_dir_path(__FILE__);
                $main_display = $admin_dir . 'partials/usctdp-mgmt-admin-' . $slug . '.php';
                $this->echo_admin_page($main_display);
            }
        );
        add_action('load-' . $hook, function () use ($slug, $ajax_handlers, $post_handlers, $preloads) {
            $this->enqueue_usctdp_page_script($slug);
            $this->enqueue_usctdp_page_style($slug);
            $this->load_admin_page($slug, $ajax_handlers, $post_handlers, $preloads);
        });
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
            $main_ajax = ['gen_roster', 'select2_search', 'session_rosters', 'toggle_session_active'];
            $this->load_admin_page('main', $main_ajax, [], []);
        });

        foreach (Usctdp_Mgmt_Admin::$submenu_config as $slug => $cfg) {
            $this->add_usctdp_submenu(
                $slug,
                $cfg['title'],
                $cfg['ajax'] ?? [],
                $cfg['post'] ?? [],
                $cfg['context'] ?? []
            );
        }
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
            $registrations = isset($_POST['registrations']) ? json_decode($_POST['registrations'], true) : [];

            if ($family_id === 0) {
                throw new Web_Request_Exception('Family ID is not set or invalid.');
            }
            if (empty($payment_method)) {
                throw new Web_Request_Exception('Payment method is not set.');
            }

            if ($payment_method === 'card') {
                if ($user_id === 0) {
                    throw new Web_Request_Exception('User ID is not set or invalid.');
                }
                if (empty($payment_url)) {
                    throw new Web_Request_Exception('Payment URL is not set.');
                }
                if (function_exists('WC') && WC()->session === null) {
                    $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
                    WC()->session = new $session_class();
                    WC()->session->init();
                }
                if (function_exists('switch_to_user')) {
                    Usctdp_Mgmt::logger()->log_info("Switching to user: " . $user_id);
                    if (ob_get_length())
                        ob_clean();
                    switch_to_user($user_id);
                } else {
                    throw new Web_Request_Exception('User switching not enabled.');
                }
                Usctdp_Mgmt::logger()->log_info("Redirecting to payment URL: " . $payment_url);
                wp_redirect($payment_url);
                exit;
            } else {
                $redirect_url = add_query_arg([
                    'usctdp_token' => $unique_token,
                    'family_id' => $family_id,
                    'new_registrations' => json_encode($registrations)
                ], $this->get_redirect_url('usctdp-admin-history'));
                $message = "Registration(s) completed successfully!";
                $transient_data = [
                    'type' => 'success',
                    'message' => $message
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect($redirect_url);
                exit;
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception("payment_checkout_handler", $e);
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
}