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

/**
 * The commerce-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the commerce-specific stylesheet and JavaScript.
 *
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes
 * @author     Will Snavely <will.snavely@gmail.com>
 */
class Usctdp_Mgmt_Commerce
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
     * @param    string    $plugin_name    The name of the plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Syncs a usctdp-class post to a WooCommerce Product.
     *
     * @since 1.0.0
     * @param int     $post_id The Post ID.
     * @param WP_Post $post    The Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function sync_class_to_product($post_id, $post, $update)
    {
        // Check if this is a revision or not a usctdp-class
        if (wp_is_post_revision($post_id) || $post->post_type !== 'usctdp-class') {
            return;
        }

        // Ensure WooCommerce is active
        if (!class_exists('WC_Product')) {
            return;
        }

        // Get the mapped product ID
        $product_id = get_post_meta($post_id, '_usctdp_class_product_id', true);
        $product = null;

        if ($product_id) {
            $product = wc_get_product($product_id);
        }

        if (!$product) {
            $product = new WC_Product_Simple();
        }

        // Update Product Data
        $product->set_name($post->post_title);
        $product->set_slug($post->post_name);
        $product->set_status('publish'); // Or matches post status?
        $product->set_catalog_visibility('visible');
        $product->set_virtual(true); // Classes are virtual service

        // Pricing logic
        // Defaulting to one_day_price from ACF fields
        // Note: Field keys should be used if possible, or keys if known. 
        // Based on model file: field_usctdp_class_one_day_price
        $price = get_field('field_usctdp_class_one_day_price', $post_id);
        if (!$price) {
            // Fallback or handle empty price. 
            // Maybe try 'one_day_price' name if ACF hasn't flushed field keys?
            // Using get_field with the NAME usually works if ACF is loaded.
            $price = get_field('one_day_price', $post_id);
        }

        if ($price) {
            $product->set_regular_price($price);
            $product->set_price($price);
        }

        // Save Product
        $product_id = $product->save();

        // Update mapping
        if ($product_id) {
            update_post_meta($post_id, '_usctdp_class_product_id', $product_id);
            // Also store reference back
            update_post_meta($product_id, '_usctdp_source_class_id', $post_id);
        }
    }

    /**
     * Add custom fields to the checkout.
     *
     * @since 1.0.0
     * @param WC_Checkout $checkout The checkout object.
     */
    public function add_checkout_fields($checkout)
    {
        echo '<div id="usctdp_registration_fields"><h3>' . __('Student Registration', 'usctdp-mgmt') . '</h3>';

        // Student Name
        woocommerce_form_field('usctdp_student_name', array(
            'type'          => 'text',
            'class'         => array('my-field-class form-row-wide'),
            'label'         => __('Student Name', 'usctdp-mgmt'),
            'placeholder'   => __('Enter the student\'s name', 'usctdp-mgmt'),
            'required'      => true,
        ), $checkout->get_value('usctdp_student_name'));

        // Number of Days
        woocommerce_form_field('usctdp_enrollment_days', array(
            'type'          => 'select',
            'class'         => array('my-field-class form-row-wide'),
            'label'         => __('Enrollment Duration', 'usctdp-mgmt'),
            'options'       => array(
                '1' => __('1 Day', 'usctdp-mgmt'),
                '2' => __('2 Days', 'usctdp-mgmt'),
            ),
            'required'      => true,
        ), $checkout->get_value('usctdp_enrollment_days'));

        // Class Days
        // Note: Ideally dynamically populated based on context, but hardcoded options for now as requested.
        woocommerce_form_field('usctdp_class_days', array(
            'type'          => 'multiselect', // Or select with multiple? WC supports 'multiselect' with plugins usually, but standard is select.
            // Using checkboxes might be better for UI but WC form field 'checkbox' is single.
            // Let's use a select for simplicity in standard WC.
            'class'         => array('my-field-class form-row-wide'),
            'label'         => __('Class Days', 'usctdp-mgmt'),
            'options'       => array(
                'Mon' => 'Monday',
                'Tue' => 'Tuesday',
                'Wed' => 'Wednesday',
                'Thu' => 'Thursday',
                'Fri' => 'Friday',
            ),
            'input_class' => array('wc-enhanced-select'), // Adds Select2 if available
            'custom_attributes' => array('multiple' => 'multiple'),
        ), $checkout->get_value('usctdp_class_days'));

        echo '</div>';
    }

    /**
     * Update the order meta with field values.
     *
     * @since 1.0.0
     * @param int $order_id
     */
    public function checkout_field_update_order_meta($order_id)
    {
        if (!empty($_POST['usctdp_student_name'])) {
            update_post_meta($order_id, 'usctdp_student_name', sanitize_text_field($_POST['usctdp_student_name']));
        }
        if (!empty($_POST['usctdp_enrollment_days'])) {
            update_post_meta($order_id, 'usctdp_enrollment_days', sanitize_text_field($_POST['usctdp_enrollment_days']));
        }
        if (!empty($_POST['usctdp_class_days'])) {
            // sanitize_text_field might not handle arrays well if multiselect.
            $days = $_POST['usctdp_class_days'];
            if (is_array($days)) {
                $days = implode(', ', $days);
            }
            update_post_meta($order_id, 'usctdp_class_days', sanitize_text_field($days));
        }
    }

    /**
     * Display fields in Admin Order view
     */
    public function admin_order_data_after_billing_address($order)
    {
        echo '<p><strong>' . __('Student Name') . ':</strong> ' . get_post_meta($order->get_id(), 'usctdp_student_name', true) . '</p>';
        echo '<p><strong>' . __('Enrollment Days') . ':</strong> ' . get_post_meta($order->get_id(), 'usctdp_enrollment_days', true) . '</p>';
        echo '<p><strong>' . __('Class Days') . ':</strong> ' . get_post_meta($order->get_id(), 'usctdp_class_days', true) . '</p>';
    }
}
