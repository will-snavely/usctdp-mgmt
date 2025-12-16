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

if (file_exists(dirname(__DIR__, 3) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
} else {
}

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
    if (defined('WP_CLI') && WP_CLI) {
        include_once(dirname(__FILE__) . '/includes/cli-commands.php');
    }
}

run_usctdp_mgmt();
