<?php

/**
 * Backfills usctdp_purchase.discounts and a matching ledger 'adjustment'
 * entry pair for online-order two-day-clinic purchases created before
 * create_purchase_and_ledger_entries() (class-usctdp-mgmt-woocommerce-hooks.php)
 * started itemizing the second day's discount.
 *
 * Before that fix, a clinic's second registered day was charged its
 * already-discounted net price directly as a single 'charge' entry, with
 * nothing recorded in usctdp_purchase.discounts. The family was charged the
 * right amount - the discount was just invisible in the ledger, and
 * unrecoverable once the registration was later touched: reviewPriceChange()
 * (usctdp-mgmt-admin-history.js) reads usctdp_purchase.discounts as its
 * "what discounts currently apply" baseline, so an empty value there made a
 * later "Modify" price-change review forget the discount ever existed and
 * potentially recompute a too-high new price.
 *
 * For each affected purchase, this restates its existing single 'charge'
 * entry pair up to the full One-day base price and adds a
 * 'Second Day Discount' adjustment pair for the difference - the same shape
 * create_purchase_and_ledger_entries() now writes for new orders. This
 * changes how the historical charge is *itemized*, not how much was ever
 * actually owed or paid: total_fees - total_adjustments (the net) is
 * identical before and after, and payment entries are left untouched.
 *
 * Deliberately skips any purchase that already has an 'adjustment' ledger
 * entry of any kind - either it's already in the new (post-fix) shape, or a
 * "Modify" price-change review has already run against it and booked its
 * own adjustment. Reconciling that overlap (a registration modified before
 * this fix existed) is a separate, judgment-call problem left alone here -
 * this only backfills the clean, never-since-touched cases.
 */
