<?php

/**
 * General-purpose tool for writing a one-off, manually-reasoned ledger
 * adjustment directly against a purchase - e.g. backfilling a payment a bug
 * silently dropped (see the order_id-scoping fix to record_deferred_payment()
 * in class-usctdp-mgmt-woocommerce-hooks.php), correcting a price after the
 * fact, or paying out a refund/house credit without going through the admin
 * UI's Post Payment/Refund modals.
 *
 * Every --type writes the same double-entry pair(s) those existing flows
 * already use, so a manual row can't throw a purchase's balance out of
 * balance the way a single free-form row could:
 *
 *   payment    debit payment_<method>        / credit <purchase.type>_fees
 *              Same pair record_deferred_payment() and
 *              build_ledger_entries_for_line_item() write for a real online
 *              payment (class-usctdp-mgmt-woocommerce-hooks.php /
 *              class-usctdp-mgmt-admin-ajax.php). When --order-id is given,
 *              deliberately uses record_deferred_payment()'s exact
 *              event_id/event/description, so a backfilled row reads
 *              identically to one that hook would have written itself.
 *
 *   adjustment debit/credit <purchase.type>_fees vs revenue, direction-based
 *              Mirrors USCTDP_Admin.createAdjustmentLedger
 *              (admin/js/usctdp-mgmt-admin.js).
 *
 *   payout     debit <purchase.type>_fees / credit payment_<method>
 *              Mirrors USCTDP_Admin.createPayoutLedger.
 *
 *   refund     adjustment (always "decrease") + payout, combined
 *              Mirrors USCTDP_Admin.createRefundLedger.
 *
 *   correction debit/credit <purchase.type>_fees vs payment_<method>,
 *              direction-based, but entry_type stays 'payment' (unlike
 *              payout/refund, which write entry_type 'refund'/
 *              'house_credit'). For fixing a *ledger recording* mistake
 *              where no money actually moved - e.g. record_deferred_payment()
 *              crediting the full undiscounted price instead of what the
 *              card was actually charged (see its discount-netting fix in
 *              class-usctdp-mgmt-woocommerce-hooks.php). A --type=payout/
 *              refund would tag it entry_type 'refund' and inflate
 *              total_refunds on the Purchase History UI, falsely implying
 *              money was paid back; --type=correction instead nets the
 *              purchase's total_payments back down to what was truly
 *              collected, matching the entry_type of the mistake it's
 *              fixing. --direction=decrease reverses an over-recorded
 *              payment (the common case); --direction=increase is for the
 *              rarer case where a payment was recorded too low.
 *
 * Always previews the entries it would write; --fix is required to actually
 * insert them, and asks for interactive confirmation first (skippable with
 * --yes) - same "report first, --fix to act" convention as
 * Usctdp_Reconcile_Ledger/Usctdp_Delete_Family/etc.
 */
class Usctdp_Ledger_Adjust
{
    const VALID_TYPES = ['payment', 'adjustment', 'payout', 'refund', 'correction'];
    const VALID_METHODS = ['cash', 'check', 'card', 'house_credit'];

    private function get_purchase($purchase_id)
    {
        $query = new Usctdp_Mgmt_Purchase_Query(['id' => $purchase_id, 'number' => 1]);
        if (empty($query->items)) {
            return null;
        }
        return $query->items[0];
    }

    private function format_snake_case($str)
    {
        if (empty($str)) {
            return '';
        }
        return ucwords(strtolower(trim(str_replace('_', ' ', $str))));
    }

    private function build_payment_entries($purchase, $amount, $method, $order_id, $reason, $base)
    {
        $amount_str = number_format($amount, 2, '.', '');
        $description = $order_id
            ? 'Payment received in online store.'
            : 'Payment - ' . $reason;

        return [
            array_merge($base, [
                'account' => 'payment_' . $method,
                'payment_method' => $method,
                'debit' => $amount_str,
                'credit' => '0.00',
                'entry_type' => 'payment',
                'description' => $description,
            ]),
            array_merge($base, [
                'account' => $purchase->type . '_fees',
                'payment_method' => $method,
                'debit' => '0.00',
                'credit' => $amount_str,
                'entry_type' => 'payment',
                'description' => $description,
            ]),
        ];
    }

