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
        echo "<div><p> After Variations Table</p></div>";
    } 

    public function display_before_cart_button() {
        echo "<div><p>Before Cart Button</p></div>";
    } 

    public function display_after_cart_button() {
        echo "<div><p> After Cart Button</p></div>";
    } 

    public function display_after_variations_form() {
        echo "<div><p> After Variations Form</p></div>";
    } 
}
