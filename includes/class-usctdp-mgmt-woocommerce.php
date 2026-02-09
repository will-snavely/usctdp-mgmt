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
    public function __construct()
    {
    }

    private function load_context()
    {
        global $product;
        $query = new Usctdp_Mgmt_Product_Link_Query([
            'product_id' => $product->get_id(),
            'number' => 1,
        ]);
    }

    public function display_before_variations_form()
    {
    }

    public function display_before_variations_table()
    {
    }

    public function display_after_variations_table()
    {
        $current_user_id = get_current_user_id();
        if (current_user_can('register_student')) {
            $this->render_admin_shop_options();
        } else {
            $family_query = new Usctdp_Mgmt_Family_Query([
                "user_id" => $current_user_id,
                "number" => 1
            ]);
            if (!empty($family_query->items)) {
                $this->render_user_shop_options($family_query->items[0]);
            }
        }
    }

    private function render_admin_shop_options()
    {

    }

    private function render_user_shop_options($family)
    {
        $query = new Usctdp_Mgmt_Student_Query([
            'family_id' => $family->id,
        ]);
        $students = [];
        if (!empty($query->items)) {
            $students = $query->items;
        }
        ?>
        <div id="usctdp-woocommerce-extra" class="hidden">
            <div id="usctdp-student-selector">
                <div id="select_name_or_new">
                    <div id="student_label">
                        <label for="student_name_select">Student</label>
                    </div>
                    <select name="student_name" id="student_name_select" required>
                        <option value=""></option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student->id; ?>">
                                <?php echo $student->first . ' ' . $student->last; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button id="new-student-button" class="button">Add New...</button>
                </div>
            </div>
            <div id="usctdp-day-selectors"></div>
        </div>
        <?php
    }

    public function display_before_cart_button()
    {
    }

    public function display_after_cart_button()
    {
    }

    public function display_after_variations_form()
    {
    }
}
