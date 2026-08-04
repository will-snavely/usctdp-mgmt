<?php

/**
 * Finds 'active' registrations with no evidence of payment - the exact
 * signature confirm_registration()'s previously no-op'd order_id filter
 * could leave behind (see class-usctdp-mgmt-woocommerce-hooks.php): a
 * registration that got activated by some *other* customer's successful
 * order, while its own payment failed or was never completed.
 *
 * Report-only, deliberately with no --fix. Unlike reconcile_ledger (where
 * backfilling a missing ledger entry for a payment that genuinely happened
 * is mechanical), what to do with a registration that was activated
 * without ever being paid for is a judgment call - void it, contact the
 * family, chase the payment down - not something to auto-resolve.
 */
class Usctdp_Audit_Registrations
{
    /**
     * 'active' registrations with either no purchase attached at all
     * (purchase_id is 0 until create_purchase_and_ledger_entries() sets it -
     * see after_checkout_validation()'s add_item() call, which doesn't
     * include purchase_id) or a purchase with zero matching ledger rows
     * (the same silent-insert-failure signature reconcile_ledger looks for).
     *
     * order_id is a real column on usctdp_registration again (see
     * class-usctdp-mgmt-registration-table.php), but every row this audit
     * is actually looking for predates that fix by definition - they were
     * wrongly activated by confirm_registration()'s previously no-op'd
     * order_id filter, back when nothing ever set order_id at all. So it'll
     * be NULL on precisely the rows we care about. Falling back to the same
     * tracking_id => '_tracking_id' order-item-meta join reconcile_ledger
     * uses (LEFT JOINed, since even that may not resolve for very old rows)
     * keeps the report useful for exactly the historical data it exists to
     * surface, while still preferring the real column once it's populated.
     */
    private function find_unpaid_active_registrations()
    {
        global $wpdb;

        return $wpdb->get_results(
            "   SELECT
                    reg.id AS registration_id,
                    COALESCE(reg.order_id, oi.order_id) AS order_id,
                    reg.purchase_id AS purchase_id,
                    reg.tracking_id AS tracking_id,
                    reg.created_at AS registration_created_at,
                    stud.id AS student_id,
                    stud.first AS student_first,
                    stud.last AS student_last,
                    act.title AS activity_title
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON stud.id = reg.student_id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim
                    ON oim.meta_key = '_tracking_id' AND oim.meta_value = reg.tracking_id
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi
                    ON oi.order_item_id = oim.order_item_id
                WHERE reg.status = 'active'
                AND (
                    reg.purchase_id = 0
                    OR NOT EXISTS (
                        SELECT 1 FROM {$wpdb->prefix}usctdp_ledger AS led
                        WHERE led.purchase_id = reg.purchase_id
                    )
                )
                ORDER BY reg.id ASC"
        );
    }

    public function audit()
    {
        $rows = $this->find_unpaid_active_registrations();

        if (empty($rows)) {
            WP_CLI::success('No active registrations without payment evidence found.');
            return;
        }

        WP_CLI::log(sprintf('Found %d active registration(s) with no evidence of payment:', count($rows)));
        WP_CLI::log('');

        foreach ($rows as $row) {
            $student_name = trim($row->student_first . ' ' . $row->student_last);
            $purchase_note = ((int) $row->purchase_id === 0)
                ? 'no purchase attached'
                : "purchase #{$row->purchase_id}, no ledger entries";
            WP_CLI::log(sprintf(
                'registration #%d | order_id=%s | %s | %s | tracking_id=%s | %s | registered %s',
                $row->registration_id,
                $row->order_id ?: '(unresolved)',
                $student_name,
                $row->activity_title,
                $row->tracking_id ?: '(none)',
                $purchase_note,
                $row->registration_created_at
            ));
        }

        WP_CLI::log('');
        WP_CLI::log(sprintf(
            '%d active registration(s) found with no payment evidence. Review each one against the ' .
            'order it resolved to (order_id "(unresolved)" means no order line item matched its tracking_id) ' .
            'before deciding whether to void it, chase the payment, or leave it as-is.',
            count($rows)
        ));
    }
}
