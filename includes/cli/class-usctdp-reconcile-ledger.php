<?php

/**
 * Finds registrations whose purchase has no matching usctdp_ledger rows -
 * the exact inconsistency the unchecked add_item() calls in
 * create_purchase_and_ledger_entries()/record_deferred_payment() could leave
 * behind (see class-usctdp-mgmt-woocommerce-hooks.php). Report-only by
 * default; --fix attempts to backfill the missing ledger entries for the
 * self-checkout path (create_purchase_and_ledger_entries's case) by
 * re-deriving them from the original WooCommerce order.
 *
 * Deliberately does NOT rely on registration.order_id - that column doesn't
 * exist (no such schema column, no meta table either), so any filter or
 * update keyed on it is a silent no-op. Purchase<->ledger is joined on
 * purchase_id (a real column on both tables); purchase<->order is joined by
 * matching usctdp_purchase.tracking_id against the '_tracking_id' order item
 * meta checkout_create_order_line_item() writes.
 */
class Usctdp_Reconcile_Ledger
{
    /**
     * Registrations with a purchase_id set but zero rows in usctdp_ledger
     * for that purchase, joined with enough context to identify each one.
     */
    private function find_orphaned_purchases()
    {
        global $wpdb;

        return $wpdb->get_results(
            "   SELECT
                    reg.id AS registration_id,
                    reg.status AS registration_status,
                    reg.activity_id AS activity_id,
                    pur.id AS purchase_id,
                    pur.type AS purchase_type,
                    pur.tracking_id AS tracking_id,
                    pur.created_at AS purchase_created_at,
                    pur.family_id AS family_id,
                    stud.id AS student_id,
                    stud.first AS student_first,
                    stud.last AS student_last,
                    act.title AS activity_title,
                    act.product_id AS product_id
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = reg.purchase_id
                JOIN {$wpdb->prefix}usctdp_student AS stud ON stud.id = reg.student_id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                WHERE reg.purchase_id > 0
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}usctdp_ledger AS led
                    WHERE led.purchase_id = reg.purchase_id
                )
                ORDER BY reg.id ASC"
        );
    }

    /**
     * Finds the order whose line item carries this tracking_id in its
     * '_tracking_id' meta - the same key checkout_create_order_line_item()
     * writes at checkout. Assumes traditional (non-HPOS) order item storage,
     * i.e. wp_woocommerce_order_itemmeta; this plugin's other raw queries
     * (get_clinics, get_activities, etc.) make the same assumption.
     */
    private function find_order_id_by_tracking_id($tracking_id)
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "   SELECT oi.order_id
                FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim
                JOIN {$wpdb->prefix}woocommerce_order_items AS oi
                    ON oi.order_item_id = oim.order_item_id
                WHERE oim.meta_key = '_tracking_id' AND oim.meta_value = %s
                LIMIT 1",
            $tracking_id
        ));
    }

    /**
     * Re-derives the price for one activity within an order line item, the
     * same way create_purchase_and_ledger_entries() does: the item's own
     * total when it's the only activity on the item, or a One/Two-day split
     * from the product's pricing table when it's one of a clinic's two
     * day-of-week picks.
     */
    private function get_activity_price($item, $activity_id, $activity_ids, $order_id)
    {
        $item_total = floatval($item->get_total());

        if (count($activity_ids) === 1) {
            return $item_total;
        }

        $activity_query = new Usctdp_Mgmt_Activity_Query(['id' => $activity_ids[0], 'number' => 1]);
        if (empty($activity_query->items)) {
            throw new Exception("Activity {$activity_ids[0]} not found for order $order_id.");
        }
        $first_activity = $activity_query->items[0];

        $pricing = Usctdp_Mgmt_Model::get_activity_pricing($first_activity);
        if (!$pricing) {
            throw new Exception("Pricing not found for activity {$activity_ids[0]} on order $order_id.");
        }
        $day1_price = floatval($pricing->pricing['One']);
        $activity_prices = [
            $activity_ids[0] => $day1_price,
            $activity_ids[1] => $item_total - $day1_price,
        ];

        if (!array_key_exists($activity_id, $activity_prices)) {
            throw new Exception("Activity $activity_id not among order $order_id's line item activities.");
        }
        return $activity_prices[$activity_id];
    }

    /**
     * Backfills the four ledger rows (charge x2, payment x2) for one
     * orphaned purchase, mirroring create_purchase_and_ledger_entries()
     * exactly but reusing the purchase that already exists instead of
     * creating a new one. Wrapped in its own transaction so one bad row
     * can't take others down with it; every add_item() is checked, per the
     * fix already applied to the source of this bug.
     */
    private function backfill_one($row)
    {
        global $wpdb;

        if (empty($row->tracking_id)) {
            throw new Exception("Purchase {$row->purchase_id} has no tracking_id to locate its order by.");
        }

        $order_id = $this->find_order_id_by_tracking_id($row->tracking_id);
        if (!$order_id) {
            throw new Exception("No order found with a line item tracking_id of '{$row->tracking_id}'.");
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception("Order $order_id (from tracking_id '{$row->tracking_id}') no longer exists.");
        }

        $matching_item = null;
        $activity_ids = [];
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_tracking_id') !== $row->tracking_id) {
                continue;
            }
            $ids = array_map('intval', (array) $item->get_meta('_activities'));
            if (in_array((int) $row->activity_id, $ids, true)) {
                $matching_item = $item;
                $activity_ids = $ids;
                break;
            }
        }
        if (!$matching_item) {
            throw new Exception("Order $order_id has no line item covering activity {$row->activity_id}.");
        }

        $price = $this->get_activity_price($matching_item, (int) $row->activity_id, $activity_ids, $order_id);
        $payment_method = $order->get_payment_method();
        $reference_id = $order->get_transaction_id() ?: (string) $order_id;
        $created_at = current_time('mysql');
        $created_by = get_current_user_id();

        $ledger_base = [
            'purchase_id' => $row->purchase_id,
            'family_id' => $row->family_id,
            'order_id' => $order_id,
            'event_id' => 'wc_order' . $order_id,
            'event' => 'WooCommerce Order ' . $order_id,
            'payment_method' => $payment_method,
            'reference_id' => $reference_id,
            'created_at' => $created_at,
            'created_by' => $created_by,
        ];

        $entries = [
            array_merge($ledger_base, [
                'account' => 'registration_fees',
                'entry_type' => 'charge',
                'description' => 'Backfilled: order placed in online store.',
                'debit' => $price,
                'credit' => 0,
            ]),
            array_merge($ledger_base, [
                'account' => 'revenue',
                'entry_type' => 'charge',
                'description' => 'Backfilled: order placed in online store.',
                'debit' => 0,
                'credit' => $price,
            ]),
            array_merge($ledger_base, [
                'account' => 'payment_' . $payment_method,
                'entry_type' => 'payment',
                'description' => 'Backfilled: order paid in online store.',
                'debit' => $price,
                'credit' => 0,
            ]),
            array_merge($ledger_base, [
                'account' => 'registration_fees',
                'entry_type' => 'payment',
                'description' => 'Backfilled: order paid in online store.',
                'debit' => 0,
                'credit' => $price,
            ]),
        ];

        $wpdb->query('START TRANSACTION');
        try {
            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            foreach ($entries as $entry) {
                $entry_id = $ledger_query->add_item($entry);
                if (!$entry_id) {
                    // add_item() only calls $wpdb->insert() at all if
                    // validate_item() didn't already bail (e.g. on a null
                    // field) - so $wpdb->last_error can be empty/stale even
                    // on a real failure here. Dumping the entry itself
                    // covers that case: an unexpected null will show up
                    // directly instead of us having to guess at it again.
                    throw new Exception(
                        "Failed to insert '{$entry['account']}' {$entry['entry_type']} ledger entry. "
                        . 'Entry: ' . wp_json_encode($entry) . '. '
                        . 'wpdb->last_error: ' . ($wpdb->last_error ?: '(empty)')
                    );
                }
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        return $order_id;
    }

    public function reconcile($fix = false)
    {
        $orphans = $this->find_orphaned_purchases();

        if (empty($orphans)) {
            WP_CLI::success('No orphaned purchases found - every purchase has matching ledger entries.');
            return;
        }

        WP_CLI::log(sprintf('Found %d purchase(s) with no ledger entries:', count($orphans)));
        WP_CLI::log('');

        $fixed = 0;
        $failed = 0;

        foreach ($orphans as $row) {
            $student_name = trim($row->student_first . ' ' . $row->student_last);
            WP_CLI::log(sprintf(
                'registration #%d | purchase #%d (%s) | %s | %s | tracking_id=%s | created %s',
                $row->registration_id,
                $row->purchase_id,
                $row->purchase_type,
                $student_name,
                $row->activity_title,
                $row->tracking_id ?: '(none)',
                $row->purchase_created_at
            ));

            if (!$fix) {
                continue;
            }

            try {
                $order_id = $this->backfill_one($row);
                WP_CLI::log("  -> backfilled from order #$order_id");
                $fixed++;
            } catch (Throwable $e) {
                WP_CLI::log('  -> SKIPPED: ' . $e->getMessage());
                Usctdp_Mgmt::logger()->log_exception('USCTDP: reconcile_ledger backfill', $e);
                $failed++;
            }
        }

        WP_CLI::log('');
        if ($fix) {
            WP_CLI::log(sprintf('Backfilled %d, skipped %d, out of %d orphaned purchase(s).', $fixed, $failed, count($orphans)));
        } else {
            WP_CLI::log(sprintf('%d orphaned purchase(s) found. Re-run with --fix to attempt backfilling them.', count($orphans)));
        }
    }
}
