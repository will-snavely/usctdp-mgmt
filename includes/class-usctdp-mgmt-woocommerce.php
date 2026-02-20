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

    public function display_before_cart_button()
    {
    }

    public function display_after_cart_button()
    {
    }

    public function display_after_variations_form()
    {
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity)
    {
        error_log("Adding cart item data");
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
        error_log("checkout_create_order_line_item");
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
        return $activity->capacity;
    }


    private function parse_cart_data($errors) {
        $registrations = [];
        $activities = [];
        $students = [];
        $cart_data_valid = true;

        foreach (WC()->cart->get_cart() as $item) {
            $student_id = $item->get_meta('student_id');
            if(!isset($students[$student_id])) {
                $student_query = new Usctdp_Mgmt_Student_Query([
                    'id' => $student_id,
                    'number' => 1,
                ]);
                if(empty($student_query->items)) {
                    $errors->add('invalid_student', "$student_id is not a valid student id.");
                    $cart_data_valid = false;
                    continue;
                }
                $students[$student_id] = $student_query->items[0];
            }
 
            foreach([$day_1_id, $day_2_id] as $day) {
                if(empty($day)) { 
                    continue;
                }
                if(!isset($activities[$day])) {
                    $activity_query = new Usctdp_Mgmt_Activity_Query([
                        'id' => $activity_id,
                        'number' => 1,
                    ]);
                    if(empty($activity_query->items)) {
                        $errors->add('invalid_class', "$activity_id is not a valid activity id.");
                        $cart_data_valid = false;
                        continue;
                    }
                    $activities[$day] = $activity_query->items[0];
                }

                $registrations[] = [
                    "student_id" => $student_id,
                    "activity_id" => $day,
                    "cart_item" => $item
                ];
            }
        }

        return [
            "result": $cart_data_valid,
            "registrations": $registrations,
            "students": $students,
            "activities": $activities
        ];
    }

    public function validate_and_reserve_capacity($data, $errors)
    {
        global $wpdb;
        error_log("in validate_and_reserve_capacity");
            
        $registration_table = $wpdb->prefix . 'usctdp_registration';
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $cart_data_valid = true;

        $parsed_cart = $this->parse_cart_data($errors);
        if(!$parsed_card["valid"]) {
            return;
        }

        $registrations = $parsed_cart["registrations"];
        $activities = $parsed_cart["activities"];
        $students = $parsed_cart["students"];

        $count_query_template = "
            SELECT COUNT(*) FROM $registration_table 
            WHERE activity_id = %d
            AND (status = %d 
            OR (status = %d AND created_at > NOW() - INTERVAL %d MINUTE))";

        $activity_lock_template = "SELECT * FROM $activity_table WHERE id=%d FOR UPDATE";
        $txn_started = false;
        $txn_commited = false;
        ksort($activities);

        try {
            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach($activities as $activity_id => $activity) {
                $activity_lock = $wpdb->prepare($activity_lock_template, $activity_id);
                $wpdb->get_row($activity_lock);
            }

            foreach($registrations as $reg) {
                $student = $students[$reg["student_id"]];
                $activity = $activities[$reg["activity_id"]];
                $max_capacity = $activity->capacity;
                $count_query = $wpdb->prepare(
                    $count_template,
                    $item["activity_id"],
                    Usctdp_Registration_Status::Confirmed->value,
                    Usctdp_Registration_Status::Pending->value,
                    $this->hold_minutes);
                $current_count = $wpdb->get_var($count_query);
                if ($current_count >= $max_capacity) {
                    $errors->add('out_of_stock', 'Sorry, "' . $activity->title . '" is currently full.');
                    break;
                }

                $reg_query = new Usctdp_Mgmt_Registration_Query([
                    'student_id' => $student_id,
                    'activity_id' => $activity_id
                ]);
                if(!empty($reg_query->items)) {
                    $name = $student->title;
                    $class = $activity->title;
                    $errors->add('already_enrolled', "$name is already enrolled in '$class'.");
                    break;
                }

                $reg_query = new Usctdp_Mgmt_Registration_Query();
                $result = $reg_query->add_item([
                    $item["activity_id"],
                    'activity_id' => $item["activity_id"],
                    'student_id' => $item["student_id"],
                    'order_id' => null,
                    'student_level' => $student->level,
                    'credit' => 0,
                    'debit' => 0,
                    'status' => Usctdp_Registration_Status::Pending->value,
                    'created_at' => current_time('mysql'),
                    'last_modified_at' => current_time('mysql'),
                    'last_modified_by' => get_current_user_id(),
                    'notes' => '',
                ]);
                
            }

            $wpdb->query('COMMIT');
            $txn_commited = true;
        } catch(Throwable e) {
            $errors->add("An unexpected error occurred during checkout.");
        } finally {
            if(!$txn_commited) {
                $wpdb->query('ROLLBACK');
            }
        }
    }

    public function attach_order_to_registration($order)
    {
        error_log("creating registration");
        foreach ($order->get_items() as $item_id => $item) {
            $student_id = $item->get_meta('student_id');
            $day_1_id = $item->get_meta('day_1_id');
            $day_2_id = $item->get_meta('day_2_id');
            foreach ([$day_1_id, $day_2_id] as $day_id) {
                if (empty($day_id)) {
                    continue;
                }
                $query = new Usctdp_Mgmt_Registration_Query();
            }
        }
    }

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
