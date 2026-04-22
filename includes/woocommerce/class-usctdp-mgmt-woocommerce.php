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
            $total = 0;
            foreach ($line_items as $line_item) {
                $student_id = $line_item["student_id"];
                $student = Usctdp_Mgmt_Model::get_student($student_id);
                if (!$student) {
                    $error_message = "Student with ID $student_id not found.";
                    throw new Usctdp_Woocommerce_Exception($error_message);
                }

                $custom_price = floatval($line_item["base_price"]);
                $total += $custom_price;

                if ($line_item["type"] == "merchandise") {
                    $product_id = $line_item["product_id"];
                    $woo_product = $this->get_woo_product_by_id($product_id);
                    $item_id = $order->add_product($woo_product, 1);
                    $item = $order->get_item($item_id);
                    $item->add_meta_data('Student', $student->title);
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
                    $item->set_props(array('subtotal' => $custom_price, 'total' => $custom_price));
                    $item->save();
                }
            }

            // Apply discounts to the order as negative fees
            $discounts = $line_item["discounts"];
            $discount_total = 0;
            if ($discounts) {
                foreach ($discounts as $discount) {
                    $discount_amount = floatval($discount["amount"]);
                    $discount_total += $discount_amount;
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name($discount["reason"]);
                    $fee->set_total(-$discount_amount);
                    $order->add_item($fee);
                }
            }

            $order->set_total($total - $discount_total);
            if ($payment_method === 'cash') {
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
                $order->update_status('pending', 'Awaiting payment via ' . $payment_method);
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
