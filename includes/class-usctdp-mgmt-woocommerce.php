<?php

/**
 * The commerce-specific functionality of the plugin.
 *
 * @link       https://www.wsnavely.com
 * @since      1.0.0
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 */
class Usctdp_Mgmt_Woocommerce
{
    private $clinic;
    private $classes_by_day;
    private $activity_type;

    public function __construct()
    {        
    }

    private function load_context() {
        global $product;
        $query = new Usctdp_Mgmt_Product_Link_Query([
            'product_id' => $product->get_id(),
            'number' => 1,
        ]);
    }

    public function display_before_variations_form() {
        echo "<div><p> Before Variations Form</p></div>";
    }
 
    public function display_before_variations_table() {
        echo "<div><p> Before Variations Table</p></div>";
    }
 
    public function display_after_variations_table() {
        $current_user_id = get_current_user_id();
        if(current_user_can('register_student')) {
            $this->render_admin_shop_options();
        } else {
            $family_query = new Usctdp_Mgmt_Family_Query([
                "user_id" => $current_user_id,
                "number" => 1
            ]);
            if(!empty($family_query->items)) {
                $this->render_user_shop_options($family_query->items[0]);
            }
        }
    } 

    private function render_admin_shop_options() {
        echo '<div id="usctdp-extra">';
        echo   '<div id="usctdp-person-selector">';
        echo     '<div id="usctdp-family-selector">';
        echo       '<label for="family-name">Select Family: </label>';
        echo       '<select name="family_name" id="family-name" required></select>';
        echo     '</div>';
        echo     '<div id="usctdp-student-selector">';
        echo       '<label for="student-name">Select Student: </label>';
        echo       '<select name="student_name" id="student-name" required></select>';
        echo       '<button id="new-student-button" class="button">Add New Student</button>';
        echo     '</div>';
        echo   '</div>';
        echo   '<div id="usctdp-day-selector">';
        echo   '</div>';
        echo '</div>';
    }

    private function render_user_shop_options() {
        echo '<div id="usctdp-woocommerce-extra">';
        echo   '<div id="usctdp-person-selector">';
        echo     '<label for="student-name">Select Student: </label>';
        echo     '<select name="student_name" id="student-name" required></select>';
        echo     '<button id="new-student-button" class="button">Add New Student</button>';
        echo   '</div>';
        echo   '<div id="usctdp-day-selector"></div>';
        echo '</div>';
    }

    public function display_before_cart_button() {
    } 

    public function display_after_cart_button() {
        echo "<div><p> After Cart Button</p></div>";
    } 

    public function display_after_variations_form() {
        echo "<div><p> After Variations Form</p></div>";
    } 
}
