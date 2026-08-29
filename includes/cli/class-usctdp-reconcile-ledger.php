<?php

/**
 * Finds and backfills two related gaps left by create_purchase_and_ledger_entries()
 * (class-usctdp-mgmt-woocommerce-hooks.php) not completing for an order:
 *
 *   1. Orphaned purchases: a purchase was created but its four ledger rows
 *      never were - the unchecked add_item() calls that method used to have
 *      let this happen silently (e.g. the payment_method varchar(20)
 *      truncation bug).
 *   2. Orphaned registrations: a registration was activated
 *      (confirm_registration(), a separate, non-transactional hook callback
 *      on the same action) but purchase_id is still 0 - the purchase step
 *      never ran at all. Plausible cause: the web container getting
 *      recreated (or the request otherwise dying) between
 *      confirm_registration() (priority 10) and create_purchase_and_ledger_entries()
 *      (priority 20) on the same woocommerce_order_status_processing /
 *      woocommerce_payment_complete hook.
 *
 * Report-only by default; --fix backfills both by re-deriving everything
 * from the original WooCommerce order.
 *
 * Deliberately does NOT rely on registration.order_id for locating the
 * order - purchase<->order (and registration<->order, for case 2) is
 * resolved by matching tracking_id against the '_tracking_id' order item
 * meta checkout_create_order_line_item() writes, since that's resolvable
 * even for historical rows from before order_id existed as a real column.
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
     * 'active' registrations with no purchase attached at all - purchase_id
     * stays 0 until create_purchase_and_ledger_entries() sets it, so this is
     * a registration that got activated (confirm_registration(), a separate
     * hook callback) without that method ever having run to completion for
     * it. Scoped to 'active' only: every freshly-created registration
     * starts at purchase_id=0 while still 'pending' (normal, mid-checkout,
     * pre-payment) - only 'active' + purchase_id=0 together are the anomaly.
     */
    private function find_registrations_without_purchase()
    {
        global $wpdb;

        return $wpdb->get_results(
            "   SELECT
                    reg.id AS registration_id,
                    reg.activity_id AS activity_id,
                    reg.tracking_id AS tracking_id,
                    reg.created_at AS registration_created_at,
                    stud.id AS student_id,
                    stud.first AS student_first,
                    stud.last AS student_last,
                    stud.family_id AS family_id,
                    act.title AS activity_title,
                    act.product_id AS product_id
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON stud.id = reg.student_id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                WHERE reg.status = 'active'
                AND reg.purchase_id = 0
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
     * Resolves a tracking_id all the way to the WC_Order it belongs to.
     * Shared by both backfill paths - orphaned purchases already have a
     * tracking_id on the purchase row, orphaned registrations have one on
     * the registration row directly.
     */
    private function find_order_for_tracking_id($tracking_id, $context_label)
    {
        if (empty($tracking_id)) {
            throw new Exception("$context_label has no tracking_id to locate its order by.");
        }

        $order_id = $this->find_order_id_by_tracking_id($tracking_id);
        if (!$order_id) {
            throw new Exception("No order found with a line item tracking_id of '$tracking_id'.");
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception("Order $order_id (from tracking_id '$tracking_id') no longer exists.");
        }

        return $order;
    }

    /**
     * Finds the specific line item on $order carrying $tracking_id that
     * covers $activity_id, and every activity_id that line item covers
     * (one for a tournament, one or two for a clinic's day picks).
     */
    private function find_order_line_item($order, $tracking_id, $activity_id, $order_id)
    {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_tracking_id') !== $tracking_id) {
                continue;
            }
            $activity_ids = array_map('intval', (array) $item->get_meta('_activities'));
            if (in_array((int) $activity_id, $activity_ids, true)) {
                return [$item, $activity_ids];
            }
        }
        throw new Exception("Order $order_id has no line item covering activity $activity_id.");
    }

    /**
     * Re-derives the charge for one activity within an order line item, the
     * same way create_purchase_and_ledger_entries() does: the item's own
     * total (no discount) when it's the only activity on the item, or a
     * full One-day base price plus a second-day discount when it's one of a
     * clinic's two day-of-week picks. Returns ['base' => ..., 'discount' =>
     * ...] - 'discount' is 0.0 for a single-activity item or the first day
     * of a two-activity one.
     */
    private function get_activity_charge($item, $activity_id, $activity_ids, $order_id)
    {
        $item_total = floatval($item->get_total());

        if (count($activity_ids) === 1) {
            return ['base' => $item_total, 'discount' => 0.0];
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
        $day1_price = round(floatval($pricing->pricing['One']), 2);
        // Taken straight from the pricing table, not derived from
        // $item_total - see Usctdp_Mgmt_Model::get_second_day_discount().
        $day2_discount = Usctdp_Mgmt_Model::get_second_day_discount($pricing, $day1_price);
        if (empty($day2_discount) || $day2_discount < 0) {
            $day2_discount = 0.0;
        }
        $activity_charges = [
            $activity_ids[0] => ['base' => $day1_price, 'discount' => 0.0],
            $activity_ids[1] => ['base' => $day1_price, 'discount' => $day2_discount],
        ];

        if (!array_key_exists($activity_id, $activity_charges)) {
            throw new Exception("Activity $activity_id not among order $order_id's line item activities.");
        }
        return $activity_charges[$activity_id];
    }

    /**
     * Builds the ledger entry arrays that create_purchase_and_ledger_entries()
     * would have for a given purchase and charge - a charge pair at the
     * base price, an optional discount adjustment pair (see
     * get_activity_charge()), and a payment pair at the net price. Doesn't
     * insert anything - see insert_ledger_entries().
     */
    private function build_ledger_entries($purchase_id, $family_id, $order_id, $payment_method, $reference_id, $created_at, $created_by, $charge)
    {
        $base_price = $charge['base'];
        $discount_amount = $charge['discount'];
        $net_price = round($base_price - $discount_amount, 2);

        $ledger_base = [
            'purchase_id' => $purchase_id,
            'family_id' => $family_id,
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
                'description' => 'Order placed in online store.',
                'debit' => $base_price,
                'credit' => 0,
            ]),
            array_merge($ledger_base, [
                'account' => 'revenue',
                'entry_type' => 'charge',
                'description' => 'Order placed in online store.',
                'debit' => 0,
                'credit' => $base_price,
            ]),
        ];

        if ($discount_amount > 0) {
            $entries[] = array_merge($ledger_base, [
                'account' => 'registration_fees',
                'entry_type' => 'adjustment',
                'description' => 'Second Day Discount',
                'debit' => 0,
                'credit' => $discount_amount,
            ]);
            $entries[] = array_merge($ledger_base, [
                'account' => 'revenue',
                'entry_type' => 'adjustment',
                'description' => 'Second Day Discount',
                'debit' => $discount_amount,
                'credit' => 0,
            ]);
        }

        $entries[] = array_merge($ledger_base, [
            'account' => 'payment_' . $payment_method,
            'entry_type' => 'payment',
            'description' => 'Order paid in online store.',
            'debit' => $net_price,
            'credit' => 0,
        ]);
        $entries[] = array_merge($ledger_base, [
            'account' => 'registration_fees',
            'entry_type' => 'payment',
            'description' => 'Order paid in online store.',
            'debit' => 0,
            'credit' => $net_price,
        ]);

        return $entries;
    }

    /**
     * {code, value, amount, reason} discounts JSON for usctdp_purchase.discounts
     * - null (no key written) when there's no discount to record. Same shape
     * parse_registration_data()/parse_merchandise_data() use
     * (class-usctdp-mgmt-admin-ajax.php).
     */
    private function get_discounts_json($charge)
    {
        if ($charge['discount'] <= 0) {
            return null;
        }
        return wp_json_encode([[
            'code' => 'second_day',
            'value' => $charge['discount'],
            'amount' => $charge['discount'],
            'reason' => 'Second Day Discount',
        ]]);
    }

    /**
     * Inserts every entry from build_ledger_entries(), checking each
     * add_item() call - it silently returns false (no exception, no $wpdb
     * error surfaced) rather than erroring, which is exactly the bug this
     * whole tool exists to clean up after. Dumps the failing entry and
     * $wpdb->last_error on failure, since add_item() only calls
     * $wpdb->insert() at all if validate_item() didn't already bail (e.g.
     * on a null field) - so last_error can be empty/stale even on a real
     * failure.
     */
    private function insert_ledger_entries($entries)
    {
        global $wpdb;
        $ledger_query = new Usctdp_Mgmt_Ledger_Query();
        foreach ($entries as $entry) {
            $entry_id = $ledger_query->add_item($entry);
            if (!$entry_id) {
                throw new Exception(
                    "Failed to insert '{$entry['account']}' {$entry['entry_type']} ledger entry. "
                    . 'Entry: ' . wp_json_encode($entry) . '. '
                    . 'wpdb->last_error: ' . ($wpdb->last_error ?: '(empty)')
                );
            }
        }
    }

    /**
     * Backfills the ledger rows for one orphaned purchase, mirroring
     * create_purchase_and_ledger_entries() exactly but reusing the purchase
     * that already exists instead of creating a new one. Wrapped in its own
     * transaction so one bad row can't take others down with it.
     */
    private function backfill_orphaned_purchase($row)
    {
        global $wpdb;

        $order = $this->find_order_for_tracking_id($row->tracking_id, "Purchase {$row->purchase_id}");
        $order_id = $order->get_id();
        [$item, $activity_ids] = $this->find_order_line_item($order, $row->tracking_id, $row->activity_id, $order_id);

        $charge = $this->get_activity_charge($item, (int) $row->activity_id, $activity_ids, $order_id);
        $payment_method = $order->get_payment_method();
        $reference_id = $order->get_transaction_id() ?: (string) $order_id;
        $created_at = current_time('mysql');
        $created_by = get_current_user_id();

        $entries = $this->build_ledger_entries(
            $row->purchase_id,
            $row->family_id,
            $order_id,
            $payment_method,
            $reference_id,
            $created_at,
            $created_by,
            $charge
        );
        $discounts_json = $this->get_discounts_json($charge);

        $wpdb->query('START TRANSACTION');
        try {
            $this->insert_ledger_entries($entries);
            if ($discounts_json !== null) {
                $purchase_query = new Usctdp_Mgmt_Purchase_Query();
                $purchase_query->update_item($row->purchase_id, ['discounts' => $discounts_json]);
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        return $order_id;
    }

    /**
     * Backfills an 'active' registration that never got a purchase at all -
     * creates the purchase (mirroring create_purchase_and_ledger_entries()'s
     * own purchase-creation step), links it to the registration, and
     * inserts the same ledger rows backfill_orphaned_purchase() does. One
     * transaction covering all of it, so a failure partway through
     * (e.g. the ledger insert) rolls back the purchase creation too, rather
     * than leaving a purchase with no ledger - the exact problem this tool
     * already exists to detect.
     */
    private function backfill_missing_purchase($row)
    {
        global $wpdb;

        $order = $this->find_order_for_tracking_id($row->tracking_id, "Registration {$row->registration_id}");
        $order_id = $order->get_id();
        [$item, $activity_ids] = $this->find_order_line_item($order, $row->tracking_id, $row->activity_id, $order_id);

        $charge = $this->get_activity_charge($item, (int) $row->activity_id, $activity_ids, $order_id);
        $payment_method = $order->get_payment_method();
        $reference_id = $order->get_transaction_id() ?: (string) $order_id;
        $created_at = current_time('mysql');
        $created_by = get_current_user_id();
        $discounts_json = $this->get_discounts_json($charge);

        $wpdb->query('START TRANSACTION');
        try {
            $purchase_fields = [
                'product_id' => $row->product_id,
                'family_id' => $row->family_id,
                'student_id' => $row->student_id,
                'tracking_id' => $row->tracking_id,
                'type' => 'registration',
                'created_at' => $created_at,
                'created_by' => $created_by,
            ];
            if ($discounts_json !== null) {
                $purchase_fields['discounts'] = $discounts_json;
            }
            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            $purchase_id = $purchase_query->add_item($purchase_fields);
            if (!$purchase_id) {
                throw new Exception("Failed to create purchase for registration {$row->registration_id}.");
            }

            $reg_query = new Usctdp_Mgmt_Registration_Query();
            $reg_query->update_item($row->registration_id, ['purchase_id' => $purchase_id]);

            $entries = $this->build_ledger_entries(
                $purchase_id,
                $row->family_id,
                $order_id,
                $payment_method,
                $reference_id,
                $created_at,
                $created_by,
                $charge
            );
            $this->insert_ledger_entries($entries);

            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        return $order_id;
    }

    public function reconcile($fix = false)
    {
        $orphaned_purchases = $this->find_orphaned_purchases();
        $missing_purchases = $this->find_registrations_without_purchase();

        if (empty($orphaned_purchases) && empty($missing_purchases)) {
            WP_CLI::success('No orphaned purchases or unpaid active registrations found.');
            return;
        }

        $fixed = 0;
        $failed = 0;

        if (!empty($orphaned_purchases)) {
            WP_CLI::log(sprintf('Found %d purchase(s) with no ledger entries:', count($orphaned_purchases)));
            WP_CLI::log('');

            foreach ($orphaned_purchases as $row) {
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
                    $order_id = $this->backfill_orphaned_purchase($row);
                    WP_CLI::log("  -> backfilled from order #$order_id");
                    $fixed++;
                } catch (Throwable $e) {
                    WP_CLI::log('  -> SKIPPED: ' . $e->getMessage());
                    Usctdp_Mgmt::logger()->log_exception('USCTDP: reconcile_ledger backfill', $e);
                    $failed++;
                }
            }
            WP_CLI::log('');
        }

        if (!empty($missing_purchases)) {
            WP_CLI::log(sprintf('Found %d active registration(s) with no purchase at all:', count($missing_purchases)));
            WP_CLI::log('');

            foreach ($missing_purchases as $row) {
                $student_name = trim($row->student_first . ' ' . $row->student_last);
                WP_CLI::log(sprintf(
                    'registration #%d | %s | %s | tracking_id=%s | registered %s',
                    $row->registration_id,
                    $student_name,
                    $row->activity_title,
                    $row->tracking_id ?: '(none)',
                    $row->registration_created_at
                ));

                if (!$fix) {
                    continue;
                }

                try {
                    $order_id = $this->backfill_missing_purchase($row);
                    WP_CLI::log("  -> created purchase and backfilled from order #$order_id");
                    $fixed++;
                } catch (Throwable $e) {
                    WP_CLI::log('  -> SKIPPED: ' . $e->getMessage());
                    Usctdp_Mgmt::logger()->log_exception('USCTDP: reconcile_ledger backfill (missing purchase)', $e);
                    $failed++;
                }
            }
            WP_CLI::log('');
        }

        $total = count($orphaned_purchases) + count($missing_purchases);
        if ($fix) {
            WP_CLI::log(sprintf('Backfilled %d, skipped %d, out of %d issue(s) found.', $fixed, $failed, $total));
        } else {
            WP_CLI::log(sprintf('%d issue(s) found. Re-run with --fix to attempt backfilling them.', $total));
        }
    }
}
