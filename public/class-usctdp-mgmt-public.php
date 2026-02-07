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
        register_rest_route($rest_id, '/students/', [
            'methods' => 'GET',
            'callback' => [$this, 'get_students'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    public function enqueue_styles()
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Usctdp_Mgmt_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Usctdp_Mgmt_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

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
                [],
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
            $product_script = 'usctdp-mgmt-product-script';
            wp_enqueue_script(
                $product_script,
                plugin_dir_url(__FILE__) . "js/usctdp-mgmt-product.js",
                array('jquery'),
                $this->version,
                false
            );
            wp_localize_script($product_script, 'siteData', array(
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
            ));
        }
    }

    public function get_students()
    {
        return [];
    }
}
