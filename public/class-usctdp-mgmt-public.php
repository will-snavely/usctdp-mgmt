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
                $product_type = $tennis_product->type->value;
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

    public function get_clinics($request)
    {
        global $wpdb;
        $session_id = $request->get_param('session_id');
        $product_id = $request->get_param('product_id');
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $clinic_table = $wpdb->prefix . 'usctdp_clinic';
        $query_template = "
            SELECT * FROM $activity_table as act
            JOIN $clinic_table as clin ON act.id = clin.activity_id
            WHERE act.session_id = %d AND act.product_id = %d";
        $query = $wpdb->prepare($query_template, $session_id, $product_id);
        $results = $wpdb->get_results($query);
        return $results;
    }
}
