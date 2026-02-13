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
    private $hold_minutes = 10;
    public function __construct() {}

    private function load_context()
    {
        global $product;
        $query = new Usctdp_Mgmt_Product_Link_Query([
            'product_id' => $product->get_id(),
            'number' => 1,
        ]);
    }

    public function display_before_single_product()
    {
?>
        <dialog id="new-student-modal">
            <form id="new-student-form" method="dialog">
                <h2>Add New Student</h2>
                <div class="student_field">
                    <label for="modal_first_name">First Name</label>
                    <input type="text" id="modal_first_name" name="first_name" required>
                </div>

                <div class="student_field">
                    <label for="modal_last_name">Last Name</label>
                    <input type="text" id="modal_last_name" name="last_name" required>
                </div>

                <div class="student_field">
                    <label for="modal_birthdate">Birthday</label>
                    <input type="date" id="modal_birthdate" name="birthdate" required>
                </div>

                <div class="actions">
                    <button type="button" class="button" id="close-modal">Cancel</button>
                    <button type="submit" class="button" id="save-student-modal">Save Student</button>
                </div>
            </form>
        </dialog>
    <?php
    }

    public function display_before_variations_form() {}

    public function display_before_variations_table() {}

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

    private function render_admin_shop_options() {}

    private function render_user_shop_options($family)
    {
    ?>
        <div id="usctdp-woocommerce-extra" class="force-hidden">
            <div id="usctdp-student-selector">
                <div id="select_name_or_new">
                    <div id="student_label">
                        <label for="student_name_select">Student</label>
                    </div>
                    <div id="student_name_select_wrapper">
                        <select name="student_name" id="student_name_select" required></select>
                    </div>
                    <div id="new_student_button_wrapper">
                        <button id="new-student-button" class="button">Add New...</button>
                    </div>
                </div>
            </div>
            <div id="usctdp-day-selectors"></div>
        </div>
<?php
    }

    public function display_before_cart_button() {}

    public function display_after_cart_button() {}

    public function display_after_variations_form() {}

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity)
    {
        if (isset($_POST['student_name'])) {
            $cart_item_data['student_name'] = $_POST['student_name'];
        }
        if (isset($_POST['day_of_week_1'])) {
            $cart_item_data['day_of_week_1'] = $_POST['day_of_week_1'];
        }
        if (isset($_POST['day_of_week_2'])) {
            $cart_item_data['day_of_week_2'] = $_POST['day_of_week_2'];
        }
        return $cart_item_data;
    }

    private function int_to_day($day_of_week)
    {
        $days = [
            1 => "Monday",
            2 => "Tuesday",
            3 => "Wednesday",
            4 => "Thursday",
            5 => "Friday",
            6 => "Saturday",
            7 => "Sunday",
        ];
        return $days[$day_of_week->value];
    }

    private function get_clinic_display($activity_id)
    {
        $clinic_query = new Usctdp_Mgmt_Clinic_Query([
            'activity_id' => $activity_id,
            'number' => 1,
        ]);
        $clinic = $clinic_query->items[0];
        return $this->int_to_day($clinic->day_of_week) . " at " . $clinic->start_time->format('g:i A');
    }

    public function get_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['student_name'])) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $cart_item['student_name'],
                'number' => 1,
            ]);
            $student = $student_query->items[0];
            $item_data[] = array(
                'key' => 'Student Name',
                'value' => $student->id,
                'display' => $student->title,
            );
        }
        if (isset($cart_item['day_of_week_1'])) {
            $clinic_id = intval($cart_item['day_of_week_1']);
            $item_data[] = array(
                'key' => 'Day 1',
                'value' => $clinic_id,
                'display' => $this->get_clinic_display($clinic_id)
            );
        }
        if (isset($cart_item['day_of_week_2'])) {
            $clinic_id = intval($cart_item['day_of_week_2']);
            $item_data[] = array(
                'key' => 'Day 2',
                'value' => $clinic_id,
                'display' => $this->get_clinic_display($clinic_id)
            );
        }
        return $item_data;
    }

    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['student_name'])) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $values['student_name'],
                'number' => 1,
            ]);
            $student = $student_query->items[0];
            $item->add_meta_data('student_id', $values['student_name']);
            $item->add_meta_data('Student Name', $student->title);
        }
        if (isset($values['day_of_week_1'])) {
            $item->add_meta_data('day_1_id', $values['day_of_week_1']);
            $item->add_meta_data('Day 1', $this->get_clinic_display($values['day_of_week_1']));
        }
        if (isset($values['day_of_week_2'])) {
            $item->add_meta_data('day_2_id', $values['day_of_week_2']);
            $item->add_meta_data('Day 2', $this->get_clinic_display($values['day_of_week_2']));
        }
    }

    private function get_class_capacity($activity_id)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'id' => $activity_id,
            'number' => 1,
        ]);
        $activity = $activity_query->items[0];
        return $activity->capacity;
    }

    public function validate_and_reserve_capacity($data, $errors)
    {
        global $wpdb;
        $registration_table = $wpdb->prefix . 'usctdp_registrations';

        // Loop through cart to find class products
        foreach (WC()->cart->get_cart() as $cart_item) {
            $student_id = null;
            $day_1_id = null;
            $day_2_id = null;

            // START TRANSACTION
            $wpdb->query('START TRANSACTION');
            $count_query = "
                SELECT COUNT(*) FROM $registration_table 
                WHERE activity_id = %d
                AND (status = 'confirmed' OR (status = 'pending' AND created_at > NOW() - INTERVAL %d MINUTE))
                FOR UPDATE";

            foreach ([$day_1_id, $day_2_id] as $day_id) {
                if ($day_id == null) {
                    continue;
                }
                $activity_query = new Usctdp_Mgmt_Activity_Query([
                    'id' => $day_id,
                    'number' => 1,
                ]);
                $activity = $activity_query->items[0];
                $max_capacity = $activity->capacity;

                // PESSIMISTIC LOCK: Count Confirmed + Recent Pendings
                $current_count = $wpdb->get_var($wpdb->prepare($count_query, $day_id, $this->hold_minutes));
                if ($current_count >= $max_capacity) {
                    $errors->add('out_of_stock', 'Sorry, "' . $activity->title . '" is currently full.');
                    $wpdb->query('ROLLBACK');
                    return;
                }
            }

            // If we reach here, a spot is available. 
            // We don't insert the row yet because we don't have the Order ID.
            // But the lock is held until the end of this validation script.
            $wpdb->query('COMMIT');
        }
    }

    public function transfer_item_meta($item, $cart_item_key, $values, $order) {}

    /**
     * STEP 2: The Insertion (Happens after validation passes)
     */
    public function create_pending_registration($order)
    {
        error_log("Here");
        foreach ($order->get_items() as $item_id => $item) {
            $student_id = $item->get_meta('student_id');
            $day_1_id = $item->get_meta('day_1_id');
            $day_2_id = $item->get_meta('day_2_id');

            if (empty($student_id)) {
                continue;
            }

            foreach ([$day_1_id, $day_2_id] as $day_id) {
                if (empty($day_id)) {
                    continue;
                }
                $query = new Usctdp_Mgmt_Registration_Query();
                $result = $query->add_item([
                    'activity_id' => $day_id,
                    'student_id' => $student_id,
                    'order_id' => $order->get_id(),
                    'student_level' => '1',
                    'credit' => 0,
                    'debit' => 0,
                    'status' => Usctdp_Registration_Status::Pending->value,
                    'created_at' => current_time('mysql'),
                    'last_modified_at' => current_time('mysql'),
                    'last_modified_by' => get_current_user_id(),
                    'notes' => '',
                ]);
                error_log("query result: " . strval($result));
            }
        }
    }

    /**
     * STEP 3: The Confirmation (Payment received)
     */
    public function confirm_registration($order_id)
    {
        error_log("confirming " . strval($order_id));
        $query = new Usctdp_Mgmt_Registration_Query([
            'order_id' => $order_id,
            'status' => Usctdp_Registration_Status::Pending->value,
        ]);
        foreach ($query->items as $item) {
            $query->update_item($item->id, [
                "status" => Usctdp_Registration_Status::Confirmed->value,
                "last_modified_at" => current_time('mysql'),
                "last_modified_by" => get_current_user_id(),
            ]);
        }
    }
}
