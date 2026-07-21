<?php

class Usctdp_Mgmt_Woocommerce_Hooks
{
    private $hold_minutes = 10;
    public function __construct()
    {
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

    /**
     * Silently suppress the "Please choose product options" error when no variation_id
     * is present. WooCommerce's add_to_cart_handler_variable() generates this notice
     * after the woocommerce_add_to_cart_validation filter, so returning false here
     * exits the handler before wc_add_notice() is ever called.
     *
     * This catches all callers (AJAX, PayPal, standard POST) that fire the variable
     * product handler without a valid variation — including PayPal's change-cart and
     * simulate-cart endpoints that serialize the product form but omit variation data.
     * JS already handles the client-side "select options" UX, so no server notice is needed.
     */
    public function suppress_missing_variation_notice($passed, $product_id, $quantity, $variation_id = 0)
    {
        if (!empty($variation_id)) {
            return $passed;
        }
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            return false;
        }
        return $passed;
    }

    /**
     * Remove "Please choose product options" notices from the WC session.
     *
     * These accumulate when any AJAX endpoint (PayPal smart buttons, fragment refresh,
     * etc.) triggers add_to_cart_action without a variation_id. wc_print_notices() never
     * runs in those AJAX contexts, so the notices stay in the session and surface on
     * completely unrelated pages — cart, checkout, shop, wherever WooCommerce next
     * outputs notices.
     *
     * Registered on two hooks:
     *   shutdown  (priority 15, before WC saves the session at priority 20) — scrubs any
     *             notices generated during an AJAX request before they hit the DB.
     *   wp        (priority 100, before template rendering / notice output)  — scrubs any
     *             notices that were already persisted from a previous AJAX request so
     *             they never appear on page loads.
     */
    public function clear_stale_variation_notices()
    {
        if (!WC()->session) {
            return;
        }
        $notices = WC()->session->get('wc_notices', []);
        if (empty($notices['error'])) {
            return;
        }
        $target = 'Please choose product options';
        $filtered = array_values(array_filter($notices['error'], function ($notice) use ($target) {
            $text = is_array($notice) ? ($notice['notice'] ?? '') : $notice;
            return strpos(wp_strip_all_tags($text), $target) === false;
        }));
        if (count($filtered) === count($notices['error'])) {
            return;
        }
        $notices['error'] = $filtered;
        if (empty($notices['error'])) {
            unset($notices['error']);
        }
        WC()->session->set('wc_notices', $notices);
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

    public function display_before_cart_button()
    {
    }

    public function display_after_cart_button()
    {
    }

    public function display_after_variations_form()
    {
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
            'id' => $activity_id,
            'number' => 1,
        ]);
        $clinic = $clinic_query->items[0];
        return $this->int_to_day($clinic->day_of_week) . " at " . $clinic->start_time->format('g:i A');
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity)
    {
        $activities = [];
        if (isset($_POST['student_id'])) {
            $cart_item_data['student_id'] = intval($_POST['student_id']);
        }
        if (isset($_POST['day_of_week_1'])) {
            $id = intval($_POST['day_of_week_1']);
            $activities[] = $id;
            $cart_item_data['day_of_week_1'] = $id;
        }
        if (isset($_POST['day_of_week_2'])) {
            $id = intval($_POST['day_of_week_2']);
            $activities[] = $id;
            $cart_item_data['day_of_week_2'] = $id;
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
        $registration_table = $wpdb->prefix . 'usctdp_registration';
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $count_query_template = "
            SELECT COUNT(*) FROM $registration_table 
            WHERE activity_id = %d
            AND (status = %s
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
                    $active_statuses = [
                        "active",
                        "pending",
                    ];
                    if (in_array($existing_reg->status, $active_statuses)) {
                        if ($existing_reg->tracking_id !== $reg["tracking_id"]) {
                            $name = $student->title;
                            $class = $activity->title;
                            $msg = "$name is already enrolled in '$class'.";
                            throw new Usctdp_Checkout_Exception($msg, 'already_enrolled');
                        } else {
                            $already_reserved = true;
                        }
                    }
                }

                if ($already_reserved) {
                    continue;
                }

                $max_capacity = $activity->capacity;
                $count_query = $wpdb->prepare(
                    $count_query_template,
                    $reg["activity_id"],
                    "active",
                    "pending",
                    $this->hold_minutes
                );
                $current_count = $wpdb->get_var($count_query);
                if ($current_count >= $max_capacity) {
                    $msg = $activity->title . " is currently full.";
                    throw new Usctdp_Checkout_Exception($msg, 'out_of_stock');
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
                    'status' => 'pending',
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
                    throw new Usctdp_Checkout_Exception($msg, 'reservation_failed');
                }
            }
            $wpdb->query('COMMIT');
            $txn_commited = true;
        } catch (Usctdp_Checkout_Exception $ce) {
            $errors->add($ce->getSlug(), $ce->getMessage());
            Usctdp_Mgmt::logger()->log_error(
                'USCTDP: Error validating and reserving capacity: ' . $ce->getMessage()
            );
        } catch (Throwable $e) {
            $msg = 'A system error occurred while checking out. Please contact the office.';
            $errors->add('system-error', $msg);
            Usctdp_Mgmt::logger()->log_exception('Checkout error', $e);
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
        $item->add_meta_data('_tracking_id', $values['tracking_id']);
    }

    public function checkout_order_processed($order_id, $data, $order)
    {
        foreach ($order->get_items() as $item_id => $item) {
            $student_id  = $item->get_meta('_student_id');
            $tracking_id = $item->get_meta('_tracking_id');
            $activities  = $item->get_meta('_activities');
            foreach ($activities as $activity_id) {
                $query = new Usctdp_Mgmt_Registration_Query([
                    'student_id'  => $student_id,
                    'activity_id' => $activity_id,
                    'tracking_id' => $tracking_id,
                    'status'      => 'pending',
                ]);
                if (!empty($query->items)) {
                    $query->update_item($query->items[0]->id, [
                        'order_id' => $order_id,
                    ]);
                } else {
                    Usctdp_Mgmt::logger()->log_error(
                        "USCTDP: No pending registration found for order $order_id, " .
                        "student $student_id, activity $activity_id, tracking $tracking_id"
                    );
                }
            }
        }
    }

    public function confirm_registration($order_id)
    {
        $query = new Usctdp_Mgmt_Registration_Query([
            'order_id' => $order_id,
            'status' => 'pending'
        ]);
        foreach ($query->items as $item) {
            $query->update_item($item->id, [
                "status" => "active",
                "last_modified_at" => current_time('mysql'),
                "last_modified_by" => get_current_user_id(),
            ]);
        }
    }

    public function create_purchase_and_ledger_entries($order_id)
    {
        global $wpdb;
        $txn_started   = false;
        $txn_committed = false;

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                Usctdp_Mgmt::logger()->log_error("USCTDP: Order $order_id not found for purchase/ledger creation.");
                return;
            }

            $user_id = $order->get_customer_id();
            $family_query = new Usctdp_Mgmt_Family_Query(['user_id' => $user_id, 'number' => 1]);
            if (empty($family_query->items)) {
                Usctdp_Mgmt::logger()->log_error("USCTDP: No family found for user $user_id on order $order_id.");
                return;
            }
            $family_id      = $family_query->items[0]->id;
            $payment_method = $order->get_payment_method();
            $reference_id   = $order->get_transaction_id() ?: (string) $order_id;
            $created_at     = current_time('mysql');
            $created_by     = get_current_user_id();

            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach ($order->get_items() as $item) {
                $student_id = $item->get_meta('_student_id');
                if (!$student_id) {
                    continue;
                }

                $tracking_id = $item->get_meta('_tracking_id');
                $day_1_id    = $item->get_meta('_day_1_id');
                $day_2_id    = $item->get_meta('_day_2_id');
                $item_total  = floatval($item->get_total());

                $activity_ids = [];
                if ($day_1_id) {
                    $activity_ids[] = intval($day_1_id);
                }
                if ($day_2_id) {
                    $activity_ids[] = intval($day_2_id);
                }
                if (empty($activity_ids)) {
                    continue;
                }

                $wc_product_id = $item->get_product_id();
                $product_query = new Usctdp_Mgmt_Product_Query(['woocommerce_id' => $wc_product_id, 'number' => 1]);
                if (empty($product_query->items)) {
                    throw new Exception("USCTDP Product not found for WC product $wc_product_id on order $order_id.");
                }
                $product = $product_query->items[0];

                $activities = [];
                foreach ($activity_ids as $activity_id) {
                    $aq = new Usctdp_Mgmt_Activity_Query(['id' => $activity_id, 'number' => 1]);
                    if (empty($aq->items)) {
                        throw new Exception("Activity $activity_id not found for order $order_id.");
                    }
                    $activities[$activity_id] = $aq->items[0];
                }

                if (count($activity_ids) === 1) {
                    $activity_prices = [$activity_ids[0] => $item_total];
                } else {
                    $pricing = Usctdp_Mgmt_Model::get_activity_pricing($activities[$activity_ids[0]]);
                    if (!$pricing) {
                        throw new Exception("Pricing not found for activity {$activity_ids[0]} on order $order_id.");
                    }
                    $pricing_data         = $pricing->pricing;
                    $day1_price           = floatval($pricing_data['One']);
                    $activity_prices      = [
                        $activity_ids[0] => $day1_price,
                        $activity_ids[1] => $item_total - $day1_price,
                    ];
                }

                $ledger_query = new Usctdp_Mgmt_Ledger_Query();
                foreach ($activity_ids as $activity_id) {
                    $reg_query = new Usctdp_Mgmt_Registration_Query([
                        'order_id'    => $order_id,
                        'student_id'  => $student_id,
                        'activity_id' => $activity_id,
                        'number'      => 1,
                    ]);
                    if (empty($reg_query->items)) {
                        throw new Exception("Registration not found for order $order_id, student $student_id, activity $activity_id.");
                    }
                    $registration = $reg_query->items[0];
                    if (!empty($registration->purchase_id)) {
                        continue;
                    }

                    $purchase_query = new Usctdp_Mgmt_Purchase_Query();
                    $purchase_id    = $purchase_query->add_item([
                        'product_id'  => $product->id,
                        'family_id'   => $family_id,
                        'student_id'  => $student_id,
                        'tracking_id' => $tracking_id,
                        'type'        => 'registration',
                        'created_at'  => $created_at,
                        'created_by'  => $created_by,
                    ]);
                    if (!$purchase_id) {
                        throw new Exception("Failed to create purchase for order $order_id, activity $activity_id.");
                    }

                    $reg_query->update_item($registration->id, ['purchase_id' => $purchase_id]);
                    $price          = $activity_prices[$activity_id];
                    $activity_title = $activities[$activity_id]->title;

                    $ledger_query->add_item([
                        'purchase_id'    => $purchase_id,
                        'family_id'      => $family_id,
                        'order_id'       => $order_id,
                        'event_id'       => 'wc_order' . $order_id,
                        'event'          => 'WooCommerce Order ' . $order_id,
                        'account'        => 'registration_fees',
                        'entry_type'     => 'charge',
                        'description'    => 'Order placed in online store.',
                        'payment_method' => $payment_method,
                        'reference_id'   => $reference_id,
                        'debit'          => $price,
                        'credit'         => 0,
                        'created_at'     => $created_at,
                        'created_by'     => $created_by,
                    ]);

                    $ledger_query->add_item([
                        'purchase_id'    => $purchase_id,
                        'family_id'      => $family_id,
                        'order_id'       => $order_id,
                        'event_id'       => 'wc_order' . $order_id,
                        'event'          => 'WooCommerce Order ' . $order_id,
                        'account'        => 'registration_fees',
                        'entry_type'     => 'payment',
                        'description'    => 'Order paid in online store.',
                        'payment_method' => $payment_method,
                        'reference_id'   => $reference_id,
                        'debit'          => 0,
                        'credit'         => $price,
                        'created_at'     => $created_at,
                        'created_by'     => $created_by,
                    ]);
                }
            }

            $wpdb->query('COMMIT');
            $txn_committed = true;
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('USCTDP: create_purchase_and_ledger_entries', $e);
        } finally {
            if ($txn_started && !$txn_committed) {
                $wpdb->query('ROLLBACK');
            }
        }
    }

    /**
     * Records the payment side of the ledger for purchases created via the admin
     * invoicing flow (Usctdp_Mgmt_Woocommerce::create_woocommerce_order()).
     *
     * That flow books the charge immediately but, for card payments, defers the
     * actual payment entry until the customer completes payment through the
     * WooCommerce order-pay link - the card may fail or the customer may not pay
     * right away. This fills in that payment entry once WooCommerce confirms the
     * order is paid. It's a no-op for order items with no '_purchase_id' meta
     * (self-checkout orders, handled by create_purchase_and_ledger_entries) and
     * for purchases that already have a payment entry recorded (cash/check orders,
     * which record their payment entry client-side at invoice creation).
     */
    public function record_deferred_payment($order_id)
    {
        global $wpdb;
        $txn_started   = false;
        $txn_committed = false;

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                Usctdp_Mgmt::logger()->log_error("USCTDP: Order $order_id not found for deferred payment recording.");
                return;
            }

            $payment_method = $order->get_payment_method();
            $reference_id   = $order->get_transaction_id() ?: (string) $order_id;
            $created_at     = current_time('mysql');
            $created_by     = get_current_user_id();
            $event_id       = 'wc_order' . $order_id;
            $event          = 'WooCommerce Order ' . $order_id;

            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach ($order->get_items() as $item) {
                $purchase_id = intval($item->get_meta('_purchase_id'));
                if (!$purchase_id) {
                    continue;
                }

                $existing_payment = new Usctdp_Mgmt_Ledger_Query([
                    'purchase_id' => $purchase_id,
                    'entry_type'  => 'payment',
                    'number'      => 1,
                ]);
                if (!empty($existing_payment->items)) {
                    continue;
                }
                $purchase_query = new Usctdp_Mgmt_Purchase_Query(['id' => $purchase_id, 'number' => 1]);
                if (empty($purchase_query->items)) {
                    throw new Exception("USCTDP Purchase $purchase_id not found for order $order_id.");
                }
                $purchase = $purchase_query->items[0];
                $price    = floatval($item->get_total());

                $ledger_query = new Usctdp_Mgmt_Ledger_Query();
                $ledger_query->add_item([
                    'purchase_id'    => $purchase_id,
                    'family_id'      => $purchase->family_id,
                    'order_id'       => $order_id,
                    'event_id'       => $event_id,
                    'event'          => $event,
                    'account'        => 'payment_' . $payment_method,
                    'entry_type'     => 'payment',
                    'description'    => 'Payment received in online store.',
                    'payment_method' => $payment_method,
                    'reference_id'   => $reference_id,
                    'debit'          => $price,
                    'credit'         => 0,
                    'created_at'     => $created_at,
                    'created_by'     => $created_by,
                ]);

                $ledger_query->add_item([
                    'purchase_id'    => $purchase_id,
                    'family_id'      => $purchase->family_id,
                    'order_id'       => $order_id,
                    'event_id'       => $event_id,
                    'event'          => $event,
                    'account'        => $purchase->type . '_fees',
                    'entry_type'     => 'payment',
                    'description'    => 'Payment received in online store.',
                    'payment_method' => $payment_method,
                    'reference_id'   => $reference_id,
                    'debit'          => 0,
                    'credit'         => $price,
                    'created_at'     => $created_at,
                    'created_by'     => $created_by,
                ]);
            }

            $wpdb->query('COMMIT');
            $txn_committed = true;
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('USCTDP: record_deferred_payment', $e);
        } finally {
            if ($txn_started && !$txn_committed) {
                $wpdb->query('ROLLBACK');
            }
        }
    }
}
