<?php

/**
 * Backfills each clinic/cardio/tournament product's WooCommerce variations
 * with '_session_id' meta - the direct session_id -> variation link
 * Usctdp_Mgmt_Woocommerce::find_variations_for_session() now resolves
 * against, instead of matching the mutable 'Session' attribute value. See
 * that method and sync_product_variations() for the full reasoning.
 *
 * sync_product_variations() only stamps this meta on a variation when it's
 * part of the *currently on_sale* combo set for its product, so it self-heals
 * on its own eventually - but only the next time that specific product's
 * on-sale sessions change, and never for a variation whose session is
 * already off sale (exactly the case this whole change exists for). This
 * command does the one-time catch-up directly: it resolves every variation's
 * '_session_id' from ALL sessions the product has ever been priced for
 * (Usctdp_Mgmt_Pricing_Query rows, regardless of status) matched by title,
 * covering every existing variation - published or disabled - in one pass.
 */
class Usctdp_Backfill_Session_Variation_Ids
{
    public function run($fix)
    {
        $product_query = new Usctdp_Mgmt_Product_Query([
            'type__in' => ['clinic', 'cardio', 'tournament'],
            'number' => 0,
        ]);

        $updated = 0;
        $already_set = 0;
        $skipped_no_attribute = 0;
        $skipped_unmatched = 0;

        foreach ($product_query->items as $product) {
            $woo_product = wc_get_product($product->woocommerce_id);
            if (!$woo_product || !$woo_product->is_type('variable')) {
                continue;
            }

            $pricing_query = new Usctdp_Mgmt_Pricing_Query([
                'product_id' => $product->id,
                'number' => 0,
            ]);
            $session_ids = array_unique(array_map(function ($row) {
                return $row->session_id;
            }, $pricing_query->items));
            if (empty($session_ids)) {
                continue;
            }

            $sessions_by_title = [];
            $session_query = new Usctdp_Mgmt_Session_Query([
                'id__in' => $session_ids,
                'number' => 0,
            ]);
            foreach ($session_query->items as $session) {
                $sessions_by_title[$session->title] = $session->id;
            }

            foreach ($woo_product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation || !$variation->exists()) {
                    continue;
                }

                $attrs = $variation->get_attributes();
                $session_name = $attrs['session'] ?? '';
                if (empty($session_name)) {
                    // A blank 'session' attribute - a pre-per-session
                    // variation predating this product's per-session setup
                    // ("Any Session" in the admin UI) - has no session to
                    // resolve.
                    $skipped_no_attribute++;
                    continue;
                }

                if (!isset($sessions_by_title[$session_name])) {
                    WP_CLI::log(sprintf(
                        '  SKIP: variation %d (product %d) - no session titled "%s" found in this product\'s pricing history.',
                        $variation_id,
                        $product->id,
                        $session_name
                    ));
                    $skipped_unmatched++;
                    continue;
                }

                $session_id = $sessions_by_title[$session_name];
                if ((int) $variation->get_meta('_session_id') === (int) $session_id) {
                    $already_set++;
                    continue;
                }

                if ($fix) {
                    $variation->update_meta_data('_session_id', $session_id);
                    $variation->save();
                }
                WP_CLI::log(sprintf(
                    '  %s: variation %d (product %d, "%s") -> session %d',
                    $fix ? 'SET' : 'WOULD SET',
                    $variation_id,
                    $product->id,
                    $session_name,
                    $session_id
                ));
                $updated++;
            }
        }

        WP_CLI::log(sprintf(
            '%s %d variation(s): %d already set, %d skipped (no session attribute), %d skipped (unmatched title).',
            $fix ? 'Updated' : 'Would update',
            $updated,
            $already_set,
            $skipped_no_attribute,
            $skipped_unmatched
        ));

        if (!$fix && $updated > 0) {
            WP_CLI::log('Re-run with --fix to actually write these.');
        }
    }
}
