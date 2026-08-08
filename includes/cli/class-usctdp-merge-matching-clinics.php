<?php

/**
 * Automates the tedious part of building reservation groups for a newly
 * imported season: given two clinic product titles, finds every (session,
 * day, start_time, end_time) slot where BOTH clinics have exactly one
 * activity - i.e. they're scheduled on the same court at the same time -
 * and merges each such pair into its own new shared reservation group,
 * one per matching slot per session.
 *
 * "Same day and time" and "same day" (as a client might describe two
 * clinics that share a court) collapse to the same matching rule here:
 * exact (day_of_week, start_time, end_time) equality. There's no case in
 * this app's data model where "same day, different time" should share
 * capacity - two classes at different times on the same day use the same
 * court sequentially, not concurrently, so there's nothing to pool. If a
 * real schedule ever has two clinics on the same day at DIFFERENT times
 * that still need to share capacity for some other reason, that's exactly
 * what `wp usctdp merge_reservation_group` (manual, by activity id) is
 * for - this command is deliberately only for the same-court-same-time
 * case, which is the overwhelmingly common one.
 *
 * Scoped per session (not just per clinic) - two activities only actually
 * compete for the same court if they're happening in the same session
 * (registration period); the same day/time slot recurring in a later,
 * unrelated session is a coincidence, not shared capacity.
 */
class Usctdp_Merge_Matching_Clinics
{
    private function find_product($title)
    {
        $product_query = new Usctdp_Mgmt_Product_Query(['title' => $title, 'number' => 1]);
        if (empty($product_query->items)) {
            WP_CLI::error("No product found with title \"$title\".");
        }
        return $product_query->items[0];
    }

    /**
     * One row per (session, day, start_time, end_time) slot where both
     * products have exactly one activity - the JOIN's equality on all
     * three clinic-schedule columns is what enforces "exactly one match
     * each", since a clinic with two activities at the same slot in the
     * same session (shouldn't happen, but not something this assumes away
     * silently) would produce more than one row here rather than picking
     * one arbitrarily.
     */
    private function find_matching_slots($product_a_id, $product_b_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "   SELECT
                    a1.id AS activity_a_id, a1.title AS activity_a_title,
                    a2.id AS activity_b_id, a2.title AS activity_b_title,
                    a1.session_id AS session_id, sess.title AS session_title,
                    g1.capacity AS capacity_a, g2.capacity AS capacity_b
                FROM {$wpdb->prefix}usctdp_activity a1
                JOIN {$wpdb->prefix}usctdp_clinic c1 ON c1.id = a1.id
                JOIN {$wpdb->prefix}usctdp_reservation_group g1 ON g1.id = a1.reservation_group_id
                JOIN {$wpdb->prefix}usctdp_activity a2 ON a2.session_id = a1.session_id
                JOIN {$wpdb->prefix}usctdp_clinic c2 ON c2.id = a2.id
                    AND c2.day_of_week = c1.day_of_week
                    AND c2.start_time = c1.start_time
                    AND c2.end_time = c1.end_time
                JOIN {$wpdb->prefix}usctdp_reservation_group g2 ON g2.id = a2.reservation_group_id
                JOIN {$wpdb->prefix}usctdp_session sess ON sess.id = a1.session_id
                WHERE a1.product_id = %d AND a2.product_id = %d
                ORDER BY a1.session_id ASC, c1.day_of_week ASC, c1.start_time ASC",
            $product_a_id,
            $product_b_id
        ));
    }

    public function merge($clinic_a_title, $clinic_b_title, $dry_run = false)
    {
        $product_a = $this->find_product($clinic_a_title);
        $product_b = $this->find_product($clinic_b_title);

        $slots = $this->find_matching_slots($product_a->id, $product_b->id);
        if (empty($slots)) {
            WP_CLI::warning("No same-day-and-time slots found between \"$clinic_a_title\" and \"$clinic_b_title\".");
            return;
        }

        WP_CLI::log(sprintf(
            'Found %d matching slot(s) between "%s" and "%s":',
            count($slots),
            $clinic_a_title,
            $clinic_b_title
        ));
        WP_CLI::log('');

        $manager = new Usctdp_Manage_Reservation_Groups();
        $merged = 0;
        foreach ($slots as $slot) {
            $capacity = (int) $slot->capacity_a + (int) $slot->capacity_b;
            WP_CLI::log(sprintf(
                '%s | "%s" (cap %d) + "%s" (cap %d) -> shared capacity %d',
                $slot->session_title,
                $slot->activity_a_title,
                $slot->capacity_a,
                $slot->activity_b_title,
                $slot->capacity_b,
                $capacity
            ));

            if ($dry_run) {
                continue;
            }

            $manager->merge([$slot->activity_a_id, $slot->activity_b_id], $capacity);
            $merged++;
        }

        WP_CLI::log('');
        if ($dry_run) {
            WP_CLI::log(count($slots) . ' pair(s) would be merged. Re-run without --dry-run to apply.');
        } else {
            WP_CLI::success("$merged pair(s) merged.");
        }
    }
}
