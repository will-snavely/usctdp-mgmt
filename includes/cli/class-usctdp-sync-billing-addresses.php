<?php

/**
 * Backfills usctdp_family.address/city/state/zip from the WooCommerce
 * billing address already on file for that family's linked WP account
 * (wp_usermeta's billing_address_1/address_2/city/state/postcode).
 *
 * Those four columns are only ever populated today via legacy-import
 * staging (Usctdp_Stage_Legacy_Families / class-usctdp-mgmt-import-confirm-hooks.php)
 * or a staff edit in the admin Families panel - create_family_on_registration()
 * (class-usctdp-mgmt-woocommerce-hooks.php) never collects an address at
 * signup, so most self-registered families have all four blank even though
 * the customer's own billing address is sitting right there in their
 * account (entered at checkout, or editable under My Account > Addresses).
 *
 * Deliberately fill-only, per field: a family that already has something on
 * file in address/city/state/zip (from either source above) keeps it -
 * only a currently-blank field gets set from billing, and only for
 * families with a linked account (user_id) whose billing address has
 * something to offer. Nothing here overwrites a value someone already
 * entered or corrected.
 *
 * Report-only by default; --fix writes. See Usctdp_Void_Stale_Registrations
 * for the same report/--fix shape this follows.
 */
class Usctdp_Sync_Billing_Addresses
{
    public function run($fix = false)
    {
        $all_families = (new Usctdp_Mgmt_Family_Query(['number' => 0]))->items;

        $stats = [
            'total_families' => count($all_families),
            'no_user' => 0,
            'no_billing_address' => 0,
            'already_complete' => 0,
            'families_to_update' => 0,
            'fields_to_update' => 0,
        ];
        $ops = [];

        foreach ($all_families as $family) {
            if (empty($family->user_id)) {
                $stats['no_user']++;
                continue;
            }

            $billing = $this->get_billing_address($family->user_id);
            if ($billing === null) {
                $stats['no_billing_address']++;
                continue;
            }

            $args = [];
            foreach (['address', 'city', 'state', 'zip'] as $field) {
                if ($family->$field === '' && $billing[$field] !== '') {
                    $args[$field] = $billing[$field];
                }
            }

            if (empty($args)) {
                $stats['already_complete']++;
                continue;
            }

            $ops[] = ['family' => $family, 'args' => $args];
            $stats['families_to_update']++;
            $stats['fields_to_update'] += count($args);
        }

        $this->report($ops, $stats, $fix);

        if (!$fix) {
            return;
        }

        $updated_families = 0;
        $updated_fields = 0;
        $family_query = new Usctdp_Mgmt_Family_Query();
        foreach ($ops as $op) {
            if ($family_query->update_item($op['family']->id, $op['args'])) {
                $updated_families++;
                $updated_fields += count($op['args']);
            }
        }

        WP_CLI::log('');
        WP_CLI::success(sprintf(
            'Updated %d field(s) across %d family(ies).',
            $updated_fields,
            $updated_families
        ));
    }

    private function report($ops, $stats, $fix)
    {
        $prefix = $fix ? '' : '[DRY RUN] ';

        if (!empty($ops)) {
            WP_CLI::log(sprintf('%sFamilies to update:', $prefix));
            foreach ($ops as $op) {
                $family = $op['family'];
                $changes = [];
                foreach ($op['args'] as $field => $value) {
                    $changes[] = sprintf('%s: "" -> "%s"', $field, $value);
                }
                WP_CLI::log(sprintf(
                    '  family #%d "%s" | %s',
                    $family->id,
                    $family->title,
                    implode(' | ', $changes)
                ));
            }
            WP_CLI::log('');
        }

        WP_CLI::log(sprintf(
            'Families: %d total | %d with no linked account | %d with no billing address on file | %d already fully populated',
            $stats['total_families'],
            $stats['no_user'],
            $stats['no_billing_address'],
            $stats['already_complete']
        ));
        WP_CLI::log(sprintf(
            'To update: %d field(s) across %d family(ies)',
            $stats['fields_to_update'],
            $stats['families_to_update']
        ));

        if (!$fix && !empty($ops)) {
            WP_CLI::log('');
            WP_CLI::log('Re-run with --fix to apply these updates.');
        }
    }

    /**
     * Returns ['address' => ..., 'city' => ..., 'state' => ..., 'zip' => ...]
     * (any of which may be '') from that user's WooCommerce billing meta,
     * or null if nothing is on file at all. address_1/address_2 are joined
     * with a comma when both are present, matching usctdp_family's single
     * address column (no separate line 2).
     */
    private function get_billing_address($user_id)
    {
        $address_1 = trim((string) get_user_meta($user_id, 'billing_address_1', true));
        $address_2 = trim((string) get_user_meta($user_id, 'billing_address_2', true));
        $city = trim((string) get_user_meta($user_id, 'billing_city', true));
        $state = trim((string) get_user_meta($user_id, 'billing_state', true));
        $zip = trim((string) get_user_meta($user_id, 'billing_postcode', true));

        $address = implode(', ', array_filter([$address_1, $address_2], function ($part) {
            return $part !== '';
        }));

        if ($address === '' && $city === '' && $state === '' && $zip === '') {
            return null;
        }

        return [
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ];
    }
}
