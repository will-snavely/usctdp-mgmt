<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.wsnavely.com
 * @since             1.0.0
 * @package           Usctdp_Mgmt
 *
 * @wordpress-plugin
 * Plugin Name:       USCTDP Management
 * Plugin URI:        https://www.usctdp.com
 * Description:       This is a description of the plugin.
 * Version:           1.0.0
 * Author:            Will Snavely
 * Author URI:        https://www.wsnavely.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       usctdp-mgmt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die();
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define("USCTDP_MGMT_VERSION", "1.0.0");

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-usctdp-mgmt-activator.php
 */
function activate_usctdp_mgmt()
{
    require_once plugin_dir_path(__FILE__) .
        "includes/class-usctdp-mgmt-activator.php";
    Usctdp_Mgmt_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-usctdp-mgmt-deactivator.php
 */
function deactivate_usctdp_mgmt()
{
    require_once plugin_dir_path(__FILE__) .
        "includes/class-usctdp-mgmt-deactivator.php";
    Usctdp_Mgmt_Deactivator::deactivate();
}

register_activation_hook(__FILE__, "activate_usctdp_mgmt");
register_deactivation_hook(__FILE__, "deactivate_usctdp_mgmt");

function wporg_register_taxonomy_course()
{
    $labels = [
        "name" => _x("Courses", "taxonomy general name"),
        "singular_name" => _x("Course", "taxonomy singular name"),
        "search_items" => __("Search Courses"),
        "all_items" => __("All Courses"),
        "parent_item" => __("Parent Course"),
        "parent_item_colon" => __("Parent Course:"),
        "edit_item" => __("Edit Course"),
        "update_item" => __("Update Course"),
        "add_new_item" => __("Add New Course"),
        "new_item_name" => __("New Course Name"),
        "menu_name" => __("Course"),
    ];
    $args = [
        "hierarchical" => true, // make it hierarchical (like categories)
        "labels" => $labels,
        "show_ui" => true,
        "show_admin_column" => true,
        "query_var" => true,
        "rewrite" => ["slug" => "course"],
    ];
    register_taxonomy("course", ["post"], $args);
}
add_action("init", "wporg_register_taxonomy_course");

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . "includes/class-usctdp-mgmt.php";

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_usctdp_mgmt()
{
    $plugin = new Usctdp_Mgmt();
    $plugin->run();
}
run_usctdp_mgmt();