    private function build_adjustment_entries($purchase, $amount, $direction, $reason, $base)
    {
        $amount_str = number_format($amount, 2, '.', '');
        $zero = '0.00';
        $is_increase = $direction === 'increase';
        $description = 'Price ' . ucfirst($direction) . ' - ' . $reason;

        return [
            array_merge($base, [
                'account' => $purchase->type . '_fees',
                'debit' => $is_increase ? $amount_str : $zero,
                'credit' => $is_increase ? $zero : $amount_str,
                'entry_type' => 'adjustment',
                'description' => $description,
            ]),
            array_merge($base, [
                'account' => 'revenue',
                'debit' => $is_increase ? $zero : $amount_str,
                'credit' => $is_increase ? $amount_str : $zero,
                'entry_type' => 'adjustment',
                'description' => $description,
            ]),
        ];
    }

    private function build_payout_entries($purchase, $amount, $method, $check_number, $reason, $base)
    {
        $amount_str = number_format($amount, 2, '.', '');
        $zero = '0.00';
        $check_str = $check_number ? " #$check_number" : '';
        $method_str = $this->format_snake_case($method) . $check_str;
        $description = "Payout - $method_str - $reason";
        $entry_type = $method === 'house_credit' ? 'house_credit' : 'refund';

        return [
            array_merge($base, [
                'account' => $purchase->type . '_fees',
                'debit' => $amount_str,
                'credit' => $zero,
                'entry_type' => $entry_type,
                'description' => $description,
            ]),
            array_merge($base, [
                'account' => 'payment_' . $method,
                'debit' => $zero,
                'credit' => $amount_str,
                'entry_type' => $entry_type,
                'description' => $description,
            ]),
        ];
    }

    /**
     * Same account pair build_payment_entries() writes (<purchase.type>_fees
     * vs payment_<method>), but direction-based like build_adjustment_entries()
     * and deliberately kept at entry_type 'payment' regardless of direction -
     * see the class doc comment for why a decrease here isn't a refund.
     */
    private function build_correction_entries($purchase, $amount, $direction, $method, $reason, $base)
    {
        $amount_str = number_format($amount, 2, '.', '');
        $zero = '0.00';
        $is_decrease = $direction === 'decrease';
        $description = 'Correction (' . ucfirst($direction) . ') - ' . $reason;

        return [
            array_merge($base, [
                'account' => $purchase->type . '_fees',
                'payment_method' => $method,
                'debit' => $is_decrease ? $amount_str : $zero,
                'credit' => $is_decrease ? $zero : $amount_str,
                'entry_type' => 'payment',
                'description' => $description,
            ]),
            array_merge($base, [
                'account' => 'payment_' . $method,
                'payment_method' => $method,
                'debit' => $is_decrease ? $zero : $amount_str,
                'credit' => $is_decrease ? $amount_str : $zero,
                'entry_type' => 'payment',
                'description' => $description,
            ]),
        ];
    }

    private function build_entries($purchase, $type, $amount, $opts, $base)
    {
        switch ($type) {
            case 'payment':
                return $this->build_payment_entries(
                    $purchase,
                    $amount,
                    $opts['method'],
                    $opts['order_id'],
                    $opts['reason'],
                    $base
                );
            case 'adjustment':
                return $this->build_adjustment_entries($purchase, $amount, $opts['direction'], $opts['reason'], $base);
            case 'payout':
                return $this->build_payout_entries($purchase, $amount, $opts['method'], $opts['check_number'], $opts['reason'], $base);
            case 'refund':
                return array_merge(
                    $this->build_adjustment_entries($purchase, $amount, 'decrease', $opts['reason'], $base),
                    $this->build_payout_entries($purchase, $amount, $opts['method'], $opts['check_number'], $opts['reason'], $base)
                );
            case 'correction':
                return $this->build_correction_entries($purchase, $amount, $opts['direction'], $opts['method'], $opts['reason'], $base);
        }
        throw new Exception("Unknown adjustment type: $type");
    }

