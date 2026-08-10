<?php

/**
 * Tiered tournament pricing for the customer-facing store.
 *
 * A tournament's usctdp_pricing row can carry up to three price points,
 * e.g. {"base":155,"early_signup":150,"with_clinic":145}:
 *
 *   base          The WooCommerce variation price (set at import time by
 *                 Usctdp_Import_Session_Data), charged when no tier applies.
 *   early_signup  Charged while today is on or before the tournament's
 *                 early_registration_deadline (usctdp_tournament row).
 *   with_clinic   Charged when the student being registered is enrolled in a
 *                 clinic running alongside the tournament - either an
 *                 existing 'active' clinic registration, or a clinic being
 *                 purchased for the same student in the same cart.
 *
 * Tiers don't stack: when more than one applies, the customer pays the
 * lowest applicable price (the JSON values are absolute prices, not
 * deductions). Flat-fee tournaments (pricing JSON with only "base", or no
 * pricing row at all) are left entirely alone - the WooCommerce variation
 * price stands.
 *
 * "Running alongside" means sharing the tournament session's season, not
 * session identity or date overlap: the tournament and its concurrent
 * clinics live in *different* usctdp_session rows, and a program year's
 * offerings all carry the same usctdp_session.season value (e.g. junior
 * clinic Sessions I-IV and the fall/winter round robins are all
 * 'fall-winter'). Strict date overlap was tried first and rejected: a
 * Session I clinic (Aug-Oct) is supposed to qualify a student for the fall
 * round robin (Nov-Feb) even though the two date ranges never intersect.
 *
 * The discounted price is applied by rewriting the cart line item's price in
 * woocommerce_before_calculate_totals, which WooCommerce re-runs on every
 * cart mutation - so the price self-corrects if the early deadline passes
 * while the cart sits, or if the qualifying clinic is removed from the cart.
 * Because the discount lands on the line item total itself,
 * create_purchase_and_ledger_entries() (which books tournament ledger rows
 * from $item->get_total()) needs no changes to stay consistent.
 */
class Usctdp_Mgmt_Tournament_Pricing
{
    const TIER_LABELS = [
        'early_signup' => 'Early registration discount',
        'with_clinic' => 'Clinic student discount',
    ];

    /**
     * Per-request caches. Static lookups (activity/pricing/tournament/
     * session rows and the student's *existing* clinic registrations) can't
     * change mid-request, so they're memoized; the clinic-in-same-cart check
     * deliberately is not, since add/remove-item requests mutate the cart
     * and then recalculate within the same request.
     */
    private $context_cache = [];
    private $db_clinic_cache = [];

    /**
     * Resolve the discount tier for one cart line item. Returns null when no
     * discount applies (not a tournament item, no tiered pricing, or no tier
     * qualifies - in all of which cases the caller should leave the
     * WooCommerce price untouched), otherwise
     * ['tier' => 'early_signup'|'with_clinic', 'price' => float, 'label' => string].
     *
     * Called from all three hooks below rather than computing once and
     * stashing the result on the cart item, so the price, the cart's
     * "Pricing" row, and the order item meta can never drift apart -
     * memoization keeps the repeated calls cheap.
     */
    public function resolve_discount($cart_item, $cart_contents)
    {
        if (empty($cart_item['tournament_activity_id'])) {
            return null;
        }
        $context = $this->get_tournament_context((int) $cart_item['tournament_activity_id']);
        if (!$context) {
            return null;
        }

        $candidates = [];
        if ($this->early_tier_applies($context)) {
            $candidates['early_signup'] = floatval($context['pricing']['early_signup']);
        }
        $student_id = isset($cart_item['student_id']) ? (int) $cart_item['student_id'] : 0;
        if ($this->clinic_tier_applies($context, $student_id, $cart_contents)) {
            $candidates['with_clinic'] = floatval($context['pricing']['with_clinic']);
        }
        if (empty($candidates)) {
            return null;
        }

        asort($candidates);
        $tier = array_key_first($candidates);
        return [
            'tier' => $tier,
            'price' => $candidates[$tier],
            'label' => self::TIER_LABELS[$tier],
        ];
    }

