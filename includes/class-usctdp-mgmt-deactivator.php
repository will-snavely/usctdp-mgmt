<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.wsnavely.com
 * @since      1.0.0
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 * @author     Will Snavely <will.snavely@gmail.com>
 */
class Usctdp_Mgmt_Deactivator
{
    public static function deactivate() {
        $admin_caps = ["register_student"];
        $role = get_role( 'administrator' );
        if($role) {
            foreach($admin_caps as $cap) {
                if($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}
