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
        // 'tournament' is the only type with no "Days Per Week" attribute -
        // everything else (clinic, and 'cardio' for Cardio Tennis, which is
        // priced/scheduled exactly like a clinic despite its own product
        // type) needs the day filter or this matches every day variant for
        // the session, not just the "One day" one being registered. Same
        // === 'tournament' branching convention get_session_price_lines()
        // uses server-side and sync_product_variations() uses above.
        if ($product->type === 'tournament') {
            return $this->find_variations($woo_product, [
                'session' => $session_name,
            ]);
        } else {
            return $this->find_variations($woo_product, [
                'session' => $session_name,
                'days-per-week' => "One",
            ]);
        }
    }

    /**
     * Entry point for "a session's status just changed" - finds every
     * product this session has ever been priced for (Usctdp_Mgmt_Pricing_Query
     * rows, regardless of status) and resyncs each one's WooCommerce
     * variations to match whichever of ITS priced sessions are currently
     * 'on_sale'. Session-scoped because a status change on one session can
     * only ever affect the specific products that session has pricing for -
     * everything else is untouched.
     */
    public function sync_onsale_sessions_for_session($session_id)
    {
        $pricing_query = new Usctdp_Mgmt_Pricing_Query([
            'session_id' => $session_id,
            'number' => 0,
        ]);
        $product_ids = array_unique(array_map(function ($row) {
            return $row->product_id;
        }, $pricing_query->items));

        foreach ($product_ids as $product_id) {
            $this->sync_onsale_sessions_for_product($product_id);
        }
    }

    /**
     * Syncs a single product's on-sale sessions - its WooCommerce 'Session'
     * attribute + variations, _session_post_ids meta, and the
     * usctdp_product_session bookkeeping table - to match whichever of its
     * priced sessions (Usctdp_Mgmt_Pricing_Query rows for this product)
     * currently have status = 'on_sale'. "active" in the CLI importer's
     * import_clinic_prices()/import_tournament_pricing() (see
     * class-usctdp-import-session-data.php, which this parallels rather
     * than shares) comes from the import JSON's per-session flag; here it
     * comes from session status.
     *
     * Only clinic/cardio/tournament products carry per-session pricing at
     * all, so this is a no-op for anything else (merchandise, rackets,
     * t-shirts), and for a clinic/cardio/tournament product that isn't a
     * WooCommerce variable product yet (not fully set up).
     *
     * Unlike the importer (which deletes and recreates every variation on
     * the product from scratch on every run), this diffs against what's
     * already there - see sync_product_variations() below for how.
     */
    public function sync_onsale_sessions_for_product($product_id)
    {
        $product = Usctdp_Mgmt_Model::get_product($product_id);
        if (!$product) {
            throw new Usctdp_Woocommerce_Exception("Product with ID '$product_id' not found.");
        }
        if (!in_array($product->type, ['clinic', 'cardio', 'tournament'], true)) {
            return;
        }

        $woo_product = wc_get_product($product->woocommerce_id);
        if (!$woo_product || !$woo_product->is_type('variable')) {
            return;
        }

        $pricing_query = new Usctdp_Mgmt_Pricing_Query([
            'product_id' => $product_id,
            'number' => 0,
        ]);

        $session_ids = array_unique(array_map(function ($row) {
            return $row->session_id;
        }, $pricing_query->items));
        $sessions_by_id = [];
        if (!empty($session_ids)) {
            $session_query = new Usctdp_Mgmt_Session_Query([
                'id__in' => $session_ids,
                'number' => 0,
            ]);
            foreach ($session_query->items as $session) {
                $sessions_by_id[$session->id] = $session;
            }
        }

        // session title => ['id' => session_id, 'pricing' => decoded pricing]
        // for whichever priced sessions on this product are currently
        // on_sale. Keyed by title (not id) because that's what WooCommerce
        // attribute option values/variation attributes are keyed by.
        $public_sessions = [];
        $active_session_ids = [];
        foreach ($pricing_query->items as $row) {
            $session = $sessions_by_id[$row->session_id] ?? null;
            if (!$session || $session->status !== 'on_sale') {
                continue;
            }
            $public_sessions[$session->title] = [
                'id' => $session->id,
                'pricing' => $row->pricing,
            ];
            $active_session_ids[$session->id] = true;
        }

        $this->sync_product_sessions($product_id, $active_session_ids);
        $this->sync_product_variations($woo_product, $product, $public_sessions);
    }

    /**
     * Syncs a product's Session-attribute variations to match
     * $public_sessions (session title => ['id', 'pricing']) without
     * destroying anything that doesn't need to change: existing variations
     * are matched to a desired (session, day) combo by their actual
     * attribute values, and only created, price-updated, or disabled as
     * needed - never deleted. Disabling uses WooCommerce's own
     * enable/disable mechanism (post_status 'private' excludes a variation
     * from variation_is_visible()/get_available_variations() without
     * destroying it - see WC_Product_Variation::variation_is_visible()), so
     * a session going off sale doesn't churn variation IDs for its
     * still-public siblings on the same product, doesn't break an open
     * cart referencing one of those siblings, and a later re-publish
     * reuses the same variation (and stays resolvable from any historical
     * order that references it, unlike a deleted one).
     *
     * Mirrors the variation-building blocks in import_clinic_prices()/
     * import_tournament_pricing() (see class-usctdp-import-session-data.php),
     * which builds the same combos from a bulk JSON import rather than one
     * session's status change, and - being a bulk rebuild rather than a
     * single-session sync - has no reason to preserve existing variations
     * the way this does.
     */
    private function sync_product_variations($woo_product, $product, $public_sessions)
    {
        ksort($public_sessions);
        $session_attribute = new WC_Product_Attribute();
        $session_attribute->set_name('Session');
        $session_attribute->set_options(array_keys($public_sessions));
        $session_attribute->set_position(0);
        $session_attribute->set_visible(true);
        $session_attribute->set_variation(true);

        if ($product->type === 'tournament') {
            $woo_product->set_attributes([$session_attribute]);
        } else {
            // Clinics - and 'cardio' (Cardio Tennis products are priced and
            // scheduled exactly like clinics; the product type just exists
            // for the theme's own display/taxonomy purposes, e.g.
            // import_product_data's event_type term - see
            // get_session_price_lines() in class-usctdp-mgmt-woocommerce-hooks.php
            // for the same "=== 'tournament'" branching convention this
            // matches) - also carry a "Days Per Week" attribute (One/Two)
            // that this doesn't own - preserve whatever's already on the
            // product and only replace the 'session' slot, same as the
            // importer.
            $attributes = $woo_product->get_attributes();
            $attributes['session'] = $session_attribute;
            $woo_product->set_attributes($attributes);
        }

        $session_post_ids = [];
        foreach ($public_sessions as $session_name => $info) {
            $session_post_ids[$session_name] = $info['id'];
        }
        $woo_product->update_meta_data('_session_post_ids', $session_post_ids);
        $woo_product->save();

        // Desired (session, day) combos - day is null for tournaments -
        // keyed the same way variation_combo_key() keys existing variations
        // below, so the two can be matched up regardless of creation order.
        $desired = [];
        foreach ($public_sessions as $session_name => $info) {
            $pricing = $info['pricing'];
            if ($product->type === 'tournament') {
                $amt = $pricing['base'] ?? null;
                if (empty($amt)) {
                    continue;
                }
                $desired[$this->variation_combo_key($session_name, null)] = [
                    'session' => $session_name,
                    'day' => null,
                    'price' => $amt,
                ];
            } else {
                foreach (['One', 'Two'] as $day) {
                    $amt = $pricing[$day] ?? null;
                    if (empty($amt)) {
                        continue;
                    }
                    $desired[$this->variation_combo_key($session_name, $day)] = [
                        'session' => $session_name,
                        'day' => $day,
                        'price' => $amt,
                    ];
                }
            }
        }

        // Every existing variation on the product, keyed the same way,
        // regardless of its current enabled/disabled status - a disabled
        // variation whose combo is desired again gets re-enabled and
        // reused below rather than a duplicate created alongside it.
        $existing = [];
        foreach ($woo_product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            $attrs = $variation->get_attributes();
            $key = $this->variation_combo_key(
                $attrs['session'] ?? '',
                $product->type !== 'tournament' ? ($attrs['days-per-week'] ?? null) : null
            );
            $existing[$key] = $variation;
        }

        foreach ($desired as $key => $combo) {
            $variation = $existing[$key] ?? null;
            $is_new = !$variation;
            if ($is_new) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($woo_product->get_id());
                $variation_attrs = [sanitize_title('Session') => $combo['session']];
                if ($combo['day'] !== null) {
                    $variation_attrs[sanitize_title('Days Per Week')] = $combo['day'];
                }
                $variation->set_attributes($variation_attrs);
                $variation->set_manage_stock(false);
            } else {
                unset($existing[$key]);
            }

            $price_changed = abs((float) $variation->get_regular_price() - (float) $combo['price']) > 0.001;
            $needs_save = $is_new || $variation->get_status() !== 'publish' || $price_changed;
            if ($needs_save) {
                $variation->set_status('publish');
                $variation->set_regular_price($combo['price']);
                $variation->save();
            }
        }

        // Whatever's left in $existing is a variation whose combo is no
        // longer desired (its session went off sale, or a day's price got
        // zeroed out) - disable rather than delete, per the docblock above.
        foreach ($existing as $variation) {
            if ($variation->get_status() !== 'publish') {
                continue;
            }
            $variation->set_status('private');
            $variation->save();
        }
    }

    private function variation_combo_key($session_name, $day)
    {
        return $session_name . '||' . ($day ?? '');
    }

    /**
     * Syncs which sessions are currently on-sale for a product in the
     * usctdp_product_session bookkeeping table, keyed by the custom
     * usctdp_product id (not the WooCommerce id). Duplicated from (and kept
     * in sync with) Usctdp_Import_Session_Data::sync_product_sessions() -
     * see the note on sync_product_variations() above for why this isn't
     * shared code.
     *
     * @param int   $product_id                    usctdp_product.id
     * @param array $active_session_ids_for_product Set of session_id => true currently public for this product.
     */
    private function sync_product_sessions($product_id, $active_session_ids_for_product)
    {
        $existing_query = new Usctdp_Mgmt_Product_Session_Query([
            "product_id" => $product_id,
        ]);
        foreach ($existing_query->items as $item) {
            if (!isset($active_session_ids_for_product[$item->session_id])) {
                $existing_query->delete_item($item->id);
            }
        }

        foreach (array_keys($active_session_ids_for_product) as $session_id) {
            $query = new Usctdp_Mgmt_Product_Session_Query([
                "product_id" => $product_id,
                "session_id" => $session_id,
            ]);
            if (empty($query->items)) {
                $query->add_item([
                    "product_id" => $product_id,
                    "session_id" => $session_id,
                ]);
            }
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
