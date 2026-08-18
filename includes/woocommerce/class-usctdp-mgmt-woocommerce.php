<?php

class Usctdp_Mgmt_Woocommerce
{
    private function get_woo_product_by_id($product_id)
    {
        $product = Usctdp_Mgmt_Model::get_product($product_id);
        if (!$product) {
            throw new Usctdp_Woocommerce_Exception("Product with ID '$product_id' not found.");
        }
        return wc_get_product($product->woocommerce_id);
    }

    private function find_variations($product, $match_criteria)
    {
        if (!$product || !$product->is_type('variable')) {
            return null;
        }

        $results = [];
        foreach ($product->get_available_variations() as $variation_data) {
            $attrs = $variation_data['attributes'];
            $is_match = true;
            foreach ($match_criteria as $key => $value) {
                $search = 'attribute_' . sanitize_title($key);
                if (isset($attrs[$search])) {
                    if ($attrs[$search] !== '' && $attrs[$search] !== $value) {
                        $is_match = false;
                        break;
                    }
                } else {
                    $is_match = false;
                    break;
                }
            }

            if ($is_match) {
                $results[] = $variation_data['variation_id'];
            }
        }

        return $results;
    }

    private function find_variations_for_session($product_id, $session_id)
    {
        $product = Usctdp_Mgmt_Model::get_product($product_id);
        if (!$product) {
            throw new Usctdp_Woocommerce_Exception("Product with ID $product_id not found.");
        }

        $woo_product = wc_get_product($product->woocommerce_id);
        if (!$woo_product) {
            $id = $product->woocommerce_id;
            throw new Usctdp_Woocommerce_Exception("WooCommerce product with ID $id not found.");
        }

        $session_name = null;
        $session_meta = $woo_product->get_meta('_session_post_ids');
        foreach ($session_meta as $name => $session_id) {
            if ($session_id == $session_id) {
                $session_name = $name;
                break;
            }
        }
        if ($product->type === 'clinic') {
            return $this->find_variations($woo_product, [
                'session' => $session_name,
                'days-per-week' => "One",
            ]);
        } else {
            return $this->find_variations($woo_product, [
                'session' => $session_name,
            ]);
        }
    }