    /**
     * woocommerce_before_calculate_totals: rewrite each qualifying
     * tournament line item's price to its discounted tier price.
     */
    public function apply_tournament_pricing($cart)
    {
        // Don't touch prices while an admin edits an order in wp-admin;
        // AJAX (add-to-cart, checkout fragments) still needs to run.
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['data'])) {
                continue;
            }
            $discount = $this->resolve_discount($cart_item, $cart->get_cart());
            if ($discount) {
                $cart_item['data']->set_price($discount['price']);
            }
        }
    }

    /**
     * woocommerce_get_item_data: show which tier applied as a "Pricing" row
     * under the cart/checkout line item, alongside the Student Name /
     * Activity rows Usctdp_Mgmt_Woocommerce_Hooks::get_item_data() adds.
     */
    public function add_discount_item_data($item_data, $cart_item)
    {
        $cart_contents = WC()->cart ? WC()->cart->get_cart() : [];
        $discount = $this->resolve_discount($cart_item, $cart_contents);
        if ($discount) {
            $item_data[] = [
                'key' => 'Pricing',
                'value' => $discount['tier'],
                'display' => $discount['label'],
            ];
        }
        return $item_data;
    }

    /**
     * woocommerce_checkout_create_order_line_item: persist the applied tier
     * on the order item - 'Pricing' for customer emails / the admin order
     * screen, '_price_tier' for anything programmatic later.
     */
    public function add_discount_order_item_meta($item, $cart_item_key, $values, $order)
    {
        $cart_contents = WC()->cart ? WC()->cart->get_cart() : [];
        $discount = $this->resolve_discount($values, $cart_contents);
        if ($discount) {
            $item->add_meta_data('_price_tier', $discount['tier']);
            $item->add_meta_data('Pricing', $discount['label']);
        }
    }

    /**
     * Load everything static the tier checks need for one tournament
     * activity. Returns null (cached) for anything that can't have a tiered
     * discount: unknown/non-tournament activities, no pricing row, or a
     * flat-fee pricing JSON with no early_signup/with_clinic keys.
     */
    private function get_tournament_context($activity_id)
    {
        if (array_key_exists($activity_id, $this->context_cache)) {
            return $this->context_cache[$activity_id];
        }

        $context = null;
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'id' => $activity_id,
            'number' => 1,
        ]);
        $activity = $activity_query->items[0] ?? null;
        if ($activity && $activity->type === 'tournament') {
            $pricing_row = Usctdp_Mgmt_Model::get_activity_pricing($activity);
            $pricing = $pricing_row ? $pricing_row->pricing : null;
            if (is_array($pricing) && (!empty($pricing['early_signup']) || !empty($pricing['with_clinic']))) {
                $tournament_query = new Usctdp_Mgmt_Tournament_Query([
                    'id' => $activity_id,
                    'number' => 1,
                ]);
                $context = [
                    'pricing' => $pricing,
                    'tournament' => $tournament_query->items[0] ?? null,
                    'session' => Usctdp_Mgmt_Model::get_session($activity->session_id),
                ];
            }
        }

        return $this->context_cache[$activity_id] = $context;
    }

    /**
     * Early tier: an early_signup price exists and today (site timezone) is
     * on or before the tournament's early_registration_deadline - deadline
     * day inclusive. Tournaments with no usctdp_tournament row or no
     * deadline never qualify.
     */
    private function early_tier_applies($context)
    {
        if (empty($context['pricing']['early_signup']) || empty($context['tournament'])) {
            return false;
        }
        $deadline = $context['tournament']->early_registration_deadline;
        if (!$deadline) {
            return false;
        }
        return current_time('Y-m-d') <= $deadline->format('Y-m-d');
    }

    private function clinic_tier_applies($context, $student_id, $cart_contents)
    {
        if (empty($context['pricing']['with_clinic']) || empty($context['session']) || !$student_id) {
            return false;
        }
        // A session with no season can't match anything - refuse the
        // discount rather than treating '' == '' as a match.
        $season = (string) $context['session']->season;
        if ($season === '') {
            return false;
        }
        if ($this->cart_has_clinic_in_season($student_id, $season, $cart_contents)) {
            return true;
        }
        return $this->student_has_active_clinic_in_season($student_id, $season);
    }

    /**
     * Same-transaction qualification: a clinic registration for the same
     * student elsewhere in this cart, in a session sharing the tournament's
     * season. Clinic cart items are recognized by day_of_week_1 (set only by
     * the clinic day picker - see add_cart_item_data()), and their
     * 'activities' entries are usctdp_activity ids (clinic rows share their
     * activity's id, same as tournament rows do).
     *
     * Checkout can't grant this and then lose the clinic: after_checkout_
     * validation() reserves every cart line all-or-nothing, so if the clinic
     * can't be reserved the tournament isn't charged either.
     */
    private function cart_has_clinic_in_season($student_id, $season, $cart_contents)
    {
        foreach ($cart_contents as $other_item) {
            if (empty($other_item['day_of_week_1'])) {
                continue;
            }
            if ((int) ($other_item['student_id'] ?? 0) !== $student_id) {
                continue;
            }
            $activity_query = new Usctdp_Mgmt_Activity_Query([
                'id' => (int) $other_item['day_of_week_1'],
                'number' => 1,
            ]);
            $clinic_activity = $activity_query->items[0] ?? null;
            if (!$clinic_activity || $clinic_activity->type !== 'clinic') {
                continue;
            }
            $clinic_session = Usctdp_Mgmt_Model::get_session($clinic_activity->session_id);
            if ($clinic_session && (string) $clinic_session->season === $season) {
                return true;
            }
        }
        return false;
    }

    /**
     * Existing-registration qualification: an 'active' registration on a
     * clinic activity whose session shares the tournament's season.
     * 'pending' deliberately doesn't count - a pending row is either this
     * same cart mid-checkout (already covered by the cart check above) or an
     * abandoned attempt that was never paid for.
     */
    private function student_has_active_clinic_in_season($student_id, $season)
    {
        $cache_key = $student_id . ':' . $season;
        if (isset($this->db_clinic_cache[$cache_key])) {
            return $this->db_clinic_cache[$cache_key];
        }

        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}usctdp_registration reg
             JOIN {$wpdb->prefix}usctdp_activity act ON act.id = reg.activity_id
             JOIN {$wpdb->prefix}usctdp_session sess ON sess.id = act.session_id
             WHERE reg.student_id = %d
             AND reg.status = %s
             AND act.type = %s
             AND sess.season = %s",
            $student_id,
            'active',
            'clinic',
            $season
        ));

        return $this->db_clinic_cache[$cache_key] = ((int) $count > 0);
    }
}