    /**
     * Validates the flag combination for $type before anything is looked up
     * or built - fails fast with a specific message rather than a confusing
     * downstream error.
     */
    private function validate($type, $amount, $opts)
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new Exception('--type is required and must be one of: ' . implode(', ', self::VALID_TYPES));
        }
        if ($amount <= 0) {
            throw new Exception('--amount must be a positive number.');
        }
        if (empty($opts['reason'])) {
            throw new Exception('--reason is required.');
        }
        if (in_array($type, ['payment', 'payout', 'refund', 'correction'], true)) {
            if (empty($opts['method']) || !in_array($opts['method'], self::VALID_METHODS, true)) {
                throw new Exception('--method is required for --type=' . $type . ' and must be one of: ' . implode(', ', self::VALID_METHODS));
            }
            if (in_array($type, ['payment', 'correction'], true) && $opts['method'] === 'house_credit') {
                throw new Exception('--method=house_credit is not valid for --type=' . $type . ' - it stays entry_type "payment", which house credit is never summed under (see total_house in Usctdp_Mgmt_Purchase_Query). Use --type=payout or refund for house credit instead.');
            }
        }
        if (in_array($type, ['adjustment', 'correction'], true) && !in_array($opts['direction'], ['increase', 'decrease'], true)) {
            throw new Exception('--direction is required for --type=' . $type . ' and must be "increase" or "decrease".');
        }
    }

    private function resolve_reference_id($opts)
    {
        if (!empty($opts['reference_id'])) {
            return $opts['reference_id'];
        }
        if ($opts['order_id']) {
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($opts['order_id']);
                if ($order) {
                    return $order->get_transaction_id() ?: (string) $opts['order_id'];
                }
            }
            return (string) $opts['order_id'];
        }
        if (!empty($opts['check_number'])) {
            return $opts['check_number'];
        }
        return null;
    }

    private function print_preview($purchase, $type, $entries)
    {
        WP_CLI::log(sprintf(
            'Purchase #%d (%s, family #%d%s) - proposed %s entries:',
            $purchase->id,
            $purchase->type,
            $purchase->family_id,
            $purchase->student_id ? ", student #{$purchase->student_id}" : '',
            $type
        ));
        WP_CLI::log('');
        foreach ($entries as $entry) {
            WP_CLI::log(sprintf(
                '  %-22s %-11s debit=%-9s credit=%-9s %s',
                $entry['account'],
                $entry['entry_type'],
                $entry['debit'],
                $entry['credit'],
                $entry['description']
            ));
        }
        WP_CLI::log('');
    }

    public function adjust($purchase_id, $type, $amount, $opts, $fix, $assoc_args)
    {
        try {
            $this->validate($type, $amount, $opts);
        } catch (Throwable $e) {
            WP_CLI::error($e->getMessage());
            return;
        }

        $purchase = $this->get_purchase($purchase_id);
        if (!$purchase) {
            WP_CLI::error("No purchase found with id $purchase_id.");
            return;
        }

        $opts['reference_id'] = $this->resolve_reference_id($opts);
        $order_id = $opts['order_id'];
        $event_id = $order_id ? 'wc_order' . $order_id : 'cli_adjust_' . time() . '_' . $purchase_id;
        $event = $order_id ? ('WooCommerce Order ' . $order_id) : ('Manual Ledger Adjustment (' . $type . ')');

        $base = [
            'purchase_id' => $purchase->id,
            'family_id' => $purchase->family_id,
            'order_id' => $order_id,
            'event_id' => $event_id,
            'event' => $event,
            'payment_method' => null,
            'reference_id' => $opts['reference_id'],
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        ];

        $entries = $this->build_entries($purchase, $type, $amount, $opts, $base);

        $this->print_preview($purchase, $type, $entries);

        if (!$fix) {
            WP_CLI::log('Preview only. Re-run with --fix to write these entries.');
            return;
        }

        WP_CLI::confirm('Write these ledger entries?', $assoc_args);

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            $ids = [];
            foreach ($entries as $entry) {
                $entry_id = $ledger_query->add_item($entry);
                if (!$entry_id) {
                    throw new Exception(
                        "Failed to insert '{$entry['account']}' {$entry['entry_type']} ledger entry. "
                        . 'wpdb->last_error: ' . ($wpdb->last_error ?: '(empty)')
                    );
                }
                $ids[] = $entry_id;
            }
            $wpdb->query('COMMIT');
            WP_CLI::success('Wrote ledger entries: ' . implode(', ', $ids));
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            Usctdp_Mgmt::logger()->log_exception('USCTDP: ledger_adjust', $e);
            WP_CLI::error('Failed to write ledger entries: ' . $e->getMessage());
        }
    }
}