    public function create_woocommerce_order($family_id, $line_items, $payment_method, $check_number = null)
    {
        // Validate the registrations in the order. 
        // All registrations should have a valid registration id.
        foreach ($line_items as $line_item) {
            if ($line_item["type"] == "registration") {
                $registration_id = $line_item["registration_id"];
                $line_item_id = $line_item["line_item_id"];
                if (empty($registration_id)) {
                    $error_message = "Registration ID missing for line item $line_item_id.";
                    throw new Usctdp_Woocommerce_Exception($error_message);
                }
                $registration_query = new Usctdp_Mgmt_Registration_Query(['id' => $registration_id, 'number' => 1]);
                if (empty($registration_query->items)) {
                    $error_message = "Registration with ID $registration_id not found.";
                    throw new Usctdp_Woocommerce_Exception($error_message);
                }
            }
        }

        $family = Usctdp_Mgmt_Model::get_family($family_id);
        if (!$family) {
            $error_message = "Family with ID $family_id not found.";
            throw new Usctdp_Woocommerce_Exception($error_message);
        }
        $user_id = $family->user_id;

        $order = null;
        $order = wc_create_order(['customer_id' => $user_id]);
        if (is_wp_error($order)) {
            $error_message = 'Failed to create woocommerce order.';
            throw new Usctdp_Woocommerce_Exception($error_message);
        }

        try {
            // Two different numbers, deliberately kept separate:
            //   $total          - the full list price (sum of base_price)
            //                     Fee items (discount, house credit,
            //                     remaining balance) get subtracted from
            //                     for display, so the receipt reads Item ->
            //                     Discount -> Total the way it's meant to.
            //   $credit_total   - what's actually being collected via THIS
            //                     transaction (the admin-editable "Pay"
            //                     column - see RegistrationPaymentTable.
            //                     addExistingRegistration()/
            //                     addNewRegistration() in usctdp-mgmt-
            //                     admin.js), which drives the order's real
            //                     total below and can be less than the full
            //                     price (a partial payment).
            $total = 0;
            $discount_total = 0;
            $credit_total = 0;
            $house_credit_total = 0;
            foreach ($line_items as $line_item) {
                $student_id = $line_item["student_id"];
                $student = Usctdp_Mgmt_Model::get_student($student_id);
                if (!$student) {
                    $error_message = "Student with ID $student_id not found.";
                    throw new Usctdp_Woocommerce_Exception($error_message);
                }

                $custom_price = floatval($line_item["base_price"]);
                $total += $custom_price;
                $purchase_id = isset($line_item["purchase_id"]) ? intval($line_item["purchase_id"]) : 0;

                $credit = floatval($line_item["credit"] ?? 0);
                $line_house_credit = floatval($line_item["house_credit"] ?? 0);
                $credit_total += $credit;
                $house_credit_total += $line_house_credit;

                // The amount actually charged to the card for this specific
                // item, i.e. what's being collected now minus whatever of
                // that is covered by house credit instead. Recorded as meta
                // rather than left implicit in the item's own (list) price,
                // since record_deferred_payment() (class-usctdp-mgmt-
                // woocommerce-hooks.php) needs the real charged amount and
                // that can differ per item from what this receipt displays.
                $charged_amount = round($credit - $line_house_credit, 2);

                if ($line_item["type"] == "merchandise") {
                    $product_id = $line_item["product_id"];
                    $woo_product = $this->get_woo_product_by_id($product_id);
                    $item_id = $order->add_product($woo_product, 1);
                    $item = $order->get_item($item_id);
                    $item->add_meta_data('Student', $student->title);
                    $item->add_meta_data('_purchase_id', $purchase_id);
                    $item->add_meta_data('_charged_amount', $charged_amount);
                    $item->add_meta_data('_house_credit_amount', $line_house_credit);
                    $item->set_props(array('subtotal' => $custom_price, 'total' => $custom_price));
                    $item->save();
                } else if ($line_item["type"] == "registration") {
                    $session_id = $line_item["session_id"];
                    $session = Usctdp_Mgmt_Model::get_session($session_id);
                    if (!$session) {
                        throw new Usctdp_Woocommerce_Exception("Session with ID $session_id not found.");
                    }
                    $activity_id = $line_item["activity_id"];
                    $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
                    if (!$activity) {
                        throw new Usctdp_Woocommerce_Exception("Activity with ID $activity_id not found.");
                    }
                    $product_id = $activity->product_id;
                    $variation_ids = $this->find_variations_for_session($product_id, $session_id);
                    if (empty($variation_ids)) {
                        $error_message = "No variations found for product $product_id and session $session_id";
                        throw new Usctdp_Woocommerce_Exception($error_message);
                    }
                    $variation_id = $variation_ids[0];
                    $product = wc_get_product($variation_id);
                    $item_id = $order->add_product($product, 1);

                    $item = $order->get_item($item_id);
                    $item->add_meta_data('Student', $student->title);
                    $item->add_meta_data('Session', $session->title);
                    $item->add_meta_data('Activity', $activity->title);
                    $item->add_meta_data('_purchase_id', $purchase_id);
                    $item->add_meta_data('_charged_amount', $charged_amount);
                    $item->add_meta_data('_house_credit_amount', $line_house_credit);
                    $item->set_props(array('subtotal' => $custom_price, 'total' => $custom_price));
                    $item->save();
                }

                $discounts = isset($line_item["discounts"]) ? $line_item["discounts"] : [];
                foreach ($discounts as $discount) {
                    $discount_amount = floatval($discount["amount"]);
                    $discount_total += $discount_amount;
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name($discount["reason"]);
                    $fee->set_total(-$discount_amount);
                    $order->add_item($fee);
                }
            }

            if ($house_credit_total > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name("House Credit Applied");
                $fee->set_total(-$house_credit_total);
                $order->add_item($fee);
            }

            // Whatever of the full (list price, after discount) total isn't
            // being collected via this transaction at all - e.g. a partial
            // payment - gets its own line too, so the receipt's numbers
            // still add up (Item - Discount - House Credit - Remaining
            // Balance = Total) instead of silently charging less than the
            // displayed breakdown implies.
            $list_price_total = round($total - $discount_total, 2);
            $remaining_balance = round($list_price_total - $credit_total, 2);
            if ($remaining_balance > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Remaining Balance');
                $fee->set_total(-$remaining_balance);
                $order->add_item($fee);
            }

            // What's actually charged: collected now, minus whatever of
            // that was covered by house credit rather than the card.
            $order->set_total(round($credit_total - $house_credit_total, 2));

            if ($payment_method === 'card') {
                $order->update_status('pending', 'Awaiting payment via ' . $payment_method);
            } else if ($payment_method === 'cash') {
                $order->set_payment_method('cod');
                $order->set_payment_method_title('Cash');
                $order->add_order_note("Admin recorded payment via Cash");
                $order->payment_complete();
                $order->set_status('completed');
            } else if ($payment_method === 'check') {
                $order->set_payment_method('cheque');
                $order->set_payment_method_title('Check');
                $order->update_meta_data('_check_number', $check_number);
                $order->add_order_note("Admin recorded payment via Check #" . $check_number);
                $order->payment_complete();
                $order->set_status('completed');
            } else {
                $order->set_payment_method($payment_method);
                $order->set_payment_method_title($payment_method);
                $order->add_order_note("Admin recorded payment via " . $payment_method);
                $order->payment_complete();
                $order->set_status('completed');
            }
            $order->save();
            return [
                "order" => $order,
                "user_id" => $user_id
            ];
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('Error creating order', $e);
            if ($order instanceof WC_Order) {
                try {
                    $order->delete(true);
                } catch (Throwable $e) {
                    Usctdp_Mgmt::logger()->log_exception('Error cleaning up order', $e);
                }
            }
        }
    }
}
