<?php

/**
 * Voids 'pending' registrations that never went anywhere - created by
 * after_checkout_validation() (class-usctdp-mgmt-woocommerce-hooks.php)
 * reserving a seat during checkout validation, but the checkout was then
 * abandoned (browser closed) or rejected by some later, unrelated
 * validation step before WooCommerce ever created an order for it.
 *
 * Nothing else reaps these: release_registrations_for_order() only runs off
 * the woocommerce_order_status_failed/cancelled hooks, which need an actual
 * order to fire against - a registration whose checkout attempt never
 * produced one is invisible to it. They already stop counting toward
 * capacity once Usctdp_Mgmt_Woocommerce_Hooks::HOLD_MINUTES elapses (see
 * after_checkout_validation()'s own count query, and
 * Usctdp_Mgmt_Public::get_enrolled_counts_by_group()), so this isn't an
 * overbooking risk - what it left behind is a permanently 'pending' row
 * that can wrongly block that same student from a future "already
 * enrolled" check (after_checkout_validation()'s tracking_id comparison)
 * if a retry attempt gets a new tracking_id.
 *
 * Scoped to order_id IS NULL specifically - never touches a 'pending'
 * registration that HAS an order_id. That's a real, tracked order still
 * awaiting a slow-clearing payment method (e.g. a mailed check leaves the
 * order on WooCommerce's 'on-hold' status, which neither confirms nor
 * releases it) - voiding that would be wrong, not a cleanup.
 *
 * Report-only by default; --fix voids what's found. Currently a manual
 * command - see wp usctdp release_stale_registrations in cli-commands.php -
 * intended to eventually run on a schedule (a WP-Cron event or system cron
 * calling wp usctdp release_stale_registrations --fix), but that wiring
 * isn't in place yet.
 */
class Usctdp_Void_Stale_Registrations
{
    /**
     * 'pending' registrations older than $older_than_minutes with no
     * order_id at all. The threshold is deliberately independent of (and
     * larger than) HOLD_MINUTES - HOLD_MINUTES governs when a pending
     * reservation stops COUNTING toward capacity, which is a different,
     * more time-sensitive concern than "is this checkout attempt clearly
     * never coming back", so this leaves a wider safety margin before
     * voiding anything outright.
     */
    private function find_stale_pending_registrations($older_than_minutes)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "   SELECT
                    reg.id AS registration_id,
                    reg.activity_id AS activity_id,
                    reg.tracking_id AS tracking_id,
                    reg.created_at AS registration_created_at,
                    stud.id AS student_id,
                    stud.first AS student_first,
                    stud.last AS student_last,
                    act.title AS activity_title
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON stud.id = reg.student_id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                WHERE reg.status = 'pending'
                AND reg.order_id IS NULL
                AND reg.created_at < NOW() - INTERVAL %d MINUTE
                ORDER BY reg.id ASC",
            $older_than_minutes
        ));
    }

    /**
     * Same status transition release_registrations_for_order() already
     * performs for order-scoped abandonment ('pending' -> 'void') - see
     * that method's doc comment for why 'void' (not a new status) is what
     * "this registration doesn't count" means throughout this codebase.
     */
    private function void_registration($registration_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query();
        return $reg_query->update_item($registration_id, [
            'status' => 'void',
            'modified_at' => current_time('mysql'),
            'modified_by' => get_current_user_id(),
        ]);
    }

    public function release($older_than_minutes = 60, $fix = false)
    {
        $rows = $this->find_stale_pending_registrations($older_than_minutes);

        if (empty($rows)) {
            WP_CLI::success(
                "No stale pending registrations found (pending, no order, older than $older_than_minutes minute(s))."
            );
            return;
        }

        WP_CLI::log(sprintf(
            'Found %d stale pending registration(s) (pending, no order attached, older than %d minute(s)):',
            count($rows),
            $older_than_minutes
        ));
        WP_CLI::log('');

        $voided = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $student_name = trim($row->student_first . ' ' . $row->student_last);
            WP_CLI::log(sprintf(
                'registration #%d | %s | %s | tracking_id=%s | created %s',
                $row->registration_id,
                $student_name,
                $row->activity_title,
                $row->tracking_id ?: '(none)',
                $row->registration_created_at
            ));

            if (!$fix) {
                continue;
            }

            if ($this->void_registration($row->registration_id)) {
                WP_CLI::log('  -> voided');
                $voided++;
            } else {
                WP_CLI::log('  -> FAILED to void');
                $failed++;
            }
        }

        WP_CLI::log('');
        if ($fix) {
            WP_CLI::log(sprintf('Voided %d, failed %d, out of %d found.', $voided, $failed, count($rows)));
        } else {
            WP_CLI::log(sprintf('%d stale pending registration(s) found. Re-run with --fix to void them.', count($rows)));
        }
    }
}
