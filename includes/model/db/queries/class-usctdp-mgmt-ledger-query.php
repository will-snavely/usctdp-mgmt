<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Ledger_Query extends Query
{
    protected $table_name = 'usctdp_ledger';
    protected $table_alias = 'ulgr';
    protected $table_schema = 'Usctdp_Mgmt_Ledger_Schema';
    protected $item_name = 'ledger';
    protected $item_name_plural = 'ledger';
    protected $item_shape = 'Usctdp_Mgmt_Ledger_Row';

    public function get_ledger_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["id"])) {
            $conditions[] = "ulgr.id = %d";
            $where_args[] = $args['id'];
        }
        if (isset($args["family_id"])) {
            $conditions[] = "ulgr.family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["event_id"])) {
            $conditions[] = "ulgr.event_id = %d";
            $where_args[] = $args['event_id'];
        }
        if (isset($args["account"])) {
            $conditions[] = "ulgr.account = %s";
            $where_args[] = $args['account'];
        }
        if (isset($args["order_id"])) {
            $conditions[] = "ulgr.order_id = %d";
            $where_args[] = $args['order_id'];
        }
        if (isset($args["purchase_id"])) {
            $conditions[] = "ulgr.purchase_id = %d";
            $where_args[] = $args['purchase_id'];
        }
        if ($conditions) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        $limit_clause = '';
        $limit_args = [];
        if (isset($args["number"])) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];
        }
        if (isset($args["offset"])) {
            $limit_clause .= " OFFSET %d";
            $limit_args[] = $args['offset'];
        }
        $query = $wpdb->prepare(
            "   SELECT *
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                {$where_clause}
                ORDER BY ulgr.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                {$where_clause}";
        $count_query = $count_sql;
        if (!empty($where_args)) {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);

        return [
            'data' => $window,
            'count' => $count
        ];
    }
    public function get_ledger_events($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["family_id"])) {
            $conditions[] = "family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["purchase_id"])) {
            $conditions[] = "purchase_id = %d";
            $where_args[] = $args['purchase_id'];
        }
        if (isset($args["account"])) {
            $conditions[] = "account = %s";
            $where_args[] = $args['account'];
        }
        if ($conditions) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        $limit_clause = '';
        $limit_args = [];
        if (isset($args["number"]) && $args["number"] > 0) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];

            if (isset($args["offset"])) {
                $limit_clause .= " OFFSET %d";
                $limit_args[] = $args['offset'];
            }
        }

        $query = $wpdb->prepare(
            "SELECT
                id,
                event_id,
                order_id,
                DATE_FORMAT(created_at, '%%Y-%%m-%%dT%%T.%%fZ') as event_date,
                entry_type,
                description as event_description,
                debit as charge_amount,
                credit as payment_amount
            FROM {$wpdb->prefix}usctdp_ledger
            {$where_clause}
            ORDER BY created_at, id ASC
            {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql =
            "SELECT count(id)
            FROM {$wpdb->prefix}usctdp_ledger
            {$where_clause}";
        $count_query = $count_sql;
        if (!empty($where_args)) {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);

        return [
            'data' => $window,
            'count' => $count
        ];
    }

    /**
     * Shared by every earnings-rollup method below: the 'active', non-
     * credit_import purchase filter every figure is scoped to, plus the
     * optional purchase-date range. $args['date_from']/$args['date_to'] are
     * already-converted UTC datetime strings (see
     * Usctdp_Mgmt_Admin_Ajax::eastern_date_range_to_utc()) - this class does
     * no timezone handling of its own.
     */
    private function earnings_base_conditions($args)
    {
        $conditions = ["pur.status = %s", "pur.type != %s"];
        $where_args = ['active', 'credit_import'];

        if (isset($args['date_from'])) {
            $conditions[] = "pur.created_at >= %s";
            $where_args[] = $args['date_from'];
        }
        if (isset($args['date_to'])) {
            $conditions[] = "pur.created_at < %s";
            $where_args[] = $args['date_to'];
        }
        return [$conditions, $where_args];
    }

    /**
     * Appends a `sesh.title LIKE %search%` condition when $args['q'] is a
     * non-empty search term - the Earnings dashboard's session-name search
     * box (get_session_earnings()/get_session_earnings_count() only; it
     * doesn't narrow the summary tiles or the "Other / Unassigned" bucket,
     * which stay whole-range totals). Same $wpdb->esc_like() + LIKE pattern
     * Usctdp_Mgmt_Roster_Group_Query::search_rosters() uses for its own
     * name search.
     */
    private function add_session_search_condition(&$conditions, &$where_args, $args)
    {
        if (!empty($args['q'])) {
            global $wpdb;
            $conditions[] = "sesh.title LIKE %s";
            $where_args[] = '%' . $wpdb->esc_like($args['q']) . '%';
        }
    }

    /**
     * Grand totals (gross revenue + accounts receivable) across every
     * matching purchase, session-linked or not - used for the Earnings
     * dashboard's summary tiles, which must reflect the whole filtered
     * range regardless of which page of sessions is currently showing. No
     * session join needed at all, since nothing is grouped.
     */
    public function get_earnings_totals($args)
    {
        global $wpdb;

        [$conditions, $where_args] = $this->earnings_base_conditions($args);
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $query = $wpdb->prepare(
            "   SELECT
                    SUM(CASE WHEN ulgr.account = 'revenue' THEN ulgr.credit - ulgr.debit ELSE 0 END) AS gross_revenue,
                    SUM(CASE WHEN ulgr.account IN ('registration_fees', 'merchandise_fees')
                             THEN ulgr.debit - ulgr.credit ELSE 0 END) AS receivable
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                {$where_clause}",
            $where_args
        );
        return $wpdb->get_row($query);
    }

    /**
     * Gross revenue + accounts receivable for purchases with no session at
     * all (chiefly merchandise, which has no registration/activity to hang
     * a session off of) - the Earnings dashboard's "Other / Unassigned"
     * row. Kept separate from get_session_earnings() rather than folded
     * into its paginated result set: it's always exactly one row, so
     * pagination doesn't apply to it, and mixing a NULL-session row into an
     * otherwise real, sortable session list would be confusing.
     */
    public function get_unassigned_earnings($args)
    {
        global $wpdb;

        [$conditions, $where_args] = $this->earnings_base_conditions($args);
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $query = $wpdb->prepare(
            "   SELECT
                    SUM(CASE WHEN ulgr.account = 'revenue' THEN ulgr.credit - ulgr.debit ELSE 0 END) AS gross_revenue,
                    SUM(CASE WHEN ulgr.account IN ('registration_fees', 'merchandise_fees')
                             THEN ulgr.debit - ulgr.credit ELSE 0 END) AS receivable
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                LEFT JOIN {$wpdb->prefix}usctdp_registration AS reg ON reg.purchase_id = pur.id
                LEFT JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                LEFT JOIN {$wpdb->prefix}usctdp_session AS sesh ON sesh.id = act.session_id
                {$where_clause}
                AND sesh.id IS NULL",
            $where_args
        );
        return $wpdb->get_row($query);
    }

    /**
     * One page of the Earnings dashboard's per-session breakdown -
     * gross revenue + accounts receivable grouped by session, joining
     * purchase -> registration -> activity -> session the same way
     * Usctdp_Mgmt_Purchase_Query::get_purchase_data() does. Real sessions
     * only (INNER JOINs) - see get_unassigned_earnings() for the
     * no-session bucket, and get_earnings_totals() for the dashboard-wide
     * totals this paginated slice deliberately doesn't add up to on its
     * own.
     *
     * gross_revenue mirrors the ledger's own 'revenue' account (credited
     * once per charge - see build_ledger_entries_for_line_item() in
     * class-usctdp-mgmt-woocommerce-hooks.php). receivable reuses the exact
     * formula get_family_balance() (class-usctdp-mgmt-admin-ajax.php) uses,
     * just grouped by session instead of family.
     */
    public function get_session_earnings($args)
    {
        global $wpdb;

        [$conditions, $where_args] = $this->earnings_base_conditions($args);
        $this->add_session_search_condition($conditions, $where_args, $args);
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $limit_clause = '';
        $limit_args = [];
        if (isset($args['number'])) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];
        }
        if (isset($args['offset'])) {
            $limit_clause .= " OFFSET %d";
            $limit_args[] = $args['offset'];
        }

        $query = $wpdb->prepare(
            "   SELECT
                    sesh.id AS session_id,
                    MAX(sesh.title) AS session_title,
                    MAX(sesh.start_date) AS session_start_date,
                    MAX(sesh.end_date) AS session_end_date,
                    SUM(CASE WHEN ulgr.account = 'revenue' THEN ulgr.credit - ulgr.debit ELSE 0 END) AS gross_revenue,
                    SUM(CASE WHEN ulgr.account IN ('registration_fees', 'merchandise_fees')
                             THEN ulgr.debit - ulgr.credit ELSE 0 END) AS receivable
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                JOIN {$wpdb->prefix}usctdp_registration AS reg ON reg.purchase_id = pur.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON sesh.id = act.session_id
                {$where_clause}
                GROUP BY sesh.id
                ORDER BY session_start_date DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        return $wpdb->get_results($query);
    }

    /**
     * Total number of distinct sessions get_session_earnings() would page
     * over for these filters - the DataTables recordsTotal/recordsFiltered
     * behind the Earnings dashboard's server-side-paginated session list.
     */
    public function get_session_earnings_count($args)
    {
        global $wpdb;

        [$conditions, $where_args] = $this->earnings_base_conditions($args);
        $this->add_session_search_condition($conditions, $where_args, $args);
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $query = "  SELECT COUNT(*) FROM (
                        SELECT sesh.id
                        FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                        JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                        JOIN {$wpdb->prefix}usctdp_registration AS reg ON reg.purchase_id = pur.id
                        JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                        JOIN {$wpdb->prefix}usctdp_session AS sesh ON sesh.id = act.session_id
                        {$where_clause}
                        GROUP BY sesh.id
                    ) AS t";
        if (!empty($where_args)) {
            $query = $wpdb->prepare($query, $where_args);
        }
        return (int) $wpdb->get_var($query);
    }

    /**
     * One page of a single session's earnings broken out by product (the
     * Earnings dashboard's session drill-down) - e.g. a session's separate
     * clinic/tournament offerings and any merchandise tied to its
     * registrations. Every usctdp_purchase row has a NOT NULL product_id,
     * so there's no "unassigned" bucket to worry about at this level.
     */
    public function get_product_earnings_for_session($session_id, $args)
    {
        global $wpdb;

        [$conditions, $where_args] = $this->earnings_base_conditions($args);
        $conditions[] = "act.session_id = %d";
        $where_args[] = $session_id;
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $limit_clause = '';
        $limit_args = [];
        if (isset($args['number'])) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];
        }
        if (isset($args['offset'])) {
            $limit_clause .= " OFFSET %d";
            $limit_args[] = $args['offset'];
        }

        $query = $wpdb->prepare(
            "   SELECT
                    prod.id AS product_id,
                    MAX(prod.title) AS product_title,
                    MAX(prod.type) AS product_type,
                    SUM(CASE WHEN ulgr.account = 'revenue' THEN ulgr.credit - ulgr.debit ELSE 0 END) AS gross_revenue,
                    SUM(CASE WHEN ulgr.account IN ('registration_fees', 'merchandise_fees')
                             THEN ulgr.debit - ulgr.credit ELSE 0 END) AS receivable
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON prod.id = pur.product_id
                JOIN {$wpdb->prefix}usctdp_registration AS reg ON reg.purchase_id = pur.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                {$where_clause}
                GROUP BY prod.id
                ORDER BY gross_revenue DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        return $wpdb->get_results($query);
    }

    /**
     * Total PayPal transaction fees for the orders behind the in-range
     * 'revenue' entries get_session_earnings() would sum. PayPal fees never
     * land in the ledger (WooCommerce/PayPal computes them independently of
     * this plugin) - the WooCommerce PayPal Payments plugin instead writes
     * a fee breakdown to order meta '_ppcp_paypal_fees' (PayPalGateway::
     * FEES_META_KEY) on the `woocommerce_paypal_payments_order_captured`
     * action (see WCGatewayModule::run() in
     * woocommerce-paypal-payments/modules/ppcp-wc-gateway/src/WCGatewayModule.php)
     * - the standard capture path every normal card/PayPal-button checkout
     * goes through. This is the *same* meta key that plugin's own
     * FeesRenderer reads to show the "PayPal Fee:" line on the WooCommerce
     * order screen - deliberately not the separate, narrower
     * 'PayPal Transaction Fee' plain-string field the same hook also
     * writes (only when a slightly different condition holds), which can
     * be missing on an order this array is still present for.
     * Deliberately a single total, not allocated per-session - a single
     * order can span multiple sessions/products, and prorating a per-order
     * fee across its line items would be a guess, not a real number.
     *
     * That order meta is written through WC_Order::update_meta_data(), so
     * it lands wherever WooCommerce's order data store currently points -
     * wp_postmeta (keyed by post_id) on a legacy install, or
     * {$wpdb->prefix}wc_orders_meta (keyed by order_id) once High-
     * Performance Order Storage is enabled. This queries whichever one is
     * actually active rather than assuming, so it keeps working if a site
     * migrates to HPOS after this was written.
     *
     * The meta value is a serialized PHP array (WC_Order::save_meta_data()
     * stores it exactly as WordPress's own meta API would), so summing it
     * can't happen in SQL - each row is unserialized here and its
     * ['paypal_fee']['value'] pulled out, the same field FeesRenderer::render()
     * pulls '$breakdown['paypal_fee']' from.
     */
    public function get_paypal_fees_total($args)
    {
        global $wpdb;

        [$conditions, $where_args] = $this->earnings_base_conditions($args);
        array_unshift($conditions, "ulgr.account = %s");
        array_unshift($where_args, 'revenue');
        $conditions[] = "ulgr.order_id > 0";
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $hpos_enabled = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $meta_join = $hpos_enabled
            ? "JOIN {$wpdb->prefix}wc_orders_meta AS pm ON pm.order_id = o.order_id AND pm.meta_key = '_ppcp_paypal_fees'"
            : "JOIN {$wpdb->postmeta} AS pm ON pm.post_id = o.order_id AND pm.meta_key = '_ppcp_paypal_fees'";

        $query = $wpdb->prepare(
            "   SELECT pm.meta_value
                FROM (
                    SELECT DISTINCT ulgr.order_id
                    FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                    JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                    {$where_clause}
                ) AS o
                {$meta_join}",
            $where_args
        );

        $total = 0.0;
        foreach ($wpdb->get_col($query) as $serialized) {
            $breakdown = maybe_unserialize($serialized);
            if (is_array($breakdown) && isset($breakdown['paypal_fee']['value'])) {
                $total += (float) $breakdown['paypal_fee']['value'];
            }
        }
        return $total;
    }
}
