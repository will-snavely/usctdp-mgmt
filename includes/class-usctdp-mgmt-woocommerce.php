<?php

class CheckoutException extends Exception {
    private string $slug;

    public function __construct($message, $slug, $code = 0, ?Throwable $previous = null) {
        $this->slug = $slug;
        parent::__construct($message, $code, $previous);
    }

    public function getSlug(): string {
        return $this->slug;
    }
}

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
                        <label for="student_select">Student</label>
                    </div>
                    <div id="student_select_wrapper">
                        <select name="student_id" id="student_select" required></select>
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

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity)
    {
        $activities = [];
        if (isset($_POST['student_id'])) {
            $cart_item_data['student_id'] = $_POST['student_id'];
        }
        if (isset($_POST['day_of_week_1'])) {
            $activities[] = $_POST['day_of_week_1'];
            $cart_item_data['day_of_week_1'] = $_POST['day_of_week_1'];
        }
        if (isset($_POST['day_of_week_2'])) {
            $activities[] = $_POST['day_of_week_2'];
            $cart_item_data['day_of_week_2'] = $_POST['day_of_week_2'];
        }
        $cart_item_data['activities'] = $activities;
        $cart_item_data['tracking_id'] = uniqid("usctdp_", true);
        return $cart_item_data;
    }

    public function get_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['student_id'])) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $cart_item['student_id'],
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

    private function parse_cart_data($errors)
    {
        $registrations = [];
        $all_activities = [];
        $all_students = [];
        $cart_data_valid = true;

        foreach (WC()->cart->get_cart() as $item) {
            $tracking_id = $item['tracking_id'];
            $student_id = $item['student_id'];
            $item_activities = $item['activities'];

            if (!isset($all_students[$student_id])) {
                $student_query = new Usctdp_Mgmt_Student_Query([
                    'id' => $student_id,
                    'number' => 1,
                ]);
                if (empty($student_query->items)) {
                    $errors->add('invalid_student', "$student_id is not a valid student id.");
                    $cart_data_valid = false;
                    continue;
                }
                $all_students[$student_id] = $student_query->items[0];
            }

            foreach ($item_activities as $activity_id) {
                if (empty($activity_id)) {
                    continue;
                }
                if (!isset($all_activities[$activity_id])) {
                    $activity_query = new Usctdp_Mgmt_Activity_Query([
                        'id' => $activity_id,
                        'number' => 1,
                    ]);
                    if (empty($activity_query->items)) {
                        $errors->add('invalid_class', "$activity_id is not a valid activity id.");
                        $cart_data_valid = false;
                        continue;
                    }
                    $all_activities[$activity_id] = $activity_query->items[0];
                }

                $registrations[] = [
                    "student_id" => $student_id,
                    "activity_id" => $activity_id,
                    "tracking_id" => $tracking_id,
                    "cart_item" => $item
                ];
            }
        }

        return [
            "result" => $cart_data_valid,
            "registrations" => $registrations,
            "students" => $all_students,
            "activities" => $all_activities
        ];
    }

    public function after_checkout_validation($data, $errors)
    {
        global $wpdb;
        error_log("after_checkout_validation");
        
        $registration_table = $wpdb->prefix . 'usctdp_registration';
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $count_query_template = "
            SELECT COUNT(*) FROM $registration_table 
            WHERE activity_id = %d
            AND (status = %d 
            OR (status = %d AND created_at > NOW() - INTERVAL %d MINUTE))";
        $activity_lock_template = "SELECT * FROM $activity_table WHERE id=%d FOR UPDATE";
        $txn_started = false;
        $txn_commited = false;

        try {
            $parsed_cart = $this->parse_cart_data($errors);
            if (!$parsed_cart["result"]) {
                return;
            }

            $registrations = $parsed_cart["registrations"];
            $activities = $parsed_cart["activities"];
            $students = $parsed_cart["students"];

            // We need to lock the activities in order to prevent race conditions
            ksort($activities);

            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach ($activities as $activity_id => $activity) {
                $activity_lock = $wpdb->prepare($activity_lock_template, $activity_id);
                $wpdb->get_row($activity_lock);
            }

            foreach ($registrations as $reg) {
                $cart_item = $reg["cart_item"];
                $student = $students[$reg["student_id"]];
                $activity = $activities[$reg["activity_id"]];
                $already_reserved = false;

                $reg_query = new Usctdp_Mgmt_Registration_Query([
                    'student_id' => $reg["student_id"],
                    'activity_id' => $reg["activity_id"],
                    'number' => 1
                ]);
                if (!empty($reg_query->items)) {
                    $existing_reg = $reg_query->items[0];
                    if($existing_reg->tracking_id !== $reg["tracking_id"]) {
                        $name = $student->title;
                        $class = $activity->title;
                        $msg = "$name is already enrolled in '$class'.";
                        throw new CheckoutException($msg, 'already_enrolled');
                    } else {
                        $already_reserved = true;
                    }
                } 
            
                if($already_reserved) {
                    continue;
                }

                $max_capacity = $activity->capacity;
                $count_query = $wpdb->prepare(
                    $count_query_template,
                    $reg["activity_id"],
                    Usctdp_Registration_Status::Confirmed->value,
                    Usctdp_Registration_Status::Pending->value,
                    $this->hold_minutes
                );
                $current_count = $wpdb->get_var($count_query);
                if ($current_count >= $max_capacity) {
                    $msg = $activity->title . " is currently full.";
                    throw new CheckoutException($msg, 'out_of_stock');
                }

                $current_time = current_time('mysql');
                $reg_query = new Usctdp_Mgmt_Registration_Query();
                $result = $reg_query->add_item([
                    'activity_id' => $reg["activity_id"],
                    'student_id' => $reg["student_id"],
                    'tracking_id' => $reg["tracking_id"],
                    'student_level' => $student->level,
                    'credit' => 0,
                    'debit' => 0,
                    'status' => Usctdp_Registration_Status::Pending->value,
                    'created_at' => $current_time,
                    'created_by' => get_current_user_id(),
                    'last_modified_at' => $current_time,
                    'last_modified_by' => get_current_user_id(),
                    'notes' => '',
                ]);
                if (!$result) {
                    $msg = "An error occurred while creating the reservation ";
                    $msg .= " for " . $activity->title . ".";
                    $msg .= " Try again or contact the office.";
                    throw new CheckoutException($msg, 'reservation_failed');
                }
            }
            $wpdb->query('COMMIT');
            $txn_commited = true;
        } catch (CheckoutException $ce) {
            $errors->add($ce->getSlug(), $ce->getMessage()); 
            Usctdp_Mgmt_Logger::getLogger()->log_error(
                'USCTDP: Error validating and reserving capacity: ' . $ce->getMessage()
            );
        } catch (Throwable $e) {
            $msg = 'A system error occurred while checking out. Please contact the office.';
            $errors->add('system-error', $msg); 
            $trace = $e->getTraceAsString();
            Usctdp_Mgmt_Logger::getLogger()->log_error($e->getMessage() . "\n" . $trace);
        } finally {
            if ($txn_started && !$txn_commited) {
                $wpdb->query('ROLLBACK');
            }
        }
    }

    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['student_id'])) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $values['student_id'],
                'number' => 1,
            ]);
            $student = $student_query->items[0];
            $item->add_meta_data('_student_id', $values['student_id']);
            $item->add_meta_data('Student Name', $student->title);
        }
        if (isset($values['day_of_week_1'])) {
            $item->add_meta_data('_day_1_id', $values['day_of_week_1']);
            $item->add_meta_data('Day 1', $this->get_clinic_display($values['day_of_week_1']));
        }
        if (isset($values['day_of_week_2'])) {
            $item->add_meta_data('_day_2_id', $values['day_of_week_2']);
            $item->add_meta_data('Day 2', $this->get_clinic_display($values['day_of_week_2']));
        }
        $item->add_meta_data('_activities', $values['activities']);
    }

    public function checkout_order_processed($order_id, $data, $order)
    {
        foreach ($order->get_items() as $item_id => $item) {
            $student_id = $item->get_meta('_student_id');
            $activities = $item->get_meta('_activities');
            foreach ($activities as $activity_id) {
                $query = new Usctdp_Mgmt_Registration_Query([
                    'student_id' => $student_id,
                    'activity_id' => $activity_id,
                ]);
                if (!empty($query->items)) {
                    $query->update_item($query->items[0]->id, [
                        'order_id' => $order_id,
                    ]);
                } else {

                }
            }
        }
    }

    public function confirm_registration($order_id)
    {
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
