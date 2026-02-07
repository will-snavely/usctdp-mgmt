<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.wsnavely.com
 * @since      1.0.0
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 * @author     Will Snavely <will.snavely@gmail.com>
 */
class Usctdp_Mgmt
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Usctdp_Mgmt_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined("USCTDP_MGMT_VERSION")) {
            $this->version = USCTDP_MGMT_VERSION;
        } else {
            $this->version = "1.0.0";
        }
        $this->plugin_name = "usctdp-mgmt";

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_model_hooks();
        $this->define_woocommerce_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Usctdp_Mgmt_Loader. Orchestrates the hooks of the plugin.
     * - Usctdp_Mgmt_i18n. Defines internationalization functionality.
     * - Usctdp_Mgmt_Admin. Defines all hooks for the admin area.
     * - Usctdp_Mgmt_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-usctdp-mgmt-loader.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-usctdp-mgmt-model.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-usctdp-mgmt-woocommerce.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-usctdp-mgmt-i18n.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "admin/class-usctdp-mgmt-admin.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "public/class-usctdp-mgmt-public.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/class-usctdp-mgmt-logger.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/docgen/class-usctdp-mgmt-docgen.php";

        $this->loader = new Usctdp_Mgmt_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Usctdp_Mgmt_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {
        $plugin_i18n = new Usctdp_Mgmt_i18n();

        $this->loader->add_action(
            "plugins_loaded",
            $plugin_i18n,
            "load_plugin_textdomain",
        );
    }

    /**
     * Register all of the hooks related to the plugin data model.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_model_hooks()
    {
        $model = new Usctdp_Mgmt_Model();
        $this->loader->add_action("init", $model, "register_berlindb_entities");
    }

    private function define_woocommerce_hooks()
    {
        $commerce_handler = new Usctdp_Mgmt_Woocommerce();
        $this->loader->add_action(
            'woocommerce_before_variations_form',
            $commerce_handler,
            'display_before_variations_form',
        );
        $this->loader->add_action(
            'woocommerce_before_variations_table',
            $commerce_handler,
            'display_before_variations_table',
        );
        $this->loader->add_action(
            'woocommerce_after_variations_table',
            $commerce_handler,
            'display_after_variations_table',
        );
        $this->loader->add_action(
            'woocommerce_before_add_to_cart_button',
            $commerce_handler,
            'display_before_cart_button',
        );
        $this->loader->add_action(
            'woocommerce_after_add_to_cart_button',
            $commerce_handler,
            'display_after_cart_button',
        );
        $this->loader->add_action(
            'woocommerce_after_variations_form',
            $commerce_handler,
            'display_after_variations_form',
        );
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Usctdp_Mgmt_Admin(
            $this->get_plugin_name(),
            $this->get_version(),
        );

        $this->loader->add_action(
            "admin_enqueue_scripts",
            $plugin_admin,
            "enqueue_styles",
        );

        $this->loader->add_action(
            "admin_enqueue_scripts",
            $plugin_admin,
            "enqueue_scripts",
        );

        $this->loader->add_action(
            "admin_menu",
            $plugin_admin,
            "add_admin_menu",
        );

        $this->loader->add_action(
            "admin_notices",
            $plugin_admin,
            "show_admin_notice",
        );

        foreach (Usctdp_Mgmt_Admin::$post_handlers as $handler) {
            $this->loader->add_action(
                'admin_post_' . $handler["submit_hook"],
                $plugin_admin,
                $handler["callback"]
            );
        }

        foreach (Usctdp_Mgmt_Admin::$ajax_handlers as $handler) {
            $this->loader->add_action(
                'wp_ajax_' . $handler["action"],
                $plugin_admin,
                $handler["callback"]
            );
        }

        $this->loader->add_action(
            'admin_init',
            $plugin_admin,
            'usctdp_google_oauth_handler'
        );

        $this->loader->add_action(
            'admin_init',
            $plugin_admin,
            'settings_init'
        );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Usctdp_Mgmt_Public(
            $this->get_plugin_name(),
            $this->get_version(),
        );

        $this->loader->add_action(
            "wp_enqueue_scripts",
            $plugin_public,
            "enqueue_styles",
        );
        $this->loader->add_action(
            "wp_enqueue_scripts",
            $plugin_public,
            "enqueue_scripts",
        );

        $this->loader->add_action(
            "rest_api_init",
            $plugin_public,
            "usctdp_rest_api_init",
        );
    }


    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Usctdp_Mgmt_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
