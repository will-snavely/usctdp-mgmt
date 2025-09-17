<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.wsnavely.com
 * @since      1.0.0
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 * @author     Will Snavely <will.snavely@gmail.com>
 */
class Usctdp_Mgmt_i18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            "usctdp-mgmt",
            false,
            dirname(dirname(plugin_basename(__FILE__))) . "/languages/",
        );
    }
}
