<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.wsnavely.com
 * @since      1.0.0
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/public
 * @author     Will Snavely <will.snavely@gmail.com>
 */
class Usctdp_Mgmt_Public
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

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function usctdp_rest_api_init()
    {
        $rest_id = "usctdp-mgmt/v1";
        register_rest_route($rest_id, '/clinics/(?P<session_id>\d+)/(?P<product_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_clinics'],
            'args' => [
                'session_id' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    },
                ],
                'product_id' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    },
                ],
            ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route($rest_id, '/activities/(?P<session_id>\d+)/(?P<product_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activities'],
            'args' => [
                'session_id' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    },
                ],
                'product_id' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    },
                ],
            ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route($rest_id, '/students/', [
            'methods' => 'GET',
            'callback' => [$this, 'get_students'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route($rest_id, '/students/', [
            'methods' => 'POST',
            'callback' => [$this, 'create_student'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route($rest_id, '/waitlist/', [
            'methods' => 'POST',
            'callback' => [$this, 'create_waitlist_entry'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . "css/usctdp-mgmt-public.css",
            [],
            $this->version,
            "all",
        );

        if (is_product()) {
            wp_enqueue_style(
                'usctdp-mgmt-product-style',
                plugin_dir_url(__FILE__) . "css/usctdp-mgmt-product.css",
                ["select2"],
                $this->version,
                "all"
            );
        }
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . "js/usctdp-mgmt-public.js",
            ["jquery"],
            $this->version,
            false,
        );

        if (is_product()) {
            global $product;

            $product_script = 'usctdp-mgmt-product-script';
            wp_enqueue_script(
                $product_script,
                plugin_dir_url(__FILE__) . "js/usctdp-mgmt-product.js",
                ["jquery", "selectWoo"],
                $this->version,
                false
            );

            $product_query = new Usctdp_Mgmt_Product_Query([
                'woocommerce_id' => $product->get_id(),
                'number' => 1,
            ]);
            $product_type = null;
            $usctdp_id = null;
            if (!empty($product_query->items)) {
                $tennis_product = $product_query->items[0];
                $product_type = $tennis_product->type;
                $usctdp_id = $tennis_product->id;
            }

            $session_map = get_post_meta($product->get_id(), '_session_post_ids', true);
            wp_localize_script($product_script, 'siteData', array(
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'product_type' => $product_type,
                'usctdp_id' => $usctdp_id,
                'session_map' => $session_map,
            ));
        }
    }

    private function get_user_family()
    {
        global $wpdb;
        $current_user_id = get_current_user_id();
        $family_query = new Usctdp_Mgmt_Family_Query([
            "user_id" => $current_user_id,
            "number" => 1
        ]);
        if (empty($family_query->items)) {
            return null;
        }
        return $family_query->items[0];
    }

    /**
     * Generic (session, product) -> activities lookup with enrollment counts.
     * Unlike get_clinics() below, this doesn't join usctdp_clinic - it works
     * for any activity type, since capacity/enrollment live on usctdp_activity
     * itself. For a clinic session this returns every class time-slot; for a
     * tournament session it returns exactly one activity.
     */
    public function get_activities($request)
    {
        global $wpdb;
        $session_id = $request->get_param('session_id');
        $product_id = $request->get_param('product_id');
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $registration_table = $wpdb->prefix . 'usctdp_registration';

        $activity_query = $wpdb->prepare(
            "SELECT act.*, COUNT(reg.id) as enrolled_count
            FROM $activity_table as act
            LEFT JOIN $registration_table as reg ON act.id = reg.activity_id
            WHERE act.session_id = %d AND act.product_id = %d
            GROUP BY act.id",
            $session_id,
            $product_id
        );

        $results = $wpdb->get_results($activity_query);
        foreach ($results as &$activity) {
            $activity->capacity = (int) $activity->capacity;
            $activity->enrolled_count = (int) $activity->enrolled_count;
        }
        return $results;
    }

    public function get_clinics($request)
    {
        global $wpdb;
        $session_id = $request->get_param('session_id');
        $product_id = $request->get_param('product_id');
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $clinic_table = $wpdb->prefix . 'usctdp_clinic';
        $registration_table = $wpdb->prefix . 'usctdp_registration';

        $clinic_query = $wpdb->prepare(
            "SELECT * FROM $activity_table as act
            JOIN $clinic_table as clin ON act.id = clin.id
            WHERE act.session_id = %d AND act.product_id = %d",
            $session_id,
            $product_id
        );

        $registration_query = $wpdb->prepare(
            "SELECT act.id, COUNT(reg.id) as enrolled_count 
            FROM $activity_table as act
            JOIN $clinic_table as clin ON act.id = clin.id
            LEFT JOIN $registration_table as reg ON act.id = reg.activity_id
            WHERE act.session_id = %d AND act.product_id = %d
            GROUP BY act.id",
            $session_id,
            $product_id
        );

        $clinic_results = $wpdb->get_results($clinic_query);
        $registration_results = $wpdb->get_results($registration_query);
        $registration_map = [];
        foreach ($registration_results as $registration) {
            $registration_map[$registration->id] = $registration->enrolled_count;
        }

        foreach ($clinic_results as &$clinic) {
            $clinic->enrolled_count = 0;
            if (isset($registration_map[$clinic->id])) {
                $clinic->enrolled_count = (int) $registration_map[$clinic->id];
            }
            $clinic->capacity = (int) $clinic->capacity;
            $clinic->type = (int) $clinic->type;
        }
        return $clinic_results;
    }

    public function get_students($request)
    {
        global $wpdb;
        $family = $this->get_user_family();
        if (empty($family)) {
            return new WP_Error('no_family', 'No family found for user', [
                'status' => 400
            ]);
        }
        $student_query = new Usctdp_Mgmt_Student_Query([
            "family_id" => $family->id,
            "sort" => "id",
            "order" => "DESC"
        ]);
        return $student_query->items;
    }

    public function create_student($request)
    {
        $family = $this->get_user_family();
        if (empty($family)) {
            return new WP_Error('no_family', 'No family found for user', [
                'status' => 400
            ]);
        }
        $first_name = $request->get_param("first_name");
        if (empty($first_name)) {
            return new WP_Error('no_first_name', 'First name is required', [
                'status' => 400
            ]);
        }
        $last_name = $request->get_param("last_name");
        if (empty($last_name)) {
            return new WP_Error('no_last_name', 'Last name is required', [
                'status' => 400
            ]);
        }
        $birthdate = $request->get_param("birthdate");
        if (empty($birthdate)) {
            return new WP_Error('no_birthdate', 'Birthdate is required', [
                'status' => 400
            ]);
        }

        $student_query = new Usctdp_Mgmt_Student_Query();
        $result = $student_query->create_student(
            $first_name,
            $last_name,
            intval($family->id),
            strval($birthdate),
            ""
        );
        if (!$result) {
            error_log("Failed to create student.");
            return new WP_Error("create_student_failed", "Failed to create student", [
                'status' => 400
            ]);
        }
        return $result;
    }

    public function create_waitlist_entry($request)
    {
        $family = $this->get_user_family();
        if (empty($family)) {
            return new WP_Error('no_family', 'No family found for user', [
                'status' => 400
            ]);
        }
        $student_id = $request->get_param('student_id');
        if (empty($student_id)) {
            return new WP_Error('no_student_id', 'Student is required', [
                'status' => 400
            ]);
        }
        $activity_id = $request->get_param('activity_id');
        if (empty($activity_id)) {
            return new WP_Error('no_activity_id', 'Activity is required', [
                'status' => 400
            ]);
        }

        $student_query = new Usctdp_Mgmt_Student_Query([
            'id' => intval($student_id),
            'number' => 1,
        ]);
        if (empty($student_query->items) || intval($student_query->items[0]->family_id) !== intval($family->id)) {
            return new WP_Error('invalid_student', 'Invalid student', [
                'status' => 400
            ]);
        }

        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'id' => intval($activity_id),
            'number' => 1,
        ]);
        if (empty($activity_query->items)) {
            return new WP_Error('invalid_activity', 'Invalid activity', [
                'status' => 400
            ]);
        }

        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query([
            'activity_id' => intval($activity_id),
            'student_id' => intval($student_id),
            'number' => 1,
        ]);
        if (!empty($waitlist_query->items)) {
            return $waitlist_query->items[0];
        }

        $result = $waitlist_query->add_item([
            'activity_id' => intval($activity_id),
            'student_id' => intval($student_id),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);
        if (!$result) {
            error_log("Failed to create waitlist entry.");
            return new WP_Error("create_waitlist_failed", "Failed to add student to waitlist", [
                'status' => 400
            ]);
        }
        return $result;
    }
}
