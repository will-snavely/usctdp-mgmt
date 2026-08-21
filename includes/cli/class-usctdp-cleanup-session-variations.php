<?php

/**
 * Permanently deletes WooCommerce variations that
 * Usctdp_Mgmt_Woocommerce::sync_product_variations() has taken off sale -
 * see that method's docblock for why it disables (status = 'private')
 * rather than deletes a variation when its session goes off sale: it keeps
 * variation IDs stable for anything still holding one (an open cart, a
 * historical order), and lets a re-published session reuse the same
 * variation instead of minting a new one.
 *
 * Over time, though, sessions that never come back leave their disabled
 * variations behind permanently. This is the explicit, occasional cleanup
 * for that - deliberately NOT run automatically as part of the sync itself,
 * since doing so would reintroduce exactly the "deleted out from under
 * something still using it" risk that disabling instead of deleting was
 * built to avoid.
 */
class Usctdp_Cleanup_Session_Variations
{
    /**
     * @param int  $older_than_days Only consider variations disabled at
     *                              least this long (by post_modified - the
     *                              only thing that ever touches a disabled
     *                              variation again is sync_product_variations()
     *                              re-enabling it, which would take it out
     *                              of contention here anyway).
     * @param bool $fix             Actually delete. Without this, report only.
     */
    public function cleanup($older_than_days, $fix)
    {
        $variation_ids = get_posts([
            'post_type' => 'product_variation',
            'post_status' => 'private',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => [
                [
                    'column' => 'post_modified',
                    'before' => "{$older_than_days} days ago",
                ],
            ],
        ]);

        $deletable = [];
        $referenced = [];
        $skipped_non_session = 0;

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->get_parent_id()) {
                continue;
            }

            $attrs = $variation->get_attributes();
            if (empty($attrs['session'])) {
                // A disabled variation that isn't one of this system's -
                // e.g. a merchandise size/color someone disabled by hand -
                // isn't this command's business.
                $skipped_non_session++;
                continue;
            }

            $order_id = $this->find_order_id_referencing_variation($variation_id);
            if ($order_id) {
                $referenced[] = ['variation' => $variation, 'order_id' => $order_id];
                continue;
            }

            $deletable[] = $variation;
        }

        WP_CLI::log(sprintf(
            'Disabled session variations older than %d days: %d deletable, %d still referenced by a past order (never deleted, regardless of age), %d not a session variation (skipped).',
            $older_than_days,
            count($deletable),
            count($referenced),
            $skipped_non_session
        ));

        foreach ($referenced as $r) {
            WP_CLI::log('  SKIP (order #' . $r['order_id'] . '): ' . $this->describe_variation($r['variation']));
        }

        foreach ($deletable as $variation) {
            $label = $this->describe_variation($variation);
            if ($fix) {
                $parent_id = $variation->get_parent_id();
                $variation->delete(true);
                // Busts the parent's cached price range/stock-status data,
                // which a direct variation delete() doesn't otherwise
                // refresh on its own.
                wc_delete_product_transients($parent_id);
                WP_CLI::log('  Deleted: ' . $label);
            } else {
                WP_CLI::log('  Would delete: ' . $label);
            }
        }

        if (!$fix && !empty($deletable)) {
            WP_CLI::log('Re-run with --fix to actually delete these.');
        }
    }

    private function describe_variation($variation)
    {
        $attrs = $variation->get_attributes();
        $parts = array_filter([$attrs['session'] ?? null, $attrs['days-per-week'] ?? null]);
        return 'variation ' . $variation->get_id()
            . ' (product ' . $variation->get_parent_id() . ', ' . implode(' / ', $parts) . ')';
    }

    /**
     * Same wp_woocommerce_order_itemmeta/wp_woocommerce_order_items join
     * Usctdp_Reconcile_Ledger::find_order_id_by_tracking_id() uses for
     * '_tracking_id' - here checking WooCommerce's own '_variation_id' meta
     * key instead, to find whether any order has ever included this
     * specific variation. Assumes traditional (non-HPOS) order item
     * storage, same as that method.
     */
    private function find_order_id_referencing_variation($variation_id)
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "   SELECT oi.order_id
                FROM {$wpdb->prefix}woocommerce_order_itemmeta AS oim
                JOIN {$wpdb->prefix}woocommerce_order_items AS oi
                    ON oi.order_item_id = oim.order_item_id
                WHERE oim.meta_key = '_variation_id' AND oim.meta_value = %d
                LIMIT 1",
            $variation_id
        ));
    }
}