class Usctdp_Backfill_Second_Day_Discounts
{
    /**
     * Every 'registration' purchase created from a WooCommerce order
     * (reg.order_id > 0) that has no 'adjustment' ledger entry at all yet -
     * the broad candidate set findable purely from usctdp_* tables, before
     * any of them are checked against their original order to see whether a
     * second-day discount actually applies.
     */
    private function find_candidates()
    {
        global $wpdb;

        return $wpdb->get_results(
            "   SELECT
                    pur.id AS purchase_id,
                    pur.discounts AS discounts,
                    pur.tracking_id AS tracking_id,
                    pur.family_id AS family_id,
                    reg.id AS registration_id,
                    reg.activity_id AS activity_id,
                    reg.order_id AS order_id,
                    stud.first AS student_first,
                    stud.last AS student_last
                FROM {$wpdb->prefix}usctdp_purchase AS pur
                JOIN {$wpdb->prefix}usctdp_registration AS reg ON reg.purchase_id = pur.id
                JOIN {$wpdb->prefix}usctdp_student AS stud ON stud.id = pur.student_id
                WHERE pur.type = 'registration'
                AND reg.order_id > 0
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}usctdp_ledger AS led
                    WHERE led.purchase_id = pur.id AND led.entry_type = 'adjustment'
                )
                ORDER BY pur.id ASC"
        );
    }

    /**
     * Resolves a candidate row to everything needed to decide whether it's
     * actually a two-day clinic's second day and, if so, how big the
     * discount is - or returns null (with a reason logged by the caller) for
     * anything that turns out not to apply. Mirrors
     * Usctdp_Reconcile_Ledger::find_order_line_item()/get_activity_charge(),
     * but only cares about the *second* activity on a two-activity item.
     */
    private function resolve_discount($row)
    {
        $order = wc_get_order((int) $row->order_id);
        if (!$order) {
            return ['skip' => "order #{$row->order_id} no longer exists"];
        }

        $item = null;
        $activity_ids = [];
        foreach ($order->get_items() as $candidate) {
            if ($candidate->get_meta('_tracking_id') !== $row->tracking_id) {
                continue;
            }
            $item = $candidate;
            $activity_ids = array_map('intval', (array) $candidate->get_meta('_activities'));
            break;
        }
        if (!$item) {
            return ['skip' => "no line item on order #{$row->order_id} matches tracking_id '{$row->tracking_id}'"];
        }
        if (count($activity_ids) !== 2) {
            return ['skip' => 'not a two-day clinic line item'];
        }

        $index = array_search((int) $row->activity_id, $activity_ids, true);
        if ($index === false) {
            return ['skip' => "activity {$row->activity_id} not among this line item's activities"];
        }
        if ($index === 0) {
            return ['skip' => 'this purchase is the first day, not the discounted second day'];
        }

        $activity_query = new Usctdp_Mgmt_Activity_Query(['id' => $activity_ids[0], 'number' => 1]);
        $first_activity = $activity_query->items[0] ?? null;
        if (!$first_activity) {
            return ['skip' => "activity {$activity_ids[0]} not found"];
        }
        $pricing = Usctdp_Mgmt_Model::get_activity_pricing($first_activity);
        if (!$pricing || empty($pricing->pricing['One'])) {
            return ['skip' => "no One-day pricing found for activity {$activity_ids[0]}"];
        }

        $day1_price = round(floatval($pricing->pricing['One']), 2);
        // Taken straight from the pricing table (see
        // Usctdp_Mgmt_Model::get_second_day_discount()), not derived from
        // the order's line total - but unlike the other two callers of that
        // method, this one is restating a *historical* charge, and pricing
        // can change over time (a new session, a corrected price, etc.).
        // So the pricing-derived discount is only trusted once it's checked
        // against what the order actually charged - if today's pricing
        // table would produce a different net than what really happened,
        // restating from it would silently change how much the family was
        // ever charged, which is exactly what this tool must never do.
        $discount_amount = Usctdp_Mgmt_Model::get_second_day_discount($pricing, $day1_price);
        if (empty($discount_amount) || $discount_amount <= 0) {
            return ['skip' => "today's pricing table shows no second-day discount for this product/session"];
        }

        $item_total = round(floatval($item->get_total()), 2);
        $historical_net = round($item_total - $day1_price, 2);
        $expected_net = round($day1_price - $discount_amount, 2);
        if (abs($historical_net - $expected_net) > 0.01) {
            return ['skip' => sprintf(
                "today's pricing (One=%.2f, discount=%.2f -> net %.2f) doesn't reconcile with what the order actually charged for this day (net %.2f) - pricing has likely changed since this order; skipping rather than alter the amount ever charged",
                $day1_price,
                $discount_amount,
                $expected_net,
                $historical_net
            )];
        }

        return [
            'skip' => null,
            'day1_price' => $day1_price,
            'net_price' => $historical_net,
            'discount_amount' => $discount_amount,
        ];
    }

    /**
     * The purchase's existing 'charge' entries - expected to be exactly one
     * on registration_fees (debit) and one on revenue (credit), both
     * currently sitting at the already-discounted net price. Returns null if
     * that shape isn't found, so the caller can skip rather than guess.
     */
    private function find_charge_entries($purchase_id)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, account, debit, credit
             FROM {$wpdb->prefix}usctdp_ledger
             WHERE purchase_id = %d AND entry_type = 'charge'",
            $purchase_id
        ));

        $fees = null;
        $revenue = null;
        foreach ($rows as $row) {
            if ($row->account === 'registration_fees' && floatval($row->debit) > 0) {
                $fees = $row;
            } elseif ($row->account === 'revenue' && floatval($row->credit) > 0) {
                $revenue = $row;
            }
        }
        if (!$fees || !$revenue) {
            return null;
        }
        return ['fees' => $fees, 'revenue' => $revenue];
    }

    /**
     * Restates one purchase: bumps its existing charge pair up to the full
     * base price, inserts the discount adjustment pair, and writes the
     * discounts record onto usctdp_purchase - all in one transaction, so a
     * failure partway through can't leave a purchase half-restated.
     */
    private function backfill_purchase($row, $discount)
    {
        global $wpdb;

        $charge_entries = $this->find_charge_entries($row->purchase_id);
        if (!$charge_entries) {
            throw new Exception("Purchase {$row->purchase_id} doesn't have the expected single registration_fees/revenue charge pair to restate.");
        }

        $wpdb->query('START TRANSACTION');
        try {
            $updated = $wpdb->update(
                "{$wpdb->prefix}usctdp_ledger",
                ['debit' => $discount['day1_price']],
                ['id' => $charge_entries['fees']->id]
            );
            if ($updated === false) {
                throw new Exception("Failed to restate registration_fees charge entry {$charge_entries['fees']->id}: " . $wpdb->last_error);
            }
            $updated = $wpdb->update(
                "{$wpdb->prefix}usctdp_ledger",
                ['credit' => $discount['day1_price']],
                ['id' => $charge_entries['revenue']->id]
            );
            if ($updated === false) {
                throw new Exception("Failed to restate revenue charge entry {$charge_entries['revenue']->id}: " . $wpdb->last_error);
            }

            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            $ledger_base = [
                'purchase_id' => $row->purchase_id,
                'family_id' => $row->family_id,
                'order_id' => $row->order_id,
                'event_id' => 'wc_order' . $row->order_id,
                'event' => 'WooCommerce Order ' . $row->order_id,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
            ];

            $entry_id = $ledger_query->add_item(array_merge($ledger_base, [
                'account' => 'registration_fees',
                'entry_type' => 'adjustment',
                'description' => 'Second Day Discount',
                'debit' => 0,
                'credit' => $discount['discount_amount'],
            ]));
            if (!$entry_id) {
                throw new Exception("Failed to insert registration_fees discount adjustment for purchase {$row->purchase_id}.");
            }
            $entry_id = $ledger_query->add_item(array_merge($ledger_base, [
                'account' => 'revenue',
                'entry_type' => 'adjustment',
                'description' => 'Second Day Discount',
                'debit' => $discount['discount_amount'],
                'credit' => 0,
            ]));
            if (!$entry_id) {
                throw new Exception("Failed to insert revenue discount adjustment for purchase {$row->purchase_id}.");
            }

            $discounts_json = wp_json_encode([[
                'code' => 'second_day',
                'value' => $discount['discount_amount'],
                'amount' => $discount['discount_amount'],
                'reason' => 'Second Day Discount',
            ]]);
            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            $purchase_query->update_item($row->purchase_id, ['discounts' => $discounts_json]);

            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function run($fix = false)
    {
        $candidates = $this->find_candidates();
        if (empty($candidates)) {
            WP_CLI::success('No candidate purchases found.');
            return;
        }

        $to_fix = 0;
        $fixed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($candidates as $row) {
            $student_name = trim($row->student_first . ' ' . $row->student_last);
            $label = sprintf(
                'purchase #%d | registration #%d | %s | order #%d',
                $row->purchase_id,
                $row->registration_id,
                $student_name,
                $row->order_id
            );

            try {
                $discount = $this->resolve_discount($row);
            } catch (Throwable $e) {
                WP_CLI::log("$label -> SKIPPED: " . $e->getMessage());
                Usctdp_Mgmt::logger()->log_exception('USCTDP: backfill_second_day_discounts resolve', $e);
                $skipped++;
                continue;
            }

            if ($discount['skip'] !== null) {
                // Not every candidate is actually a discounted second day
                // (most are one-day clinics or tournaments, or a two-day
                // item's first day) - only log the ones that are, to keep
                // the report readable.
                $skipped++;
                continue;
            }

            $to_fix++;
            WP_CLI::log(sprintf(
                '%s -> restate base $%.2f, discount $%.2f (currently charged net $%.2f)',
                $label,
                $discount['day1_price'],
                $discount['discount_amount'],
                $discount['net_price']
            ));

            if (!$fix) {
                continue;
            }

            try {
                $this->backfill_purchase($row, $discount);
                WP_CLI::log('  -> backfilled');
                $fixed++;
            } catch (Throwable $e) {
                WP_CLI::log('  -> SKIPPED: ' . $e->getMessage());
                Usctdp_Mgmt::logger()->log_exception('USCTDP: backfill_second_day_discounts', $e);
                $failed++;
            }
        }

        WP_CLI::log('');
        if ($fix) {
            WP_CLI::log(sprintf('Backfilled %d, failed %d, out of %d affected purchase(s) found (%d other candidate(s) didn\'t apply).', $fixed, $failed, $to_fix, $skipped));
        } else {
            WP_CLI::log(sprintf('%d affected purchase(s) found (%d other candidate(s) checked and didn\'t apply). Re-run with --fix to backfill them.', $to_fix, $skipped));
        }
    }
}
